<?php

declare(strict_types=1);

namespace App\LDAP;

use App\Security\Encryption;

class LDAPConnection
{
    private $connection;
    private array $config;
    private Encryption $encryption;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->encryption = new Encryption();
    }

    public function connect(): bool
    {
        // CRITICAL: Set LDAPCONF environment variable first (production config)
        if (file_exists('/etc/ldap/ldap.conf')) {
            putenv('LDAPCONF=/etc/ldap/ldap.conf');
        }

        $protocol = $this->config['protocol'] === 'ldaps' ? 'ldaps' : 'ldap';
        $uri = sprintf('%s://%s:%d', $protocol, $this->config['host'], $this->config['port']);

        // 1. Try to load local ldap.conf (highest priority)
        $localLdapConfLocations = [
            __DIR__ . '/../../ldap.conf',
            dirname(__DIR__, 2) . '/ldap.conf',
            '/var/www/html/ldap.conf'
        ];

        foreach ($localLdapConfLocations as $confPath) {
            if (file_exists($confPath)) {
                putenv('LDAPCONF=' . realpath($confPath));
                break;
            }
        }

        // 2. Fallback to system ldap.conf if no local config
        if (!getenv('LDAPCONF') && file_exists('/etc/ldap/ldap.conf')) {
            putenv('LDAPCONF=/etc/ldap/ldap.conf');
        }

        // 3. AUTO-CONFIGURATION: Find and set the CA Certificate for TLS/SSL if not already in ldap.conf
        // (This runs even if LDAPCONF is set, but ldap.conf usually takes precedence)
        $certPaths = [
            // Docker/Linux path (if mounted or copied)
            '/var/www/html/ad-certificate.crt',
            // Relative path for local execution (ad-manager root)
            dirname(__DIR__, 2) . '/ad-certificate.crt',
            // Relative path for local execution (project root)
            dirname(__DIR__, 3) . '/ad-certificate.crt',
            // Windows absolute paths (common locations)
            'C:/dhcp-viewer/ad-certificate.crt',
            'D:/dhcp-viewer/ad-certificate.crt',
        ];

        $certFound = false;
        foreach ($certPaths as $certPath) {
            if (file_exists($certPath)) {
                // Determine absolute path
                $absPath = realpath($certPath);

                // Configure OpenLDAP environment variables
                putenv("LDAPTLS_CACERT=$absPath");
                putenv("TLS_CACERT=$absPath"); // Some older configs use this

                // Only enforce demand if we haven't set a custom ldap.conf (which might say 'never')
                if (!getenv('LDAPCONF')) {
                    putenv("LDAPTLS_REQCERT=demand");
                }

                $certFound = true;
                break;
            }
        }

        if (!$certFound) {
            // If no cert found, try to allow insecure connections for testing if configured
            // putenv("LDAPTLS_REQCERT=never");
        }

        $this->connection = ldap_connect($uri);

        if (!$this->connection) {
            throw new \RuntimeException('Failed to connect to LDAP server');
        }

        ldap_set_option($this->connection, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($this->connection, LDAP_OPT_REFERRALS, 0);
        ldap_set_option($this->connection, LDAP_OPT_NETWORK_TIMEOUT, $this->config['connection_timeout'] ?? 10);

        if ($this->config['use_tls'] && $this->config['protocol'] !== 'ldaps') {
            if (!ldap_start_tls($this->connection)) {
                $error = ldap_error($this->connection);
                throw new \RuntimeException('Failed to start TLS: ' . $error);
            }
        }

        return true;
    }

    public function bind(): bool
    {
        // Se um usuário está logado, a conexão DEVE usar suas credenciais.
        // Isso garante que todas as ações sejam auditadas em nome do usuário correto.
        if (isset($_SESSION['user_dn']) && isset($_SESSION['user_password_enc'])) {
            try {
                $userDn = $_SESSION['user_dn'];
                $userPassword = $this->encryption->decrypt($_SESSION['user_password_enc']);

                if (!@ldap_bind($this->connection, $userDn, $userPassword)) {
                    // Se o bind com o usuário da sessão falhar, a operação deve falhar.
                    // Isso pode acontecer se a senha foi alterada em outro lugar.
                    // Forçar o logout para que o usuário se autentique novamente com a nova senha.
                    throw new \RuntimeException('Falha na autenticação com as credenciais da sessão. A senha pode ter sido alterada. Por favor, faça login novamente.');
                }
                return true; // Bind com usuário da sessão bem-sucedido.

            } catch (\Exception $e) {
                // Se a descriptografia falhar (ex: chave de criptografia mudou), a sessão é inválida.
                throw new \RuntimeException('Sessão inválida. Por favor, faça login novamente. Detalhe: ' . $e->getMessage());
            }
        }

        // Se não houver usuário logado, usa o usuário de serviço.
        // Essencial para a tela de login e operações onde não há um usuário logado.
        $bindDn = $this->config['bind_dn'];
        $bindPassword = $this->encryption->decrypt($this->config['bind_password_enc']);

        if (!@ldap_bind($this->connection, $bindDn, $bindPassword)) {
            $error = ldap_error($this->connection);
            throw new \RuntimeException("Falha no bind do LDAP com o usuário de serviço: " . $error);
        }

        return true;
    }

    public function search(string $baseDn, string $filter, array $attributes = []): array
    {
        $this->validateLDAPFilter($filter);

        $result = @ldap_search($this->connection, $baseDn, $filter, $attributes);

        if (!$result) {
            throw new \RuntimeException('LDAP search failed: ' . ldap_error($this->connection));
        }

        $entries = ldap_get_entries($this->connection, $result);
        ldap_free_result($result);

        return $this->normalizeEntries($entries);
    }

    public function searchOne(string $baseDn, string $filter, array $attributes = []): ?array
    {
        $results = $this->search($baseDn, $filter, $attributes);
        return $results[0] ?? null;
    }

    public function modify(string $dn, array $entry): bool
    {
        $result = @ldap_modify($this->connection, $dn, $entry);

        if (!$result) {
            $error = ldap_error($this->connection);
            $errno = ldap_errno($this->connection);

            // Get extended diagnostic message if available
            @ldap_get_option($this->connection, LDAP_OPT_DIAGNOSTIC_MESSAGE, $extendedError);

            $errorMsg = "LDAP modify failed: $error (Error code: $errno)";
            if (!empty($extendedError)) {
                $errorMsg .= " | Extended: $extendedError";
            }

            throw new \RuntimeException($errorMsg);
        }

        return true;
    }

    public function add(string $dn, array $entry): bool
    {
        $logFile = __DIR__ . '/../../logs/ldap-add-debug.log';
        file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Attempting to add DN: $dn" . PHP_EOL, FILE_APPEND);
        file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Entry: " . json_encode($entry, JSON_PRETTY_PRINT) . PHP_EOL, FILE_APPEND);

        $result = @ldap_add($this->connection, $dn, $entry);

        if (!$result) {
            $error = ldap_error($this->connection);
            $errno = ldap_errno($this->connection);

            // Tentar obter diagnóstico detalhado
            ldap_get_option($this->connection, LDAP_OPT_DIAGNOSTIC_MESSAGE, $extendedError);

            $errorMsg = "LDAP add failed: $error (Error code: $errno)";
            if (!empty($extendedError)) {
                $errorMsg .= " | Extended: $extendedError";
            }
            $errorMsg .= " | DN: $dn";

            file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "ERROR: $errorMsg" . PHP_EOL, FILE_APPEND);
            file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Error: $error" . PHP_EOL, FILE_APPEND);
            file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Error number: $errno" . PHP_EOL, FILE_APPEND);
            file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Extended error: " . ($extendedError ?? 'N/A') . PHP_EOL, FILE_APPEND);

            throw new \RuntimeException($errorMsg);
        }

        file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Successfully added DN: $dn" . PHP_EOL, FILE_APPEND);
        return true;
    }

    public function delete(string $dn): bool
    {
        $result = @ldap_delete($this->connection, $dn);

        if (!$result) {
            throw new \RuntimeException('LDAP delete failed: ' . ldap_error($this->connection));
        }

        return true;
    }

    public function modifyPassword(string $dn, string $newPassword): bool
    {
        // Password changes require secure connection (LDAPS or StartTLS)
        if (!$this->isSecureConnection()) {
            throw new \RuntimeException('A senha não atende aos requisitos de complexidade do domínio (comprimento, caracteres especiais, etc). Este erro também pode ocorrer se o domínio exigir uma conexão segura (LDAPS ou StartTLS) para alterar senhas.');
        }


        // A validação de complexidade é feita pelo próprio AD.
        // Não validamos aqui para não rejeitar senhas que o AD aceitaria.

        // Use AD password change method
        $encodedPassword = $this->encodePassword($newPassword);

        // Clear any previous LDAP errors
        @ldap_get_option($this->connection, LDAP_OPT_ERROR_STRING, $previousError);

        try {
            $result = $this->modify($dn, [
                'unicodePwd' => $encodedPassword
            ]);

            return $result;
        } catch (\RuntimeException $e) {
            // Provide more specific error messages for password changes
            $error = $e->getMessage();

            if (stripos($error, 'Constraint violation') !== false || stripos($error, '0000052D') !== false) {
                throw new \RuntimeException('A senha não atende aos requisitos de complexidade do domínio. Verifique: comprimento mínimo, histórico de senhas, caracteres especiais obrigatórios.');
            } elseif (stripos($error, 'Insufficient access') !== false) {
                throw new \RuntimeException('Permissões insuficientes para alterar a senha. Verifique se o usuário tem direitos de "Reset Password" no AD.');
            } elseif (stripos($error, 'Unwilling to perform') !== false) {
                throw new \RuntimeException('O servidor AD recusou a operação. Isso pode ocorrer se a conexão não for segura (LDAPS/StartTLS) ou se a política de domínio impedir a alteração.');
            }

            throw $e;
        }
    }

    public function setPasswordMustChange(string $dn, bool $mustChange): bool
    {
        $value = $mustChange ? '0' : '-1';

        return $this->modify($dn, [
            'pwdLastSet' => $value
        ]);
    }

    public function enableAccount(string $dn): bool
    {
        $user = $this->searchOne($dn, '(objectClass=user)', ['userAccountControl']);

        if (!$user) {
            throw new \RuntimeException('User not found');
        }

        $uac = (int) $user['useraccountcontrol'];
        $uac = $uac & ~2; // Remove ACCOUNTDISABLE flag

        return $this->modify($dn, [
            'userAccountControl' => (string) $uac
        ]);
    }

    public function disableAccount(string $dn): bool
    {
        $user = $this->searchOne($dn, '(objectClass=user)', ['userAccountControl']);

        if (!$user) {
            throw new \RuntimeException('User not found');
        }

        $uac = (int) $user['useraccountcontrol'];
        $uac = $uac | 2; // Add ACCOUNTDISABLE flag

        return $this->modify($dn, [
            'userAccountControl' => (string) $uac
        ]);
    }

    public function addMemberToGroup(string $groupDn, string $memberDn): bool
    {
        $result = @ldap_mod_add($this->connection, $groupDn, [
            'member' => $memberDn
        ]);

        if (!$result) {
            $error = ldap_error($this->connection);
            if (stripos($error, 'Insufficient access') !== false) {
                $error .= ' (Dica: O grupo pode ser protegido pelo AdminSDHolder do AD. Verifique as permissões de segurança diretamente no objeto do grupo.)';
            }

            throw new \RuntimeException('Failed to add member: ' . $error);
        }

        return true;
    }

    public function removeMemberFromGroup(string $groupDn, string $memberDn): bool
    {
        $result = @ldap_mod_del($this->connection, $groupDn, [
            'member' => $memberDn
        ]);

        if (!$result) {
            $error = ldap_error($this->connection);
            if (stripos($error, 'Insufficient access') !== false) {
                $error .= ' (Dica: O grupo pode ser protegido pelo AdminSDHolder do AD. Verifique as permissões de segurança diretamente no objeto do grupo.)';
            }

            throw new \RuntimeException('Failed to remove member: ' . $error);
        }

        return true;
    }

    public function testConnection(): array
    {
        try {
            $this->connect();
            $this->bind();

            // Try a simple search
            $this->search($this->config['base_dn'], '(objectClass=*)', ['dn']);

            return ['success' => true, 'message' => 'Conexão bem-sucedida!'];

        } catch (\Exception $e) {
            $message = $e->getMessage();

            // Add specific hints for common LDAPS/TLS issues
            if (stripos($message, 'Can\'t contact LDAP server') !== false) {
                $message .= ' (Dica: Verifique o firewall na porta ' . ($this->config['port'] ?? '636') . ' e se o serviço LDAPS está ativo no controlador de domínio.)';
            } elseif (stripos($message, 'TLS') !== false || stripos($message, 'certificate') !== false) {
                $message .= ' (Dica: O servidor PHP pode não confiar no certificado SSL do AD. Verifique a configuração do `ldap.conf` e o `TLS_CACERT`.)';
            }

            return [
                'success' => false,
                'message' => $message
            ];
        }
    }

    private function encodePassword(string $password): string
    {
        return mb_convert_encoding('"' . $password . '"', 'UTF-16LE', 'UTF-8');
    }

    private function isSecureConnection(): bool
    {
        // Check if using LDAPS or if StartTLS was successfully initiated
        // For password changes, we need either LDAPS or StartTLS
        return $this->config['protocol'] === 'ldaps' ||
            ($this->config['use_tls'] == 1 && $this->config['protocol'] === 'ldap');
    }

    private function validatePasswordComplexity(string $password): bool
    {
        // Segue a política padrão do Active Directory:
        // - Mínimo de 7 caracteres
        // - Pelo menos 3 das 4 categorias: maiúscula, minúscula, número, especial
        if (strlen($password) < 7) {
            return false;
        }

        $categories = 0;
        if (preg_match('/[A-Z]/', $password))
            $categories++; // maiúscula
        if (preg_match('/[a-z]/', $password))
            $categories++; // minúscula
        if (preg_match('/[0-9]/', $password))
            $categories++; // número
        if (preg_match('/[^A-Za-z0-9]/', $password))
            $categories++; // especial

        // AD exige pelo menos 3 de 4 categorias
        return $categories >= 3;
    }

    private function validateLDAPFilter(string $filter): void
    {
        // Basic LDAP injection prevention
        // Check for null bytes
        if (strpos($filter, "\x00") !== false) {
            throw new \InvalidArgumentException('Invalid LDAP filter: null byte detected');
        }

        // Validate balanced parentheses
        $openCount = substr_count($filter, '(');
        $closeCount = substr_count($filter, ')');

        if ($openCount !== $closeCount) {
            throw new \InvalidArgumentException('Invalid LDAP filter: unbalanced parentheses');
        }

        // Must start with ( and end with ) or be *
        if ($filter !== '*' && (!str_starts_with($filter, '(') || !str_ends_with($filter, ')'))) {
            throw new \InvalidArgumentException('Invalid LDAP filter format');
        }
    }

    private function normalizeEntries(array $entries): array
    {
        $normalized = [];
        $count = $entries['count'] ?? 0;

        for ($i = 0; $i < $count; $i++) {
            $entry = [];
            $attributeCount = $entries[$i]['count'] ?? 0;

            for ($j = 0; $j < $attributeCount; $j++) {
                $attribute = $entries[$i][$j];
                $valueCount = $entries[$i][$attribute]['count'] ?? 0;

                if ($valueCount === 1) {
                    $entry[$attribute] = $entries[$i][$attribute][0];
                } else {
                    $entry[$attribute] = [];
                    for ($k = 0; $k < $valueCount; $k++) {
                        $entry[$attribute][] = $entries[$i][$attribute][$k];
                    }
                }
            }

            $entry['dn'] = $entries[$i]['dn'] ?? '';
            $normalized[] = $entry;
        }

        return $normalized;
    }

    public function close(): void
    {
        if ($this->connection) {
            ldap_unbind($this->connection);
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}
