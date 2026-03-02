<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use PDO;

class AuditService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function log(
        string $username,
        string $action,
        ?string $targetDn,
        string $ip,
        string $result,
        array $details = []
    ): int {
        $targetOu = $this->extractOU($targetDn);
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

        // Get user ID if exists
        $stmt = $this->db->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        $userId = $user['id'] ?? null;

        $stmt = $this->db->prepare('
            INSERT INTO audit_logs (
                user_id, username, action, target_dn, target_ou, 
                ip_address, user_agent, result, details
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');

        $stmt->execute([
            $userId,
            $username,
            $action,
            $targetDn,
            $targetOu,
            $ip,
            $userAgent,
            $result,
            json_encode($details)
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function getLogs(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['username'])) {
            $where[] = 'username LIKE ?';
            $params[] = '%' . $filters['username'] . '%';
        }

        if (!empty($filters['action'])) {
            $where[] = 'action = ?';
            $params[] = $filters['action'];
        }

        if (!empty($filters['result'])) {
            $where[] = 'result = ?';
            $params[] = $filters['result'];
        }

        if (!empty($filters['target_ou'])) {
            $where[] = 'target_ou LIKE ?';
            $params[] = '%' . $filters['target_ou'] . '%';
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'created_at >= ?';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'created_at <= ?';
            $params[] = $filters['date_to'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $sql = "SELECT * FROM audit_logs $whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $logs = $stmt->fetchAll();

        // Decode JSON details
        foreach ($logs as &$log) {
            $log['details'] = json_decode($log['details'], true);
        }

        return $logs;
    }

    public function getLogCount(array $filters = []): int
    {
        $where = [];
        $params = [];

        if (!empty($filters['username'])) {
            $where[] = 'username LIKE ?';
            $params[] = '%' . $filters['username'] . '%';
        }

        if (!empty($filters['action'])) {
            $where[] = 'action = ?';
            $params[] = $filters['action'];
        }

        if (!empty($filters['result'])) {
            $where[] = 'result = ?';
            $params[] = $filters['result'];
        }

        if (!empty($filters['target_ou'])) {
            $where[] = 'target_ou LIKE ?';
            $params[] = '%' . $filters['target_ou'] . '%';
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'created_at >= ?';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'created_at <= ?';
            $params[] = $filters['date_to'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $sql = "SELECT COUNT(*) as total FROM audit_logs $whereClause";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $result = $stmt->fetch();
        return (int) ($result['total'] ?? 0);
    }

    public function getLogById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM audit_logs WHERE id = ?');
        $stmt->execute([$id]);
        
        $log = $stmt->fetch();
        
        if (!$log) {
            return null;
        }

        // Decode JSON details
        $log['details'] = json_decode($log['details'], true);

        return $log;
    }

    public function getStatistics(): array
    {
        $stats = [];

        // Total actions today
        $stmt = $this->db->query('
            SELECT COUNT(*) as total FROM audit_logs 
            WHERE DATE(created_at) = CURDATE()
        ');
        $stats['actions_today'] = (int) ($stmt->fetch()['total'] ?? 0);

        // Actions by type for today
        $stmt = $this->db->query('
            SELECT action, COUNT(*) as count FROM audit_logs 
            WHERE DATE(created_at) = CURDATE()
            GROUP BY action
            ORDER BY count DESC
        ');
        $stats['actions_today_by_type'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Actions by type over the last 7 days
        $stmt = $this->db->query('
            SELECT action, COUNT(*) as count FROM audit_logs 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY action
            ORDER BY count DESC
        ');
        $stats['actions_by_type'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Success rate over the last 30 days
        $stmt = $this->db->query('
            SELECT 
                SUM(CASE WHEN result = "success" THEN 1 ELSE 0 END) as success_count,
                COUNT(*) as total
            FROM audit_logs 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ');
        $result = $stmt->fetch();
        $stats['success_rate'] = $result['total'] > 0 
            ? round(((int)$result['success_count'] / (int)$result['total']) * 100, 1)
            : 0;

        // Most active users over the last 7 days
        $stmt = $this->db->query('
            SELECT username, COUNT(*) as actions FROM audit_logs 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY username
            ORDER BY actions DESC
            LIMIT 5
        ');
        $stats['most_active_users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $stats;
    }

    private function extractOU(? string $dn): ?string
    {
        if (!$dn) {
            return null;
        }

        // Extract OU from DN
        if (preg_match('/OU=([^,]+)/', $dn, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
