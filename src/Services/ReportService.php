<?php

declare(strict_types=1);

namespace App\Services;

use Mpdf\Mpdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportService
{
    private AuditService $audit;

    public function __construct()
    {
        $this->audit = new AuditService();
    }

    public function generatePDF(array $filters = []): string
    {
        $logs = $this->audit->getLogs($filters, 1000);
        $stats = $this->audit->getStatistics();

        $html = $this->buildReportHTML($logs, $stats, $filters);

        $tmpDir = $_ENV['MPDF_TEMP_PATH'] ?? __DIR__ . '/../../storage/mpdf_tmp';
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0777, true);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-L',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 20,
            'margin_bottom' => 20,
            'tempDir' => $tmpDir,
        ]);

        $mpdf->SetTitle('AD Manager - Audit Report');
        $mpdf->SetAuthor('AD Manager');
        $mpdf->WriteHTML($html);

        $filename = 'audit_report_' . date('Y-m-d_His') . '.pdf';
        $filepath = $this->getReportsPath() . '/' . $filename;

        $mpdf->Output($filepath, 'F');

        return $filename;
    }

    public function generateExcel(array $filters = []): string
    {
        $logs = $this->audit->getLogs($filters, 5000);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Add header with logo and title
        $sheet->mergeCells('A1:I1');
        $sheet->setCellValue('A1', 'SEJUS - Secretaria de Estado da Justiça');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells('A2:I2');
        $sheet->setCellValue('A2', 'AD Manager - Relatório de Auditoria');
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells('A3:I3');
        $sheet->setCellValue('A3', 'Gerado em: ' . date('d/m/Y H:i:s'));
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        // Add empty row
        $sheet->getRowDimension(4)->setRowHeight(10);

        // Set headers
        $headers = ['ID', 'Date/Time', 'User', 'Action', 'Target DN', 'OU', 'IP Address', 'Result', 'Details'];
        $sheet->fromArray($headers, null, 'A5');

        // Style headers
        $headerStyle = $sheet->getStyle('A5:I5');
        $headerStyle->getFont()->setBold(true);
        $headerStyle->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $headerStyle->getFill()->getStartColor()->setRGB('4472C4');
        $headerStyle->getFont()->getColor()->setRGB('FFFFFF');

        // Add data
        $row = 6;
        foreach ($logs as $log) {
            $details = is_array($log['details']) ? json_encode($log['details']) : $log['details'];

            $sheet->fromArray([
                $log['id'],
                $log['created_at'],
                $log['username'],
                $log['action'],
                $log['target_dn'] ?? '',
                $log['target_ou'] ?? '',
                $log['ip_address'],
                $log['result'],
                $details
            ], null, 'A' . $row);

            $row++;
        }

        // Auto-size columns
        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Add filters
        $sheet->setAutoFilter('A5:I5');

        $filename = 'audit_report_' . date('Y-m-d_His') . '.xlsx';
        $filepath = $this->getReportsPath() . '/' . $filename;

        $writer = new Xlsx($spreadsheet);
        $writer->save($filepath);

        return $filename;
    }

    private function buildReportHTML(array $logs, array $stats, array $filters): string
    {
        $filterInfo = $this->buildFilterInfo($filters);

        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <style>
                body { font-family: Arial, sans-serif; font-size: 10pt; }
                h1 { color: #333; font-size: 18pt; margin-bottom: 5px; }
                h2 { color: #666; font-size: 14pt; margin-top: 20px; margin-bottom: 10px; }
                .header { margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 10px; }
                .header-table { width: 100%; border: none; }
                .header-table td { border: none; vertical-align: middle; padding: 0; }
                .header-logo { width: 120px; }
                .header-logo img { width: 100px; height: auto; }
                .header-text { padding-left: 20px; }
                .header-title { font-size: 16pt; font-weight: bold; color: #333; margin: 0 0 5px 0; }
                .header-subtitle { font-size: 14pt; color: #666; margin: 5px 0; }
                .header-date { font-size: 9pt; color: #999; margin: 5px 0 0 0; }
                .info { margin-bottom: 20px; }
                .info-label { font-weight: bold; }
                .stats { display: table; width: 100%; margin-bottom: 20px; }
                .stat-box { display: table-cell; padding: 10px; background: #f5f5f5; border: 1px solid #ddd; text-align: center; }
                .stat-value { font-size: 24pt; font-weight: bold; color: #4472C4; }
                .stat-label { font-size: 9pt; color: #666; }
                table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                th { background: #4472C4; color: white; padding: 8px; text-align: left; font-size: 9pt; }
                td { padding: 6px; border-bottom: 1px solid #ddd; font-size: 9pt; }
                tr:nth-child(even) { background: #f9f9f9; }
                .success { color: green; font-weight: bold; }
                .failure { color: red; font-weight: bold; }
                .error { color: orange; font-weight: bold; }
            </style>
        </head>
        <body>
            <div class="header">
                <table class="header-table">
                    <tr>
                        <td class="header-logo">
                            <img src="https://cdn.es.gov.br/images/logo/governo/brasao/center/Brasao_Governo_100.png" alt="Brasão do Governo">
                        </td>
                        <td class="header-text">
                            <p class="header-title">SEJUS - Secretaria de Estado da Justiça</p>
                            <p class="header-subtitle">AD Manager - Relatório de Auditoria</p>
                            <p class="header-date">Gerado em: ' . date('d/m/Y H:i:s') . '</p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="info">
                <p><span class="info-label">Report Type:</span> Audit Logs</p>
                ' . $filterInfo . '
            </div>

            <h2>Statistics</h2>
            <div class="stats">
                <div class="stat-box">
                    <div class="stat-value">' . $stats['actions_today'] . '</div>
                    <div class="stat-label">Actions Today</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value">' . $stats['success_rate'] . '%</div>
                    <div class="stat-label">Success Rate (30d)</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value">' . count($logs) . '</div>
                    <div class="stat-label">Total Records</div>
                </div>
            </div>

            <h2>Audit Logs</h2>
            <table>
                <thead>
                    <tr>
                        <th>Date/Time</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Target</th>
                        <th>IP</th>
                        <th>Result</th>
                    </tr>
                </thead>
                <tbody>';

        foreach ($logs as $log) {
            $resultClass = strtolower($log['result']);
            $html .= '
                    <tr>
                        <td>' . htmlspecialchars($log['created_at']) . '</td>
                        <td>' . htmlspecialchars($log['username']) . '</td>
                        <td>' . htmlspecialchars($log['action']) . '</td>
                        <td>' . htmlspecialchars($log['target_dn'] ?? 'N/A') . '</td>
                        <td>' . htmlspecialchars($log['ip_address']) . '</td>
                        <td class="' . $resultClass . '">' . htmlspecialchars($log['result']) . '</td>
                    </tr>';
        }

        $html .= '
                </tbody>
            </table>
        </body>
        </html>';

        return $html;
    }

    private function buildFilterInfo(array $filters): string
    {
        if (empty($filters)) {
            return '<p><span class="info-label">Filters:</span> None</p>';
        }

        $info = '<p><span class="info-label">Filters Applied:</span></p><ul>';

        foreach ($filters as $key => $value) {
            if (!empty($value)) {
                $label = ucwords(str_replace('_', ' ', $key));
                $info .= '<li>' . htmlspecialchars($label) . ': ' . htmlspecialchars($value) . '</li>';
            }
        }

        $info .= '</ul>';

        return $info;
    }

    private function getReportsPath(): string
    {
        $path = $_ENV['REPORTS_PATH'] ?? __DIR__ . '/../../storage/reports';

        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        return $path;
    }

    public function getReportFile(string $filename): ?string
    {
        $filepath = $this->getReportsPath() . '/' . basename($filename);

        if (!file_exists($filepath)) {
            return null;
        }

        return $filepath;
    }

    public function cleanOldReports(int $daysOld = 7): int
    {
        $path = $this->getReportsPath();
        $count = 0;
        $cutoff = time() - ($daysOld * 86400);

        $files = glob($path . '/*');

        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoff) {
                unlink($file);
                $count++;
            }
        }

        return $count;
    }
}
