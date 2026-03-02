<?php

declare(strict_types=1);

namespace App\Services;

use App\LDAP\LDAPConnection;

class UserService
{
    private ADConfigService $adConfig;
    private PermissionService $permissions;
    private AuditService $audit;

    public function __construct()
    {
        $this->adConfig = new ADConfigService();
        $this->permissions = new PermissionService();
        $this->audit = new AuditService();
    }

    public function searchUsers(string $query, string $userDn): array
    {
        $this->logDebug('=== User Search Start ===');
        $this->logDebug('Query: ' . $query);
        $this->logDebug('User DN: ' . $userDn);
        
        $config = $this->adConfig->getActiveConfig();
        
        if (!$config) {
            throw new \RuntimeException('AD configuration not found');
        }

        $this->logDebug('Base DN: ' . $config['base_dn']);

        $ldap = new LDAPConnection($config);
        $ldap->connect();
        $ldap->bind();

        // Build search filter
        $escapedQuery = ldap_escape($query, '', LDAP_ESCAPE_FILTER);
        $filter = sprintf(
            '(&(objectClass=user)(objectCategory=person)(|(sAMAccountName=*%s*)(displayName=*%s*)(mail=*%s*)))',
            $escapedQuery,
            $escapedQuery,
            $escapedQuery
        );

        $this->logDebug('LDAP Filter: ' . $filter);

        $attributes = [
            'dn', 'sAMAccountName', 'displayName', 'mail', 
            'telephoneNumber', 'department', 'title', 
            'userAccountControl', 'lastLogon', 'whenCreated'
        ];

        $results = $ldap->search($config['base_dn'], $filter, $attributes);
        $this->logDebug('LDAP search returned: ' . count($results) . ' results');

        // Filter by permissions
        $results = $this->permissions->filterByPermission($results, $userDn);
        $this->logDebug('After permission filter: ' . count($results) . ' results');

        // Normalize results
        $normalized = array_map([$this, 'normalizeUser'], $results);
        $this->logDebug('Returning ' . count($normalized) . ' normalized results');
        
        return $normalized;
    }

    public function searchOUs(): array
    {
        $config = $this->adConfig->getActiveConfig();
        if (!$config) {
            throw new \RuntimeException('AD configuration not found');
        }

        $ldap = new LDAPConnection($config);
        $ldap->connect();
        $ldap->bind();

        $filter = '(objectClass=organizationalUnit)';
        $attributes = ['ou', 'distinguishedName'];

        $results = $ldap->search($config['base_dn'], $filter, $attributes);

        // Sort by DN length to get a somewhat hierarchical order
        usort($results, fn($a, $b) => strlen($a['dn']) <=> strlen($b['dn']));

        return array_map(fn($ou) => [
            'name' => $ou['ou'] ?? 'N/A',
            'dn' => $ou['dn'] ?? ''
        ], $results);
    }

    private function logDebug(string $message): void
    {
        file_put_contents(
            '/var/www/ad-manager/logs/auth-debug.log',
            date('[Y-m-d H:i:s] ') . $message . PHP_EOL,
            FILE_APPEND
        );
    }

    public function getUser(string $dn, string $userDn): ?array
    {
        if (!$this->permissions->canManageUser($userDn, $dn)) {
            throw new \RuntimeException('Permission denied');
        }

        $config = $this->adConfig->getActiveConfig();
        
        if (!$config) {
            throw new \RuntimeException('AD configuration not found');
        }

        $ldap = new LDAPConnection($config);
        $ldap->connect();
        $ldap->bind();

        $attributes = [
            'dn', 'sAMAccountName', 'displayName', 'mail', 
            'telephoneNumber', 'department', 'title', 
            'userAccountControl', 'lastLogon', 'whenCreated',
            'memberOf'
        ];

        $user = $ldap->searchOne($dn, '(objectClass=user)', $attributes);

        return $user ? $this->normalizeUser($user) : null;
    }

    public function resetPassword(
        string $targetDn,
        string $newPassword,
        bool $mustChange,
        string $userDn,
        string $username,
        string $ip
    ): bool {
        if (!$this->permissions->canResetPassword($userDn, $targetDn)) {
            $this->audit->log($username, 'reset_password', $targetDn, $ip, 'failure', [
                'reason' => 'Permission denied'
            ]);
            throw new \RuntimeException('Permission denied');
        }

        $config = $this->adConfig->getActiveConfig();
        
        if (!$config) {
            throw new \RuntimeException('AD configuration not found');
        }

        try {
            $ldap = new LDAPConnection($config);
            $ldap->connect();
            $ldap->bind();

            // Reset password
            $ldap->modifyPassword($targetDn, $newPassword);

            // Set must change flag
            if ($mustChange) {
                $ldap->setPasswordMustChange($targetDn, true);
            }

            $this->audit->log($username, 'reset_password', $targetDn, $ip, 'success', [
                'must_change' => $mustChange
            ]);

            return true;

        } catch (\Exception $e) {
            // Provide a more specific error for password policy violations
            if (stripos($e->getMessage(), 'unwilling to perform') !== false) {
                $isSecureConnection = ($config['protocol'] === 'ldaps' || ($config['use_tls'] ?? false));
                
                $errorMessage = 'A senha não atende aos requisitos de complexidade do domínio (comprimento, caracteres especiais, etc).';
                if (!$isSecureConnection) {
                    $errorMessage .= ' Este erro também pode ocorrer se o domínio exigir uma conexão segura (LDAPS ou StartTLS) para alterar senhas.';
                }

                $this->audit->log($username, 'reset_password', $targetDn, $ip, 'failure', [
                    'reason' => 'Password policy violation'
                ]);
                throw new \RuntimeException($errorMessage);
            }

            $this->audit->log($username, 'reset_password', $targetDn, $ip, 'error', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function generatePassword(int $length = 12): string
    {
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $special = '!@#$%^&*()_+-=[]{}|;:,.<>?';

        $all = $uppercase . $lowercase . $numbers . $special;

        $password = '';
        
        // Ensure at least one of each type
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        $password .= $special[random_int(0, strlen($special) - 1)];

        // Fill the rest
        for ($i = 4; $i < $length; $i++) {
            $password .= $all[random_int(0, strlen($all) - 1)];
        }

        // Shuffle
        return str_shuffle($password);
    }

    public function enableUser(string $targetDn, string $userDn, string $username, string $ip): bool
    {
        if (!$this->permissions->canManageUser($userDn, $targetDn)) {
            $this->audit->log($username, 'enable_user', $targetDn, $ip, 'failure', [
                'reason' => 'Permission denied'
            ]);
            throw new \RuntimeException('Permission denied');
        }

        $config = $this->adConfig->getActiveConfig();
        
        if (!$config) {
            throw new \RuntimeException('AD configuration not found');
        }

        try {
            $ldap = new LDAPConnection($config);
            $ldap->connect();
            $ldap->bind();

            $ldap->enableAccount($targetDn);

            $this->audit->log($username, 'enable_user', $targetDn, $ip, 'success');

            return true;

        } catch (\Exception $e) {
            $this->audit->log($username, 'enable_user', $targetDn, $ip, 'error', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function disableUser(string $targetDn, string $userDn, string $username, string $ip): bool
    {
        if (!$this->permissions->canManageUser($userDn, $targetDn)) {
            $this->audit->log($username, 'disable_user', $targetDn, $ip, 'failure', [
                'reason' => 'Permission denied'
            ]);
            throw new \RuntimeException('Permission denied');
        }

        $config = $this->adConfig->getActiveConfig();
        
        if (!$config) {
            throw new \RuntimeException('AD configuration not found');
        }

        try {
            $ldap = new LDAPConnection($config);
            $ldap->connect();
            $ldap->bind();

            $ldap->disableAccount($targetDn);

            $this->audit->log($username, 'disable_user', $targetDn, $ip, 'success');

            return true;

        } catch (\Exception $e) {
            $this->audit->log($username, 'disable_user', $targetDn, $ip, 'error', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function createUser(array $data, string $userDn, string $username, string $ip, bool $copyGroups = false): bool
    {
        // For creating users, we check if the operator can manage the target OU.
        // We use the target OU itself as the target DN for the permission check.
        if (!$this->permissions->canManageUser($userDn, $data['ou'])) {
            $this->audit->log($username, 'create_user', $data['ou'], $ip, 'failure', [
                'reason' => 'Permission denied on target OU'
            ]);
            throw new \RuntimeException('Permissão negada para criar usuários nesta Unidade Organizacional (OU).');
        }

        $config = $this->adConfig->getActiveConfig();
        if (!$config) {
            throw new \RuntimeException('AD configuration not found');
        }

        // Escape the Common Name to handle special characters like commas, etc.
        // This is crucial to prevent "Invalid DN syntax" errors from LDAP.
        $escapedCn = ldap_escape($data['display_name'], '', LDAP_ESCAPE_DN);
        $cn = $data['display_name']; // Keep original for attributes
        $targetDn = "CN={$escapedCn}," . $data['ou'];

        // O atributo 'sn' (sobrenome) é obrigatório no AD
        // Se não for fornecido, usar o primeiro nome como fallback
        $lastName = !empty($data['last_name']) ? $data['last_name'] : $data['first_name'];

        // Validar se a senha contém partes do nome do usuário (requisito comum do AD)
        $passwordLower = strtolower($data['password']);
        $firstNameLower = strtolower($data['first_name']);
        $lastNameLower = strtolower($lastName);
        $usernameLower = strtolower($data['username']);
        $displayNameLower = strtolower($data['display_name']);
        
        // Verificar se a senha contém partes significativas do nome (3+ caracteres)
        if (strlen($firstNameLower) >= 3 && stripos($passwordLower, $firstNameLower) !== false) {
            throw new \RuntimeException("A senha não pode conter o primeiro nome do usuário ('{$data['first_name']}').");
        }
        if (strlen($lastNameLower) >= 3 && stripos($passwordLower, $lastNameLower) !== false) {
            throw new \RuntimeException("A senha não pode conter o sobrenome do usuário ('{$lastName}').");
        }
        
        // Verificar partes do username (dividir por ponto)
        $usernameParts = explode('.', $usernameLower);
        foreach ($usernameParts as $part) {
            if (strlen($part) >= 3 && stripos($passwordLower, $part) !== false) {
                throw new \RuntimeException("A senha não pode conter partes do nome de login ('{$part}').");
            }
        }

        // Basic user object
        $entry = [
            'objectClass' => ['top', 'person', 'organizationalPerson', 'user'],
            'cn' => $cn,
            'givenName' => $data['first_name'],
            'sn' => $lastName,
            'displayName' => $data['display_name'],
            'sAMAccountName' => $data['username'],
            'userPrincipalName' => $data['username'] . '@' . $this->getDomainFromBaseDN($config['base_dn']),
        ];

        // IMPORTANTE: Sempre criar a conta como DESABILITADA (514) primeiro
        // O AD não permite criar usuários ativos sem senha
        // Depois de definir a senha, habilitamos a conta se necessário
        $entry['userAccountControl'] = '514'; // 514 = Conta Desabilitada

        $logFile = __DIR__ . '/../../logs/create-user-debug.log';
        
        try {
            file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "=== Starting user creation ===" . PHP_EOL, FILE_APPEND);
            file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Target DN: $targetDn" . PHP_EOL, FILE_APPEND);
            file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Username: {$data['username']}" . PHP_EOL, FILE_APPEND);
            file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Display Name: {$data['display_name']}" . PHP_EOL, FILE_APPEND);
            
            $ldap = new LDAPConnection($config);
            $ldap->connect();
            $ldap->bind();

            file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "LDAP connected and bound successfully" . PHP_EOL, FILE_APPEND);

            // Add the user
            file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Adding user to AD..." . PHP_EOL, FILE_APPEND);
            $ldap->add($targetDn, $entry);
            file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "User added successfully" . PHP_EOL, FILE_APPEND);

            // Set the password
            file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Setting password..." . PHP_EOL, FILE_APPEND);
            $ldap->modifyPassword($targetDn, $data['password']);
            file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Password set successfully" . PHP_EOL, FILE_APPEND);

            // Set must change password flag
            if ($data['must_change']) {
                file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Setting must change password flag..." . PHP_EOL, FILE_APPEND);
                $ldap->setPasswordMustChange($targetDn, true);
            }

            // Habilitar a conta se o usuário NÃO marcou "conta desabilitada"
            // Por padrão, criamos desabilitada e agora habilitamos se necessário
            if (!$data['is_disabled']) {
                file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Enabling account..." . PHP_EOL, FILE_APPEND);
                $ldap->enableAccount($targetDn);
                file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Account enabled successfully" . PHP_EOL, FILE_APPEND);
            } else {
                file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Account will remain disabled as requested" . PHP_EOL, FILE_APPEND);
            }

            file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "User creation completed successfully!" . PHP_EOL, FILE_APPEND);

            // Se copyGroups está ativado e há grupos para copiar
            if ($copyGroups && !empty($data['copy_groups'])) {
                file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Copying groups from template user..." . PHP_EOL, FILE_APPEND);
                
                $groupsCopied = 0;
                $groupsFailed = 0;
                
                foreach ($data['copy_groups'] as $groupDn) {
                    try {
                        file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Adding to group: $groupDn" . PHP_EOL, FILE_APPEND);
                        $ldap->addMemberToGroup($groupDn, $targetDn);
                        $groupsCopied++;
                    } catch (\Exception $e) {
                        file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Failed to add to group $groupDn: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
                        $groupsFailed++;
                    }
                }
                
                file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Groups copied: $groupsCopied, Failed: $groupsFailed" . PHP_EOL, FILE_APPEND);
            }

            $this->audit->log($username, 'create_user', $targetDn, $ip, 'success', [
                'created_username' => $data['username'],
                'copied_from' => $data['copy_from_dn'] ?? null,
                'groups_copied' => $groupsCopied ?? 0
            ]);

            return true;
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "ERROR: $errorMsg" . PHP_EOL, FILE_APPEND);
            file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Stack trace: " . $e->getTraceAsString() . PHP_EOL, FILE_APPEND);
            
            // Fornecer mensagens mais específicas para erros comuns
            if (stripos($errorMsg, 'unwilling to perform') !== false) {
                $isSecureConnection = ($config['protocol'] === 'ldaps' || ($config['use_tls'] ?? false));
                
                $detailedMsg = 'Não foi possível criar o usuário.\n\n';
                
                if (!$isSecureConnection) {
                    $detailedMsg .= '⚠️ PROBLEMA DETECTADO: Conexão NÃO segura!\n\n';
                    $detailedMsg .= 'O Active Directory está configurado para EXIGIR conexão segura (LDAPS ou StartTLS) ao definir senhas.\n\n';
                    $detailedMsg .= 'SOLUÇÃO:\n';
                    $detailedMsg .= '1. Vá em Configurações do Sistema\n';
                    $detailedMsg .= '2. Altere o Protocolo para "LDAPS"\n';
                    $detailedMsg .= '3. Altere a Porta para "636"\n';
                    $detailedMsg .= '4. Ou ative "Usar StartTLS" se estiver usando porta 389\n\n';
                    $detailedMsg .= 'Configuração atual: ' . strtoupper($config['protocol']) . ' na porta ' . $config['port'];
                } else {
                    $detailedMsg .= 'A senha pode não atender aos requisitos de complexidade do domínio:\n';
                    $detailedMsg .= '   - Mínimo de 8 caracteres\n';
                    $detailedMsg .= '   - Letras maiúsculas e minúsculas\n';
                    $detailedMsg .= '   - Números\n';
                    $detailedMsg .= '   - Caracteres especiais (!@#$%^&*)\n';
                    $detailedMsg .= '   - Não pode conter o nome do usuário\n';
                    $detailedMsg .= '   - Não pode ser uma das últimas senhas usadas';
                }
                
                $this->audit->log($username, 'create_user', $targetDn, $ip, 'error', ['error' => 'Password policy or security requirements not met']);
                throw new \RuntimeException($detailedMsg);
            }
            
            if (stripos($errorMsg, 'already exists') !== false || stripos($errorMsg, 'entry already exists') !== false) {
                $this->audit->log($username, 'create_user', $targetDn, $ip, 'error', ['error' => 'User already exists']);
                throw new \RuntimeException('Já existe um usuário com este nome ou login no Active Directory.');
            }
            
            $this->audit->log($username, 'create_user', $targetDn, $ip, 'error', ['error' => $errorMsg]);
            throw $e;
        }
    }

    private function normalizeUser(array $user): array
    {
        $uac = (int) ($user['useraccountcontrol'] ?? 0);
        $isDisabled = ($uac & 2) !== 0;

        return [
            'dn' => $user['dn'] ?? '',
            'username' => $user['samaccountname'] ?? '',
            'display_name' => $user['displayname'] ?? '',
            'email' => $user['mail'] ?? '',
            'phone' => $user['telephonenumber'] ?? '',
            'department' => $user['department'] ?? '',
            'title' => $user['title'] ?? '',
            'is_disabled' => $isDisabled,
            'last_logon' => $this->convertFileTime($user['lastlogon'] ?? '0'),
            'created_at' => $user['whencreated'] ?? '',
            'groups' => $user['memberof'] ?? []
        ];
    }

    private function convertFileTime(string $fileTime): ?string
    {
        if ($fileTime === '0' || empty($fileTime)) {
            return null;
        }

        $timestamp = (int) $fileTime;
        $unixTimestamp = ($timestamp / 10000000) - 11644473600;

        return date('Y-m-d H:i:s', (int) $unixTimestamp);
    }

    private function getDomainFromBaseDN(string $baseDn): string
    {
        $domainParts = [];
        $dnComponents = ldap_explode_dn($baseDn, 0);
        
        if ($dnComponents === false) {
            return '';
        }
        
        foreach ($dnComponents as $key => $component) {
            // ldap_explode_dn retorna um array com 'count' como primeiro elemento (inteiro)
            // Ignoramos elementos que não são strings
            if ($key === 'count' || !is_string($component)) {
                continue;
            }
            
            if (str_starts_with(strtoupper($component), 'DC=')) {
                $domainParts[] = substr($component, 3);
            }
        }
        return implode('.', $domainParts);
    }
}
