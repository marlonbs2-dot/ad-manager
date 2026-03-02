<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\ReportService;
use App\Security\CSRF;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ReportController extends BaseController
{
    private ReportService $reportService;

    public function __construct()
    {
        parent::__construct();
        $this->reportService = new ReportService();
    }

    public function index(Request $request, Response $response): Response
    {
        $this->requireAuth();
        
        ob_start();
        include __DIR__ . '/../../views/reports.php';
        $html = ob_get_clean();

        $response->getBody()->write($html);
        return $response;
    }

    public function export(Request $request, Response $response): Response
    {
        $this->requireAuth();

        $params = $request->getQueryParams();
        
        $type = $params['type'] ?? 'pdf';
        $filters = [
            'username' => $params['username'] ?? '',
            'action' => $params['action'] ?? '',
            'result' => $params['result'] ?? '',
            'target_ou' => $params['target_ou'] ?? '',
            'date_from' => $params['date_from'] ?? '',
            'date_to' => $params['date_to'] ?? ''
        ];

        try {
            if ($type === 'excel') {
                $filename = $this->reportService->generateExcel($filters);
                $contentType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            } else {
                $filename = $this->reportService->generatePDF($filters);
                $contentType = 'application/pdf';
            }

            $filepath = $this->reportService->getReportFile($filename);

            if (!$filepath) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => 'Report file not found'
                ], 404);
            }

            $response->getBody()->write(file_get_contents($filepath));
            
            return $response
                ->withHeader('Content-Type', $contentType)
                ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->withHeader('Content-Length', (string) filesize($filepath));

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
