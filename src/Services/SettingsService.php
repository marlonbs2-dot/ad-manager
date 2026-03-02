<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use PDO;

class SettingsService
{
    private PDO $db;
    private static array $cache = [];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Obter valor de uma configuração
     */
    public function get(string $key, ?string $default = null): ?string
    {
        // Verificar cache primeiro
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        try {
            $stmt = $this->db->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
            $stmt->execute([$key]);
            $result = $stmt->fetchColumn();

            $value = $result !== false ? $result : $default;

            // Armazenar no cache
            self::$cache[$key] = $value;

            return $value;
        } catch (\Exception $e) {
            return $default;
        }
    }

    /**
     * Definir valor de uma configuração
     */
    public function set(string $key, string $value, ?string $description = null): bool
    {
        try {
            $stmt = $this->db->prepare('
                INSERT INTO settings (setting_key, setting_value, description) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                    setting_value = VALUES(setting_value),
                    description = COALESCE(VALUES(description), description)
            ');

            $result = $stmt->execute([$key, $value, $description]);

            // Limpar cache
            unset(self::$cache[$key]);

            return $result;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Obter todas as configurações
     */
    public function getAll(): array
    {
        try {
            $stmt = $this->db->query('SELECT * FROM settings ORDER BY setting_key');
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Obter configurações das APIs
     */
    public function getApiSettings(): array
    {
        return [
            'dhcp_api_url' => $this->get('dhcp_api_url', 'https://10.168.11.80:5443'),
            'dhcp_api_key' => $this->get('dhcp_api_key', ''),
            'share_api_url' => $this->get('share_api_url', 'https://10.168.11.80:5002'),
            'dhcp_api_enabled' => $this->get('dhcp_api_enabled', '1') === '1',
            'share_api_enabled' => $this->get('share_api_enabled', '1') === '1',
            'api_timeout' => (int) $this->get('api_timeout', '30'),
            'api_retry_attempts' => (int) $this->get('api_retry_attempts', '3')
        ];
    }

    /**
     * Salvar configurações das APIs
     */
    public function saveApiSettings(array $settings): bool
    {
        try {
            $this->db->beginTransaction();

            foreach ($settings as $key => $value) {
                if (in_array($key, ['dhcp_api_url', 'dhcp_api_key', 'share_api_url', 'dhcp_api_enabled', 'share_api_enabled', 'api_timeout', 'api_retry_attempts'])) {
                    $this->set($key, (string) $value);
                }
            }

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    /**
     * Testar conectividade com uma API
     */
    public function testApiConnection(string $url): array
    {
        // Determinar endpoint correto baseado na URL
        $endpoint = '/health';
        if (strpos($url, ':5002') !== false) {
            // Share API usa /api/status
            $endpoint = '/api/status';
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url . $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'AD-Manager/1.0'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || !empty($error)) {
            return [
                'success' => false,
                'message' => 'Erro de conexão: ' . $error,
                'http_code' => 0
            ];
        }

        if ($httpCode !== 200) {
            return [
                'success' => false,
                'message' => 'API retornou código HTTP: ' . $httpCode,
                'http_code' => $httpCode,
                'response' => $response
            ];
        }

        // Verificar se a resposta é JSON válido
        $jsonData = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'message' => 'API retornou resposta inválida (não é JSON)',
                'http_code' => $httpCode,
                'response' => substr($response, 0, 200) . '...'
            ];
        }

        // Verificar se a API retornou sucesso
        if (!isset($jsonData['success']) || !$jsonData['success']) {
            return [
                'success' => false,
                'message' => 'API retornou erro: ' . ($jsonData['message'] ?? 'Erro desconhecido'),
                'http_code' => $httpCode,
                'response' => $response
            ];
        }

        return [
            'success' => true,
            'message' => 'Conexão bem-sucedida - ' . ($jsonData['service'] ?? 'API'),
            'http_code' => $httpCode,
            'response' => $response
        ];
    }

    /**
     * Limpar cache de configurações
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }

    // ─── PRINT SERVERS ───────────────────────────────────────────────────────────

    /** Lista todos os servidores de impressão */
    public function getPrintServers(bool $onlyEnabled = false): array
    {
        try {
            $sql = 'SELECT * FROM print_servers';
            $sql .= $onlyEnabled ? ' WHERE enabled = 1' : '';
            $sql .= ' ORDER BY name';
            return $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    /** Retorna um servidor de impressão por ID */
    public function getPrintServer(int $id): ?array
    {
        try {
            $stmt = $this->db->prepare('SELECT * FROM print_servers WHERE id = ?');
            $stmt->execute([$id]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /** Adiciona um servidor de impressão */
    public function addPrintServer(string $name, string $url, string $apiKey, string $description = '', bool $enabled = true): bool
    {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO print_servers (name, url, api_key, description, enabled) VALUES (?, ?, ?, ?, ?)'
            );
            return $stmt->execute([$name, $url, $apiKey, $description, $enabled ? 1 : 0]);
        } catch (\Exception $e) {
            return false;
        }
    }

    /** Atualiza um servidor de impressão */
    public function updatePrintServer(int $id, string $name, string $url, string $apiKey, string $description = '', bool $enabled = true): bool
    {
        try {
            $stmt = $this->db->prepare(
                'UPDATE print_servers SET name = ?, url = ?, api_key = ?, description = ?, enabled = ? WHERE id = ?'
            );
            return $stmt->execute([$name, $url, $apiKey, $description, $enabled ? 1 : 0, $id]);
        } catch (\Exception $e) {
            return false;
        }
    }

    /** Remove um servidor de impressão */
    public function deletePrintServer(int $id): bool
    {
        try {
            $stmt = $this->db->prepare('DELETE FROM print_servers WHERE id = ?');
            return $stmt->execute([$id]);
        } catch (\Exception $e) {
            return false;
        }
    }
}