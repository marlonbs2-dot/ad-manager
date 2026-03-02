<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use PDO;

class ShareServiceHybrid
{
    private ShareService $httpService;
    private PDO $db;

    public function __construct()
    {
        $this->httpService = new ShareService();
        $this->db = Database::getInstance();
    }

    private function logDebug(string $message): void
    {
        file_put_contents(
            '/var/www/html/logs/share-hybrid-debug.log',
            date('[Y-m-d H:i:s] ') . $message . PHP_EOL,
            FILE_APPEND
        );
    }

    public function syncLogs(int $hours = 2, string $shareFilter = ''): array
    {
        $this->logDebug("Using HTTP API for share log sync");
        return $this->httpService->syncLogs($hours, $shareFilter);
    }

    public function testConnection(): array
    {
        $this->logDebug("Using HTTP API for share connection test");
        return $this->httpService->testConnection();
    }

    // Database operations (work locally in PHP container)
    public function getLogs(int $page = 1, int $limit = 50, array $filters = []): array
    {
        $offset = ($page - 1) * $limit;
        $where = ['1=1'];
        $params = [];
        
        // Apply filters
        if (!empty($filters['server'])) {
            $where[] = 'server_name = ?';
            $params[] = $filters['server'];
        }
        
        if (!empty($filters['username'])) {
            $where[] = 'username LIKE ?';
            $params[] = '%' . $filters['username'] . '%';
        }
        
        if (!empty($filters['share_name'])) {
            $where[] = 'share_name LIKE ?';
            $params[] = '%' . $filters['share_name'] . '%';
        }
        
        if (!empty($filters['action'])) {
            $where[] = 'action = ?';
            $params[] = $filters['action'];
        }
        
        if (!empty($filters['date_from'])) {
            $where[] = 'time_created >= ?';
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = 'time_created <= ?';
            $params[] = $filters['date_to'];
        }
        
        $whereClause = implode(' AND ', $where);
        
        // Get total count
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM share_logs WHERE {$whereClause}");
        $stmt->execute($params);
        $total = $stmt->fetchColumn();
        
        // Get logs
        $stmt = $this->db->prepare("
            SELECT * FROM share_logs 
            WHERE {$whereClause} 
            ORDER BY time_created DESC 
            LIMIT {$limit} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'logs' => $logs,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => (int)$total,
                'pages' => ceil($total / $limit)
            ]
        ];
    }

    public function getStatistics(int $days = 7): array
    {
        $stats = [];
        
        // Filtro de data
        $dateFilter = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Total logs
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM share_logs WHERE time_created >= ?');
        $stmt->execute([$dateFilter]);
        $stats['total_logs'] = $stmt->fetchColumn();
        
        // Logs by action
        $stmt = $this->db->prepare('
            SELECT action, COUNT(*) as count 
            FROM share_logs 
            WHERE time_created >= ?
            GROUP BY action 
            ORDER BY count DESC
        ');
        $stmt->execute([$dateFilter]);
        $stats['by_action'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Top users
        $stmt = $this->db->prepare('
            SELECT username, COUNT(*) as count 
            FROM share_logs 
            WHERE username IS NOT NULL AND time_created >= ?
            GROUP BY username 
            ORDER BY count DESC 
            LIMIT 10
        ');
        $stmt->execute([$dateFilter]);
        $stats['top_users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Top shares
        $stmt = $this->db->prepare('
            SELECT share_name, COUNT(*) as count 
            FROM share_logs 
            WHERE share_name IS NOT NULL AND time_created >= ?
            GROUP BY share_name 
            ORDER BY count DESC 
            LIMIT 10
        ');
        $stmt->execute([$dateFilter]);
        $stats['top_shares'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $stats;
    }

    // Server management methods (delegate to HTTP service)
    public function getServers(): array
    {
        return $this->httpService->getAllServers();
    }

    public function addServer(array $serverData): bool
    {
        return $this->httpService->addServer($serverData);
    }

    public function updateServer(int $serverId, array $serverData): bool
    {
        return $this->httpService->updateServer($serverId, $serverData);
    }

    public function deleteServer(int $serverId): bool
    {
        return $this->httpService->deleteServer($serverId);
    }

    public function testServerConnection(int $serverId): array
    {
        return $this->httpService->testServerConnection($serverId);
    }

    public function syncLogsFromServer(string $serverName, int $hours = 2, string $shareFilter = ''): array
    {
        return $this->httpService->syncLogsFromServer($serverName, $hours, $shareFilter);
    }

    public function exportLogs(array $filters, string $format = 'csv'): array
    {
        return $this->httpService->exportLogs($filters, $format);
    }
}