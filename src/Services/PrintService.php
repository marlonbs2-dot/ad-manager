<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Print Service - Consome a Print API Node.js
 * Baseado em DhcpServiceHttp
 */
class PrintService
{
    private string $apiUrl;
    private string $apiKey;

    public function __construct(array $server)
    {
        $this->apiUrl = rtrim($server['url'], '/');
        $this->apiKey = $server['api_key'];
    }

    /**
     * Faz requisição HTTP para a Print API
     */
    private function apiRequest(string $method, string $endpoint, ?array $data = null): array
    {
        $url = $this->apiUrl . $endpoint;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-API-Key: ' . $this->apiKey,
                'Expect:',  // desabilita Expect: 100-continue que causa problemas em POST com HTTPS
            ],
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        $body = $data !== null ? json_encode($data) : null;

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body ?? '{}');
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body ?? '{}');
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception("Erro de conexão com Print API: $error");
        }

        if ($httpCode !== 200) {
            $errorData = json_decode($response, true);
            $message = $errorData['message'] ?? "Erro desconhecido (HTTP $httpCode)";
            throw new \Exception("Print API retornou erro ($httpCode): $message");
        }

        $result = json_decode($response, true);

        if (!$result || !isset($result['success'])) {
            throw new \Exception('Resposta inválida da Print API');
        }

        if (!$result['success']) {
            throw new \Exception($result['message'] ?? 'Operação falhou');
        }

        return $result;
    }

    /** Lista todas as impressoras do servidor */
    public function getPrinters(): array
    {
        return $this->apiRequest('GET', '/api/printers')['data'] ?? [];
    }

    /** Lista jobs de uma impressora */
    public function getPrinterJobs(string $printerName): array
    {
        return $this->apiRequest('GET', '/api/printers/' . rawurlencode($printerName) . '/jobs')['data'] ?? [];
    }

    /** Pausa impressora */
    public function pausePrinter(string $printerName): void
    {
        $this->apiRequest('POST', '/api/printers/' . rawurlencode($printerName) . '/pause');
    }

    /** Retoma impressora */
    public function resumePrinter(string $printerName): void
    {
        $this->apiRequest('POST', '/api/printers/' . rawurlencode($printerName) . '/resume');
    }

    /** Limpa a fila de todos os jobs */
    public function clearQueue(string $printerName): void
    {
        $this->apiRequest('DELETE', '/api/printers/' . rawurlencode($printerName) . '/jobs');
    }

    /** Cancela um job específico */
    public function cancelJob(string $printerName, int $jobId): void
    {
        $this->apiRequest('DELETE', '/api/printers/' . rawurlencode($printerName) . '/jobs/' . $jobId);
    }

    /** Pausa um job específico */
    public function pauseJob(string $printerName, int $jobId): void
    {
        $this->apiRequest('POST', '/api/printers/' . rawurlencode($printerName) . '/jobs/' . $jobId . '/pause');
    }

    /** Retoma um job específico */
    public function resumeJob(string $printerName, int $jobId): void
    {
        $this->apiRequest('POST', '/api/printers/' . rawurlencode($printerName) . '/jobs/' . $jobId . '/resume');
    }

    /** Lista drivers de impressora instalados no servidor */
    public function getDrivers(): array
    {
        return $this->apiRequest('GET', '/api/drivers')['data'] ?? [];
    }

    /** Instala nova impressora no servidor */
    public function createPrinter(array $data): void
    {
        $this->apiRequest('POST', '/api/printers', $data);
    }

    /** Remove impressora do servidor */
    public function deletePrinter(string $printerName): void
    {
        $this->apiRequest('DELETE', '/api/printers/' . rawurlencode($printerName));
    }

    /** Lista portas de impressora disponíveis no servidor */
    public function getPorts(): array
    {
        return $this->apiRequest('GET', '/api/ports')['data'] ?? [];
    }

    /** Renomeia e/ou altera porta da impressora */
    public function updatePrinter(string $printerName, ?string $newName = null, ?string $portName = null): void
    {
        $this->apiRequest('PUT', '/api/printers/' . rawurlencode($printerName), [
            'newName' => $newName,
            'portName' => $portName
        ]);
    }

    /** Testa conectividade com a API */
    public static function testConnection(string $url, string $apiKey): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => rtrim($url, '/') . '/health',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['X-API-Key: ' . $apiKey],
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'message' => 'Erro de conexão: ' . $error];
        }

        if ($httpCode !== 200) {
            return ['success' => false, 'message' => "HTTP $httpCode"];
        }

        $data = json_decode($response, true);
        $serviceInfo = 'Print API';
        if ($data && isset($data['hostname'])) {
            $serviceInfo .= ' — ' . $data['hostname'];
        }

        return ['success' => true, 'message' => "Conexão OK — $serviceInfo"];
    }
}
