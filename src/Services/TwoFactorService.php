<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use App\Security\TOTP;
use PDO;

class TwoFactorService
{
    private PDO $db;
    private AuditService $audit;
    private const MAX_ATTEMPTS = 3;
    private const ATTEMPT_WINDOW = 300; // 5 minutes

    public function __construct()
    {
        // Set the correct timezone to avoid TOTP time skew issues.
        date_default_timezone_set('America/Sao_Paulo');

        $this->db = Database::getInstance();
        $this->audit = new AuditService();
    }

    /**
     * Enable 2FA for a user
     */
    public function enable2FA(int $userId): array
    {
        // Generate secret
        $secret = TOTP::generateSecret();
        
        // Generate backup codes
        $backupCodes = TOTP::generateBackupCodes(10);
        $hashedBackupCodes = TOTP::hashBackupCodes($backupCodes);

        return [
            'secret' => $secret,
            'backup_codes' => $backupCodes,
            'hashed_backup_codes' => $hashedBackupCodes
        ];
    }

    /**
     * Verify and activate 2FA
     */
    public function verify2FA(int $userId, string $code, string $secret): bool
    {
        error_log("[2FA Setup] Verifying code for user ID: $userId");
        error_log("[2FA Setup] Code: $code");
        error_log("[2FA Setup] Code length: " . strlen($code));
        error_log("[2FA Setup] Secret length: " . strlen($secret));
        
        $user = $this->getUser($userId);

        if (!$user) {
            error_log("[2FA Setup] User not found");
            return false;
        }

        // Verify code
        try {
            $isValid = TOTP::verifyCode($secret, $code);
            error_log("[2FA Setup] Code verification result: " . ($isValid ? 'VALID' : 'INVALID'));
            
            if (!$isValid) {
                $this->audit->log($user['username'] ?? 'unknown', '2fa_verify_failed', null, $this->getClientIp(), 'failure', ['reason' => 'Invalid code during setup']);
                $this->recordAttempt($userId, $this->getClientIp(), false);
                return false;
            }
        } catch (\Exception $e) {
            error_log("[2FA Setup] Exception during verification: " . $e->getMessage());
            $this->audit->log($user['username'] ?? 'unknown', '2fa_verify_failed', null, $this->getClientIp(), 'failure', ['reason' => 'Exception: ' . $e->getMessage()]);
            $this->recordAttempt($userId, $this->getClientIp(), false);
            return false;
        }

        // Code is valid, now activate 2FA and save the secret permanently
        error_log("[2FA Setup] Activating 2FA for user");
        $stmt = $this->db->prepare('
            UPDATE users 
            SET totp_secret = ?,
                totp_enabled = TRUE
            WHERE id = ?
        ');
        $stmt->execute([$secret, $userId]);
        
        $this->recordAttempt($userId, $this->getClientIp(), true);
        error_log("[2FA Setup] 2FA activated successfully");
        
        return true;
    }

    /**
     * Disable 2FA for a user
     */
    public function disable2FA(int $userId, string $code): bool
    {
        $user = $this->getUser($userId);
        
        if (!$user || !$user['totp_enabled']) {
            return false;
        }
        
        // Verify code before disabling
        if (!TOTP::verifyCode($user['totp_secret'], $code)) {
            $this->recordAttempt($userId, $this->getClientIp(), false);
            return false;
        }
        
        // Disable 2FA
        $stmt = $this->db->prepare('
            UPDATE users 
            SET totp_enabled = FALSE,
                totp_secret = NULL
            WHERE id = ?
        ');
        $stmt->execute([$userId]);
        
        return true;
    }

    /**
     * Validate 2FA code during login
     */
    public function validate2FACode(int $userId, string $code): bool
    {
        // Debug log
        error_log("[2FA] Validating code for user ID: $userId");
        error_log("[2FA] Code received: " . $code);
        error_log("[2FA] Code length: " . strlen($code));
        
        // Check rate limiting
        if ($this->isRateLimited($userId)) {
            error_log("[2FA] Rate limited for user ID: $userId");
            throw new \RuntimeException('Muitas tentativas falhadas. Tente novamente em 5 minutos.');
        }
        
        $user = $this->getUser($userId);
        
        if (!$user || !$user['totp_enabled']) {
            error_log("[2FA] User not found or 2FA not enabled");
            return false;
        }
        
        error_log("[2FA] User found, TOTP enabled: " . ($user['totp_enabled'] ? 'yes' : 'no'));
        error_log("[2FA] Secret exists: " . (!empty($user['totp_secret']) ? 'yes' : 'no'));
        
        // Verify TOTP code
        $totpValid = TOTP::verifyCode($user['totp_secret'], $code);
        error_log("[2FA] TOTP verification result: " . ($totpValid ? 'VALID' : 'INVALID'));
        
        if ($totpValid) {
            $this->recordAttempt($userId, $this->getClientIp(), true);
            return true;
        }
        
        // Failed attempt
        error_log("[2FA] Verification failed");
        $this->recordAttempt($userId, $this->getClientIp(), false);
        return false;
    }

    /**
     * Get QR code URL for setup
     */
    public function getQRCodeUrl(int $userId): ?string
    {
        $user = $this->getUser($userId);
        
        if (!$user || !$user['totp_secret']) {
            return null;
        }
        
        $label = $user['username'];
        return TOTP::getQRCodeUrl($user['totp_secret'], $label);
    }

    /**
     * Check if user has 2FA enabled
     */
    public function is2FAEnabled(int $userId): bool
    {
        $user = $this->getUser($userId);
        return $user && (bool)$user['totp_enabled'];
    }

    /**
     * Get remaining backup codes count
     */
    public function getBackupCodesCount(int $userId): int
    {
        $user = $this->getUser($userId);
        
        if (!$user || !$user['backup_codes']) {
            return 0;
        }
        
        $codes = json_decode($user['backup_codes'], true);
        return count($codes);
    }

    /**
     * Regenerate backup codes
     */
    public function regenerateBackupCodes(int $userId, string $code): ?array
    {
        $user = $this->getUser($userId);
        
        if (!$user || !$user['totp_enabled']) {
            return null;
        }
        
        // Verify current code
        if (!TOTP::verifyCode($user['totp_secret'], $code)) {
            return null;
        }
        
        // Generate new backup codes
        $backupCodes = TOTP::generateBackupCodes(10);
        $hashedBackupCodes = TOTP::hashBackupCodes($backupCodes);
        
        $stmt = $this->db->prepare('
            UPDATE users 
            SET backup_codes = ?
            WHERE id = ?
        ');
        $stmt->execute([
            json_encode($hashedBackupCodes),
            $userId
        ]);
        
        return $backupCodes;
    }

    /**
     * Check if user is rate limited
     */
    private function isRateLimited(int $userId): bool
    {
        $stmt = $this->db->prepare('
            SELECT COUNT(*) as attempts 
            FROM totp_attempts
            WHERE user_id = ?
            AND success = FALSE
            AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ');
        $stmt->execute([$userId, self::ATTEMPT_WINDOW]);
        $result = $stmt->fetch();
        
        return ($result['attempts'] ?? 0) >= self::MAX_ATTEMPTS;
    }

    /**
     * Record 2FA attempt
     */
    private function recordAttempt(int $userId, string $ip, bool $success): void
    {
        $stmt = $this->db->prepare('
            INSERT INTO totp_attempts (user_id, ip_address, success)
            VALUES (?, ?, ?)
        ');
        $stmt->execute([$userId, $ip, (int)$success]);
        
        // Clean old attempts
        $this->db->exec('
            DELETE FROM totp_attempts 
            WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)
        ');
    }

    /**
     * Get user data
     */
    private function getUser(int $userId): ?array
    {
        $stmt = $this->db->prepare('
            SELECT id, username, totp_secret, totp_enabled
            FROM users
            WHERE id = ?
        ');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        return $user ?: null;
    }

    /**
     * Get client IP address
     */
    private function getClientIp(): string
    {
        return $_SERVER['HTTP_X_FORWARDED_FOR'] 
            ?? $_SERVER['HTTP_X_REAL_IP'] 
            ?? $_SERVER['REMOTE_ADDR'] 
            ?? '0.0.0.0';
    }

    /**
     * Generate a token and set a cookie to trust the current device.
     */
    public function trustDevice(int $userId): void
    {
        $token = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $token);
        $expiresAt = time() + (30 * 86400); // 30 days

        // Store the hashed token in the database
        $stmt = $this->db->prepare('
            INSERT INTO trusted_devices (user_id, token, expires_at)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE token = ?, expires_at = ?
        ');
        $stmt->execute([$userId, $hashedToken, date('Y-m-d H:i:s', $expiresAt), $hashedToken, date('Y-m-d H:i:s', $expiresAt)]);

        // Set the cookie on the user's browser
        setcookie(
            '2fa_trust_token',
            $userId . ':' . $token,
            [
                'expires' => $expiresAt,
                'path' => '/',
                'domain' => $_SERVER['HTTP_HOST'],
                'secure' => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Lax'
            ]
        );
    }

    /**
     * Check if the current device is trusted for the given user.
     */
    public function isTrustedDevice(int $userId): bool
    {
        if (!isset($_COOKIE['2fa_trust_token'])) {
            return false;
        }

        $cookieValue = $_COOKIE['2fa_trust_token'];
        list($cookieUserId, $token) = explode(':', $cookieValue, 2);

        if ((int)$cookieUserId !== $userId) {
            return false;
        }

        $hashedToken = hash('sha256', $token);

        // Find the token in the database
        $stmt = $this->db->prepare('
            SELECT 1 FROM trusted_devices 
            WHERE user_id = ? AND token = ? AND expires_at > NOW()
        ');
        $stmt->execute([$userId, $hashedToken]);

        if ($stmt->fetch()) {
            // Device is trusted, extend the cookie lifetime
            $this->trustDevice($userId);
            return true;
        }

        return false;
    }
}
