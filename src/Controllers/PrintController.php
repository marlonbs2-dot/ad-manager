<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\PrintService;
use App\Services\SettingsService;
use App\Services\AuditService;
use App\Security\CSRF;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class PrintController
{
    private SettingsService $settings;
    private AuditService $audit;

    public function __construct()
    {
        $this->settings = new SettingsService();
        $this->audit = new AuditService();
    }

    private function getClientIp(): string
    {
        foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                return strpos($ip, ',') !== false ? trim(explode(',', $ip)[0]) : $ip;
            }
        }
        return '127.0.0.1';
    }

    private function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    private function requireAuth(Response $response): ?Response
    {
        if (!isset($_SESSION['user'])) {
            return $this->jsonResponse($response, ['success' => false, 'message' => 'Não autenticado'], 401);
        }
        return null;
    }

    private function getServer(int $serverId): array
    {
        $server = $this->settings->getPrintServer($serverId);
        if (!$server) {
            throw new \Exception("Servidor de impressão não encontrado (ID: $serverId)");
        }
        if (!$server['enabled']) {
            throw new \Exception("Servidor de impressão desativado");
        }
        return $server;
    }

    /** Renderiza a página principal de impressão */
    public function index(Request $request, Response $response): Response
    {
        if (!isset($_SESSION['user'])) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $servers = $this->settings->getPrintServers();

        $data = [
            'title' => 'Gerenciamento de Impressoras',
            'user' => $_SESSION['user'] ?? null,
            'servers' => $servers
        ];

        ob_start();
        require __DIR__ . '/../../views/print.php';
        $html = ob_get_clean();

        $response->getBody()->write($html);
        return $response;
    }

    /** GET /print/api/servers */
    public function getServers(Request $request, Response $response): Response
    {
        if ($err = $this->requireAuth($response))
            return $err;
        return $this->jsonResponse($response, [
            'success' => true,
            'data' => $this->settings->getPrintServers()
        ]);
    }

    /** GET /print/api/servers/{serverId}/printers */
    public function getPrinters(Request $request, Response $response, array $args): Response
    {
        if ($err = $this->requireAuth($response))
            return $err;
        try {
            $server = $this->getServer((int) $args['serverId']);
            $printers = (new PrintService($server))->getPrinters();
            return $this->jsonResponse($response, [
                'success' => true,
                'message' => count($printers) . ' impressoras encontradas',
                'data' => $printers
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /** GET /print/api/servers/{serverId}/printers/{name}/jobs */
    public function getPrinterJobs(Request $request, Response $response, array $args): Response
    {
        if ($err = $this->requireAuth($response))
            return $err;
        try {
            $server = $this->getServer((int) $args['serverId']);
            $name = urldecode($args['name']);
            $jobs = (new PrintService($server))->getPrinterJobs($name);
            return $this->jsonResponse($response, [
                'success' => true,
                'message' => count($jobs) . ' jobs encontrados',
                'data' => $jobs
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /** GET /print/api/servers/{serverId}/ports */
    public function getPorts(Request $request, Response $response, array $args): Response
    {
        if ($err = $this->requireAuth($response))
            return $err;
        try {
            $server = $this->getServer((int) $args['serverId']);
            $ports = (new PrintService($server))->getPorts();
            return $this->jsonResponse($response, ['success' => true, 'data' => $ports]);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /** PUT /print/api/servers/{serverId}/printers/{name} */
    public function updatePrinter(Request $request, Response $response, array $args): Response
    {
        if ($err = $this->requireAuth($response))
            return $err;
        try {
            $data = $request->getParsedBody();
            if (!CSRF::validateToken($data['csrf_token'] ?? null)) {
                return $this->jsonResponse($response, ['success' => false, 'message' => 'Token CSRF inválido'], 403);
            }
            $server = $this->getServer((int) $args['serverId']);
            $name = urldecode($args['name']);
            $newName = !empty($data['newName']) ? $data['newName'] : null;
            $portName = !empty($data['portName']) ? $data['portName'] : null;
            (new PrintService($server))->updatePrinter($name, $newName, $portName);
            $this->audit->log(
                $_SESSION['user']['username'] ?? 'unknown',
                'print_update_printer',
                null,
                $this->getClientIp(),
                'success',
                ['server_id' => $args['serverId'], 'printer' => $name, 'new_name' => $newName, 'port' => $portName]
            );
            return $this->jsonResponse($response, ['success' => true, 'message' => 'Impressora atualizada']);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /** POST /print/api/servers/{serverId}/printers/{name}/pause */
    public function pausePrinter(Request $request, Response $response, array $args): Response
    {
        if ($err = $this->requireAuth($response))
            return $err;
        try {
            $data = $request->getParsedBody();
            if (!CSRF::validateToken($data['csrf_token'] ?? null)) {
                return $this->jsonResponse($response, ['success' => false, 'message' => 'Token CSRF inválido'], 403);
            }
            $server = $this->getServer((int) $args['serverId']);
            $name = urldecode($args['name']);
            (new PrintService($server))->pausePrinter($name);
            $this->audit->log($_SESSION['user']['username'] ?? 'unknown', 'print_pause_printer', null, $this->getClientIp(), 'success', ['server_id' => $args['serverId'], 'printer' => $name]);
            return $this->jsonResponse($response, ['success' => true, 'message' => "Impressora \"$name\" pausada"]);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /** POST /print/api/servers/{serverId}/printers/{name}/resume */
    public function resumePrinter(Request $request, Response $response, array $args): Response
    {
        if ($err = $this->requireAuth($response))
            return $err;
        try {
            $data = $request->getParsedBody();
            if (!CSRF::validateToken($data['csrf_token'] ?? null)) {
                return $this->jsonResponse($response, ['success' => false, 'message' => 'Token CSRF inválido'], 403);
            }
            $server = $this->getServer((int) $args['serverId']);
            $name = urldecode($args['name']);
            (new PrintService($server))->resumePrinter($name);
            $this->audit->log($_SESSION['user']['username'] ?? 'unknown', 'print_resume_printer', null, $this->getClientIp(), 'success', ['server_id' => $args['serverId'], 'printer' => $name]);
            return $this->jsonResponse($response, ['success' => true, 'message' => "Impressora \"$name\" retomada"]);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /** DELETE /print/api/servers/{serverId}/printers/{name}/jobs */
    public function clearQueue(Request $request, Response $response, array $args): Response
    {
        if ($err = $this->requireAuth($response))
            return $err;
        try {
            $csrfToken = $request->getHeaderLine('X-CSRF-Token') ?: ($request->getQueryParams()['csrf_token'] ?? null);
            if (!CSRF::validateToken($csrfToken)) {
                return $this->jsonResponse($response, ['success' => false, 'message' => 'Token CSRF inválido'], 403);
            }
            $server = $this->getServer((int) $args['serverId']);
            $name = urldecode($args['name']);
            (new PrintService($server))->clearQueue($name);
            $this->audit->log($_SESSION['user']['username'] ?? 'unknown', 'print_clear_queue', null, $this->getClientIp(), 'success', ['server_id' => $args['serverId'], 'printer' => $name]);
            return $this->jsonResponse($response, ['success' => true, 'message' => "Fila de \"$name\" limpa"]);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /** DELETE /print/api/servers/{serverId}/printers/{name}/jobs/{jobId} */
    public function cancelJob(Request $request, Response $response, array $args): Response
    {
        if ($err = $this->requireAuth($response))
            return $err;
        try {
            $csrfToken = $request->getHeaderLine('X-CSRF-Token') ?: ($request->getQueryParams()['csrf_token'] ?? null);
            if (!CSRF::validateToken($csrfToken)) {
                return $this->jsonResponse($response, ['success' => false, 'message' => 'Token CSRF inválido'], 403);
            }
            $server = $this->getServer((int) $args['serverId']);
            $name = urldecode($args['name']);
            $jobId = (int) $args['jobId'];
            (new PrintService($server))->cancelJob($name, $jobId);
            $this->audit->log($_SESSION['user']['username'] ?? 'unknown', 'print_cancel_job', null, $this->getClientIp(), 'success', ['server_id' => $args['serverId'], 'printer' => $name, 'job_id' => $jobId]);
            return $this->jsonResponse($response, ['success' => true, 'message' => "Job $jobId cancelado"]);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /** POST /print/api/servers/{serverId}/printers/{name}/jobs/{jobId}/pause */
    public function pauseJob(Request $request, Response $response, array $args): Response
    {
        if ($err = $this->requireAuth($response))
            return $err;
        try {
            $data = $request->getParsedBody();
            if (!CSRF::validateToken($data['csrf_token'] ?? null)) {
                return $this->jsonResponse($response, ['success' => false, 'message' => 'Token CSRF inválido'], 403);
            }
            $server = $this->getServer((int) $args['serverId']);
            $name = urldecode($args['name']);
            $jobId = (int) $args['jobId'];
            (new PrintService($server))->pauseJob($name, $jobId);
            return $this->jsonResponse($response, ['success' => true, 'message' => "Job $jobId pausado"]);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /** POST /print/api/servers/{serverId}/printers/{name}/jobs/{jobId}/resume */
    public function resumeJob(Request $request, Response $response, array $args): Response
    {
        if ($err = $this->requireAuth($response))
            return $err;
        try {
            $data = $request->getParsedBody();
            if (!CSRF::validateToken($data['csrf_token'] ?? null)) {
                return $this->jsonResponse($response, ['success' => false, 'message' => 'Token CSRF inválido'], 403);
            }
            $server = $this->getServer((int) $args['serverId']);
            $name = urldecode($args['name']);
            $jobId = (int) $args['jobId'];
            (new PrintService($server))->resumeJob($name, $jobId);
            return $this->jsonResponse($response, ['success' => true, 'message' => "Job $jobId retomado"]);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /** GET /print/api/servers/{serverId}/drivers */
    public function getDrivers(Request $request, Response $response, array $args): Response
    {
        if ($err = $this->requireAuth($response))
            return $err;
        try {
            $server = $this->getServer((int) $args['serverId']);
            $drivers = (new PrintService($server))->getDrivers();
            return $this->jsonResponse($response, ['success' => true, 'data' => $drivers]);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /** POST /print/api/servers/{serverId}/printers */
    public function createPrinter(Request $request, Response $response, array $args): Response
    {
        if ($err = $this->requireAuth($response))
            return $err;
        try {
            $data = $request->getParsedBody();
            if (!CSRF::validateToken($data['csrf_token'] ?? null)) {
                return $this->jsonResponse($response, ['success' => false, 'message' => 'Token CSRF inválido'], 403);
            }
            $server = $this->getServer((int) $args['serverId']);
            $payload = [
                'name' => trim($data['name'] ?? ''),
                'driverName' => trim($data['driverName'] ?? ''),
                'printerIP' => trim($data['printerIP'] ?? ''),
                'portName' => trim($data['portName'] ?? ''),
                'shareName' => trim($data['shareName'] ?? ''),
                'shared' => !empty($data['shared']) ? 'true' : 'false',
                'comment' => trim($data['comment'] ?? ''),
            ];
            if (!$payload['name'] || !$payload['driverName'] || !$payload['printerIP']) {
                return $this->jsonResponse($response, ['success' => false, 'message' => 'Nome, driver e IP são obrigatórios'], 400);
            }
            (new PrintService($server))->createPrinter($payload);
            $this->audit->log(
                $_SESSION['user']['username'] ?? 'unknown',
                'print_create_printer',
                null,
                $this->getClientIp(),
                'success',
                ['server_id' => $args['serverId'], 'printer' => $payload['name'], 'driver' => $payload['driverName']]
            );
            return $this->jsonResponse($response, ['success' => true, 'message' => "Impressora \"{$payload['name']}\" instalada com sucesso"]);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /** DELETE /print/api/servers/{serverId}/printers/{name} */
    public function deletePrinter(Request $request, Response $response, array $args): Response
    {
        if ($err = $this->requireAuth($response))
            return $err;
        try {
            $csrfToken = $request->getHeaderLine('X-CSRF-Token') ?: ($request->getQueryParams()['csrf_token'] ?? null);
            if (!CSRF::validateToken($csrfToken)) {
                return $this->jsonResponse($response, ['success' => false, 'message' => 'Token CSRF inválido'], 403);
            }
            $server = $this->getServer((int) $args['serverId']);
            $name = urldecode($args['name']);
            (new PrintService($server))->deletePrinter($name);
            $this->audit->log(
                $_SESSION['user']['username'] ?? 'unknown',
                'print_delete_printer',
                null,
                $this->getClientIp(),
                'success',
                ['server_id' => $args['serverId'], 'printer' => $name]
            );
            return $this->jsonResponse($response, ['success' => true, 'message' => "Impressora \"$name\" removida"]);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
