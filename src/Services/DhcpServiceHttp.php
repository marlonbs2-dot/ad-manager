<?php

declare(strict_types=1);

namespace App\Services;

/**
 * DHCP Service - Versão HTTP API
 * Conecta-se à API Node.js que roda no Windows Host
 */
class DhcpServiceHttp
{
    private string $apiUrl;
    private string $apiKey;
    private SettingsService $settings;

    public function __construct()
    {
        $this->settings = new SettingsService();

        // URL da API DHCP (configurável via interface web)
        $this->apiUrl = $this->settings->get('dhcp_api_url', 'https://10.168.11.80:5443');
        // Prioritize database setting, fallback to ENV
        $this->apiKey = $this->settings->get('dhcp_api_key', $_ENV['DHCP_API_KEY'] ?? 'change-this-in-production');
    }

    /**
     * Faz requisição HTTP para a API
     */
    private function apiRequest(string $method, string $endpoint, ?array $data = null): array
    {
        $url = $this->apiUrl . $endpoint;

        $ch = curl_init();

        $headers = [
            'Content-Type: application/json',
            'X-API-Key: ' . $this->apiKey
        ];

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Accept self-signed certificates
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        // DEBUG LOGGING
        $logFile = __DIR__ . '/../../logs/dhcp_api_debug.log';
        $logEntry = date('[Y-m-d H:i:s] ') . "Request: $method $url\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        // DEBUG LOGGING
        $logEntry = date('[Y-m-d H:i:s] ') . "Response Code: $httpCode\n";
        if ($error)
            $logEntry .= "Curl Error: $error\n";
        $logEntry .= "Response Body: " . substr($response, 0, 500) . "\n----------------\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);

        curl_close($ch);

        if ($error) {
            throw new \Exception("Erro de conexão com API DHCP: $error");
        }

        if ($httpCode !== 200) {
            $errorData = json_decode($response, true);
            $message = $errorData['message'] ?? 'Erro desconhecido';
            throw new \Exception("API DHCP retornou erro ($httpCode): $message");
        }

        $result = json_decode($response, true);

        if (!$result || !isset($result['success'])) {
            throw new \Exception('Resposta inválida da API DHCP');
        }

        if (!$result['success']) {
            throw new \Exception($result['message'] ?? 'Operação falhou');
        }

        return $result;
    }

    /**
     * Obter todos os escopos DHCP
     */
    public function getScopes(): array
    {
        $result = $this->apiRequest('GET', '/api/scopes');
        return $result['data'] ?? [];
    }

    /**
     * Obter reservas de um escopo
     */
    public function getReservations(string $scopeId): array
    {
        $result = $this->apiRequest('GET', "/api/scopes/$scopeId/reservations");
        return $result['data'] ?? [];
    }

    /**
     * Obter leases (IPs distribuídos) de um escopo
     */
    public function getLeases(string $scopeId): array
    {
        $result = $this->apiRequest('GET', "/api/scopes/$scopeId/leases");
        return $result['data'] ?? [];
    }

    /**
     * Criar nova reserva
     */
    public function createReservation(
        string $scopeId,
        string $ipAddress,
        string $macAddress,
        string $name,
        string $description = ''
    ): void {
        $this->apiRequest('POST', '/api/reservations', [
            'scopeId' => $scopeId,
            'ipAddress' => $ipAddress,
            'macAddress' => $macAddress,
            'name' => $name,
            'description' => $description
        ]);
    }

    /**
     * Remover reserva
     */
    public function deleteReservation(string $scopeId, string $ipAddress): void
    {
        $this->apiRequest('DELETE', "/api/scopes/$scopeId/reservations/$ipAddress");
    }

    /**
     * Editar reserva existente
     */
    public function updateReservation(
        string $scopeId,
        string $currentIpAddress,
        string $newIpAddress,
        string $macAddress,
        string $name,
        string $description = ''
    ): void {
        $this->apiRequest('PUT', "/api/scopes/$scopeId/reservations/$currentIpAddress", [
            'newIpAddress' => $newIpAddress,
            'macAddress' => $macAddress,
            'name' => $name,
            'description' => $description
        ]);
    }
}
