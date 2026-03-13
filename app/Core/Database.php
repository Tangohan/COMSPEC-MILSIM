<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $pdo = null;

    public static function getPdo(): PDO
    {
        if (self::$pdo === null) {
            $config = config('database.connections.mysql', []);
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                $config['host'] ?? 'localhost',
                $config['database'] ?? '',
                $config['charset'] ?? 'utf8mb4'
            );
            try {
                self::$pdo = new PDO($dsn, $config['username'] ?? '', $config['password'] ?? '', [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
            } catch (PDOException $e) {
                if (config('app.debug', false)) {
                    throw $e;
                }
                throw new \RuntimeException('Database connection failed.', 0, $e);
            }
        }
        return self::$pdo;
    }

    public static function disconnect(): void
    {
        self::$pdo = null;
    }
}
