<?php

declare(strict_types=1);

namespace App\Security;

use OTPHP\TOTP as OTPHP_TOTP;
use ParagonIE\ConstantTime\Base32;

/**
 * TOTP (Time-Based One-Time Password) Implementation
 * Compatible with Google Authenticator, Microsoft Authenticator, Authy, etc.
 * RFC 6238: https://tools.ietf.org/html/rfc6238
 */
class TOTP
{
    /**
     * Generate a random secret key (base32 encoded)
     */
    public static function generateSecret(int $length = 16): string
    {
        // Generate a 16-character secret (16 * 5 = 80 bits), which is a common default.
        // We generate it manually to control the length and use the correct Base32 encoder from a library we already have.
        $bytes = random_bytes((int)ceil($length * 5 / 8));
        return rtrim(\ParagonIE\ConstantTime\Base32::encodeUpper($bytes), '=');
    }

    /**
     * Generate TOTP code for a given secret and time
     */
    public static function generateCode(string $secret): string
    {
        $totp = OTPHP_TOTP::createFromSecret($secret);
        return $totp->now();
    }

    /**
     * Verify a TOTP code against a secret
     */
    public static function verifyCode(string $secret, string $code): bool
    {
        $totp = OTPHP_TOTP::createFromSecret($secret);
        // Leeway de 29 segundos (máximo permitido, deve ser < período de 30s)
        // Aceita códigos do período anterior e próximo
        // Isso dá uma janela de ~90 segundos (30s antes, 30s atual, 30s depois)
        return $totp->verify($code, null, 29);
    }

    /**
     * Generate QR code URL for authenticator apps
     */
    public static function getProvisioningUri(string $secret, string $label, string $issuer = 'AD Manager'): string
    {
        $totp = OTPHP_TOTP::createFromSecret($secret);
        $totp->setLabel($label);
        $totp->setIssuer($issuer);
        return $totp->getProvisioningUri();
    }

    /**
     * Generate backup codes (one-time use)
     */
    public static function generateBackupCodes(int $count = 10, int $length = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $code = strtoupper(substr(bin2hex(random_bytes((int)ceil($length / 2))), 0, $length));
            $codes[] = substr($code, 0, $length / 2) . '-' . substr($code, $length / 2);
        }
        return $codes;
    }

    /**
     * Hash backup codes for storage
     */
    public static function hashBackupCodes(array $codes): array
    {
        return array_map(function($code) {
            return password_hash(str_replace('-', '', $code), PASSWORD_ARGON2ID);
        }, $codes);
    }

    /**
     * Verify a backup code against hashed codes
     */
    public static function verifyBackupCode(string $code, array $hashedCodes): ?int
    {
        $cleanCode = str_replace('-', '', $code);
        foreach ($hashedCodes as $index => $hash) {
            if (password_verify($cleanCode, $hash)) {
                return $index; // Return index to remove it after use
            }
        }
        return null;
    }
}
