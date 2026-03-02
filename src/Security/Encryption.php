<?php

declare(strict_types=1);

namespace App\Security;

class Encryption
{
    private const CIPHER = 'aes-256-gcm';
    private string $key;

    public function __construct()
    {
        $this->key = $_ENV['ENCRYPTION_KEY'] ?? $this->generateKey();
    }

    public function encrypt(string $data): string
    {
        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        $iv = openssl_random_pseudo_bytes($ivLength);
        $tag = '';

        $encrypted = openssl_encrypt(
            $data,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            16
        );

        if ($encrypted === false) {
            throw new \RuntimeException('Encryption failed');
        }

        return base64_encode($iv . $tag . $encrypted);
    }

    public function decrypt(string $data): string
    {
        $data = base64_decode($data);
        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        
        $iv = substr($data, 0, $ivLength);
        $tag = substr($data, $ivLength, 16);
        $encrypted = substr($data, $ivLength + 16);

        $decrypted = openssl_decrypt(
            $encrypted,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($decrypted === false) {
            throw new \RuntimeException('Decryption failed');
        }

        return $decrypted;
    }

    private function generateKey(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID);
    }

    public static function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
}
