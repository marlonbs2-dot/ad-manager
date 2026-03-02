<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuditService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AuditController extends BaseController
{
    private AuditService $auditService;

    public function __construct()
    {
        parent::__construct();
        $this->auditService = new AuditService();
    }

    public function index(Request $request, Response $response): Response
    {
        $this->requireAuth();
        
        ob_start();
        include __DIR__ . '/../../views/audit.php';
        $html = ob_get_clean();

        $response->getBody()->write($html);
        return $response;
    }

    public function getLogs(Request $request, Response $response): Response
    {
        $this->requireAuth();

        $params = $request->getQueryParams();
        
        $filters = [
            'username' => $params['username'] ?? '',
            'action' => $params['action'] ?? '',
            'result' => $params['result'] ?? '',
            'target_ou' => $params['target_ou'] ?? '',
            'date_from' => $params['date_from'] ?? '',
            'date_to' => $params['date_to'] ?? ''
        ];

        $limit = (int) ($params['limit'] ?? 50);
        $offset = (int) ($params['offset'] ?? 0);

        try {
            $logs = $this->auditService->getLogs($filters, $limit, $offset);
            $total = $this->auditService->getLogCount($filters);

            return $this->jsonResponse($response, [
                'success' => true,
                'data' => $logs,
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getLogDetails(Request $request, Response $response, array $args): Response
    {
        $this->requireAuth();

        $logId = (int) ($args['id'] ?? 0);

        if ($logId <= 0) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'ID inválido'
            ], 400);
        }

        try {
            $log = $this->auditService->getLogById($logId);

            if (!$log) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => 'Log não encontrado'
                ], 404);
            }

            return $this->jsonResponse($response, [
                'success' => true,
                'data' => $log
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getStatistics(Request $request, Response $response): Response
    {
        $this->requireAuth();

        try {
            $stats = $this->auditService->getStatistics();

            return $this->jsonResponse($response, [
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
