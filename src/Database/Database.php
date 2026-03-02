<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            try {
                $dsn = sprintf(
                    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                    $_ENV['DB_HOST'] ?? 'mysql',
                    $_ENV['DB_PORT'] ?? '3306',
                    $_ENV['DB_NAME'] ?? 'ad_manager'
                );

                self::$instance = new PDO(
                    $dsn,
                    $_ENV['DB_USER'] ?? 'ad_user',
                    $_ENV['DB_PASS'] ?? 'secure_password',
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '-03:00'"
                    ]
                );
            } catch (PDOException $e) {
                error_log('Database connection failed: ' . $e->getMessage());
                throw new \RuntimeException('Database connection failed');
            }
        }

        return self::$instance;
    }

    public static function beginTransaction(): void
    {
        self::getInstance()->beginTransaction();
    }

    public static function commit(): void
    {
        self::getInstance()->commit();
    }

    public static function rollBack(): void
    {
        self::getInstance()->rollBack();
    }
}
