<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use App\LDAP\LDAPConnection;
use App\Security\Encryption;
use PDO;

class AuthService
{
    private PDO $db;
    private ADConfigService $adConfig;
    private AuditService $audit;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->adConfig = new ADConfigService();
        $this->audit = new AuditService();
    }

    private function logDebug(string $message): void
    {
        file_put_contents(
            '/var/www/html/logs/auth-debug.log',
            date('[Y-m-d H:i:s] ') . $message . PHP_EOL,
            FILE_APPEND
        );
    }

    public function authenticate(string $username, string $password, string $ip): ?array
    {
        // Check rate limiting
        if ($this->isRateLimited($username, $ip)) {
            $this->audit->log($username, 'login_failed', null, $ip, 'failure', [
                'reason' => 'Rate limited'
            ]);
            throw new \RuntimeException('Too many login attempts. Please try again later.');
        }

        // Try emergency account first
        $emergencyUser = $this->authenticateEmergency($username, $password);
        if ($emergencyUser) {
            $this->recordLoginAttempt($username, $ip, true);
            $this->updateLastLogin($emergencyUser['id']);
            $this->audit->log($username, 'login_success', null, $ip, 'success', [
                'method' => 'emergency_account'
            ]);
            return $emergencyUser;
        }

        // Try AD authentication
        $this->logDebug('=== AD Authentication Start ===');
        $this->logDebug('Username: ' . $username);

        $config = $this->adConfig->getActiveConfig();
        if (!$config) {
            $this->logDebug('ERROR: No AD config found');
            throw new \RuntimeException('AD configuration not found');
        }

        $this->logDebug('Base DN: ' . $config['base_dn']);

        try {
            $ldap = new LDAPConnection($config);
            $ldap->connect();
            $this->logDebug('LDAP connected successfully');

            // Bind with service account FIRST
            $this->logDebug('Binding with service account...');
            $ldap->bind();
            $this->logDebug('Service account bind successful');

            // Build user DN
            $userDn = $this->buildUserDn($username, $config['base_dn'], $ldap);
            $this->logDebug('User DN found: ' . ($userDn ?? 'NULL'));

            if (!$userDn) {
                $this->logDebug('ERROR: User not found in AD');
                $this->recordLoginAttempt($username, $ip, false);
                $this->audit->log($username, 'login_failed', null, $ip, 'failure', [
                    'reason' => 'User not found'
                ]);
                return null;
            }

            // Try to bind with user credentials
            $this->logDebug('Attempting to bind with user credentials...');
            $ldap->bind($userDn, $password);
            $this->logDebug('Bind successful!');

            // Get user details
            $this->logDebug('Fetching user details...');
            $userInfo = $ldap->searchOne($userDn, '(objectClass=user)', [
                'displayName',
                'mail',
                'sAMAccountName',
                'memberOf'
            ]);
            $this->logDebug('User info retrieved. Keys: ' . implode(', ', array_keys($userInfo)));

            // Determine user role based on OU and groups
            $role = $this->determineUserRole($userDn, $userInfo, $config);
            $this->logDebug('Final role determined: ' . $role);

            // Block if user has no permissions
            if ($role === 'none') {
                $this->logDebug('ERROR: User has no permissions - blocking login');
                $this->recordLoginAttempt($username, $ip, false);
                $this->audit->log($username, 'login_failed', $userDn, $ip, 'failure', [
                    'reason' => 'Insufficient permissions'
                ]);
                return null;
            }

            $this->logDebug('User has role: ' . $role . ' - allowing login');

            // Create or update local user
            $user = $this->syncUser([
                'username' => $username,
                'display_name' => $userInfo['displayname'] ?? $username,
                'email' => $userInfo['mail'] ?? null,
                'role' => $role
            ]);

            // Add DN to user array for session
            $user['dn'] = $userDn;

            $this->recordLoginAttempt($username, $ip, true);
            $this->updateLastLogin($user['id']);
            $this->audit->log($username, 'login_success', $userDn, $ip, 'success', [
                'method' => 'ad_authentication'
            ]);

            return $user;

        } catch (\Exception $e) {
            $this->logDebug('EXCEPTION during authentication: ' . $e->getMessage());
            $this->logDebug('Exception trace: ' . $e->getTraceAsString());
            $this->recordLoginAttempt($username, $ip, false);
            $this->audit->log($username, 'login_failed', null, $ip, 'failure', [
                'reason' => $e->getMessage()
            ]);
            return null;
        }
    }

    private function authenticateEmergency(string $username, string $password): ?array
    {
        // 1. First check if the user exists and is an emergency account
        $stmt = $this->db->prepare('SELECT * FROM users WHERE username = ? AND is_emergency_account = 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user) {
            return null;
        }

        // 2. If it is an emergency account, we bypass the 'allow_local_login' check
        // Verify password
        if (password_verify($password, $user['password_hash'])) {
            // Force role to admin just in case
            $user['role'] = 'admin';
            return $user;
        }

        return null;
    }

    private function buildUserDn(string $username, string $baseDn, LDAPConnection $ldap): ?string
    {
        $filter = sprintf('(sAMAccountName=%s)', ldap_escape($username, '', LDAP_ESCAPE_FILTER));
        $result = $ldap->search($baseDn, $filter, ['dn']);

        return $result[0]['dn'] ?? null;
    }

    private function isInAdminOU(string $userDn, string $adminOu): bool
    {
        return stripos($userDn, $adminOu) !== false;
    }

    private function determineUserRole(string $userDn, array $userInfo, array $config): string
    {
        // Debug log
        $this->logDebug('=== Determining User Role ===');
        $this->logDebug('User DN: ' . $userDn);
        $this->logDebug('Admin OU: ' . $config['admin_ou']);

        // 1. Check if user is in Admin OU - Full access
        if ($this->isInAdminOU($userDn, $config['admin_ou'])) {
            $this->logDebug('Result: ADMIN (in admin OU)');
            return 'admin';
        }

        // 2. Check Permissions by OU (Reset Password or Manage Groups)
        // These are stored as JSON arrays in the config
        $resetPasswordOUs = $config['ou_reset_password'] ?? [];
        $manageGroupsOUs = $config['ou_manage_groups'] ?? [];

        // Debug OUs
        $this->logDebug('Reset Password OUs: ' . json_encode($resetPasswordOUs));
        $this->logDebug('Manage Groups OUs: ' . json_encode($manageGroupsOUs));

        $allowedOUs = array_merge(
            is_array($resetPasswordOUs) ? $resetPasswordOUs : [],
            is_array($manageGroupsOUs) ? $manageGroupsOUs : []
        );

        foreach ($allowedOUs as $ou) {
            if (empty($ou))
                continue;

            // Check if User DN contains the Allowed OU DN
            if (stripos($userDn, trim($ou)) !== false) {
                $this->logDebug('MATCH! User DN contains allowed OU: ' . $ou);
                $this->logDebug('Result: OPERATOR');
                return 'operator';
            }
        }

        // 3. No permissions
        $this->logDebug('Result: NONE (no permissions)');
        return 'none';
    }

    private function syncUser(array $userData): array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$userData['username']]);
        $existing = $stmt->fetch();

        if ($existing) {
            $stmt = $this->db->prepare('
                UPDATE users SET display_name = ?, email = ?, role = ? WHERE id = ?
            ');
            $stmt->execute([
                $userData['display_name'],
                $userData['email'],
                $userData['role'],
                $existing['id']
            ]);
            return array_merge($existing, $userData);
        }

        $stmt = $this->db->prepare('
            INSERT INTO users (username, display_name, email, role) VALUES (?, ?, ?, ?)
        ');
        $stmt->execute([
            $userData['username'],
            $userData['display_name'],
            $userData['email'],
            $userData['role']
        ]);

        $userData['id'] = (int) $this->db->lastInsertId();
        return $userData;
    }

    private function isRateLimited(string $username, string $ip): bool
    {
        $maxAttempts = (int) ($_ENV['RATE_LIMIT_LOGIN'] ?? 5);
        $window = (int) ($_ENV['RATE_LIMIT_WINDOW'] ?? 300);

        $stmt = $this->db->prepare('
            SELECT COUNT(*) as attempts FROM login_attempts
            WHERE (username = ? OR ip_address = ?)
            AND success = 0
            AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ');
        $stmt->execute([$username, $ip, $window]);
        $result = $stmt->fetch();

        return ($result['attempts'] ?? 0) >= $maxAttempts;
    }

    private function recordLoginAttempt(string $username, string $ip, bool $success): void
    {
        $stmt = $this->db->prepare('
            INSERT INTO login_attempts (username, ip_address, success) VALUES (?, ?, ?)
        ');
        $stmt->execute([$username, $ip, (int) $success]);

        // Clean old attempts
        $this->db->exec('DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)');
    }

    private function updateLastLogin(int $userId): void
    {
        $stmt = $this->db->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?');
        $stmt->execute([$userId]);
    }

    public function createEmergencyAccount(string $username, string $password): void
    {
        $stmt = $this->db->prepare('
            INSERT INTO users (username, display_name, role, is_emergency_account, password_hash)
            VALUES (?, ?, ?, 1, ?)
            ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)
        ');
        $stmt->execute([
            $username,
            'Emergency Admin',
            'admin',
            Encryption::hash($password)
        ]);
    }
}
