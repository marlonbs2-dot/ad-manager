<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use App\Security\Encryption;
use PDO;

class ADConfigService
{
    private PDO $db;
    private Encryption $encryption;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->encryption = new Encryption();
    }

    public function getActiveConfig(): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM ad_config WHERE is_active = 1 LIMIT 1');
        $stmt->execute();
        $config = $stmt->fetch();

        if (!$config) {
            return null;
        }

        // Parse JSON fields
        $config['ou_reset_password'] = json_decode($config['ou_reset_password'] ?? '[]', true);
        $config['ou_manage_groups'] = json_decode($config['ou_manage_groups'] ?? '[]', true);

        return $config;
    }

    public function saveConfig(array $data): int
    {
        // Deactivate all existing configs
        $this->db->exec('UPDATE ad_config SET is_active = 0');

        // Encrypt password
        $encryptedPassword = $this->encryption->encrypt($data['bind_password']);

        $stmt = $this->db->prepare('
            INSERT INTO ad_config (
                host, port, protocol, use_tls, base_dn, bind_dn, bind_password_enc,
                admin_ou, ou_reset_password, ou_manage_groups, connection_timeout, is_active
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
        ');

        $stmt->execute([
            $data['host'],
            $data['port'] ?? 389,
            $data['protocol'] ?? 'ldap',
            (int) ($data['use_tls'] ?? 0),
            $data['base_dn'],
            $data['bind_dn'],
            $encryptedPassword,
            $data['admin_ou'],
            json_encode($data['ou_reset_password'] ?? []),
            json_encode($data['ou_manage_groups'] ?? []),
            $data['connection_timeout'] ?? 10
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function updateConfig(int $id, array $data): bool
    {
        $fields = [];
        $params = [];

        foreach ($data as $key => $value) {
            if ($key === 'bind_password' && !empty($value)) {
                $fields[] = 'bind_password_enc = ?';
                $params[] = $this->encryption->encrypt($value);
            } elseif (in_array($key, ['ou_reset_password', 'ou_manage_groups'])) {
                $fields[] = "$key = ?";
                $params[] = json_encode($value);
            } elseif ($key !== 'bind_password') {
                $fields[] = "$key = ?";
                $params[] = $value;
            }
        }

        if (empty($fields)) {
            return false;
        }

        $params[] = $id;
        $sql = 'UPDATE ad_config SET ' . implode(', ', $fields) . ' WHERE id = ?';
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
}
