<?php

declare(strict_types=1);

namespace App\Security;

class CSRF
{
    private const TOKEN_NAME = 'csrf_token';
    private const TOKEN_LENGTH = 32;

    public static function generateToken(): string
    {
        if (!isset($_SESSION[self::TOKEN_NAME])) {
            $_SESSION[self::TOKEN_NAME] = bin2hex(random_bytes(self::TOKEN_LENGTH));
        }
        return $_SESSION[self::TOKEN_NAME];
    }

    public static function validateToken(?string $token): bool
    {
        if (!isset($_SESSION[self::TOKEN_NAME]) || $token === null) {
            return false;
        }

        return hash_equals($_SESSION[self::TOKEN_NAME], $token);
    }

    public static function getTokenName(): string
    {
        return self::TOKEN_NAME;
    }

    public static function regenerateToken(): string
    {
        $_SESSION[self::TOKEN_NAME] = bin2hex(random_bytes(self::TOKEN_LENGTH));
        return $_SESSION[self::TOKEN_NAME];
    }
}
