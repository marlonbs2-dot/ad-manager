<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuditService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class DashboardController extends BaseController
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
        include __DIR__ . '/../../views/dashboard.php';
        $html = ob_get_clean();

        $response->getBody()->write($html);
        return $response;
    }

    public function getStats(Request $request, Response $response): Response
    {
        $this->requireAuth();

        try {
            $currentUser = $_SESSION['user'] ?? [];
            $username = $currentUser['username'] ?? '';
            
            // Estatísticas gerais (mantém como está)
            $stats = $this->auditService->getStatistics();

            // Logs recentes APENAS do usuário logado
            // Filtra por username do usuário atual
            $filters = ['username' => $username];
            $recentLogs = $this->auditService->getLogs($filters, 10, 0);

            return $this->jsonResponse($response, [
                'success' => true,
                'data' => [
                    'statistics' => $stats,
                    'recent_logs' => $recentLogs
                ]
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
