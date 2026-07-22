<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $pdo = null;

    /**
     * Charge la config DB depuis app/Config/database.local.php (prioritaire), sinon .env / env().
     */
    private static function getConfig(): array
    {
        $localPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'database.local.php';
        if (is_file($localPath) && is_readable($localPath)) {
            $cfg = require $localPath;
            if (is_array($cfg) && isset($cfg['username'], $cfg['password'])) {
                return [
                    'host'     => $cfg['host'] ?? 'localhost',
                    'port'     => (int) ($cfg['port'] ?? 3306),
                    'database' => $cfg['database'] ?? '',
                    'username' => $cfg['username'],
                    'password' => $cfg['password'],
                    'charset'  => $cfg['charset'] ?? 'utf8mb4',
                ];
            }
        }

        $host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost';
        $name = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: '';
        $user = $_ENV['DB_USER'] ?? getenv('DB_USER') ?: '';
        $pass = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: '';
        $port = (int) ($_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: 3306);
        $charset = $_ENV['DB_CHARSET'] ?? getenv('DB_CHARSET') ?: 'utf8mb4';

        return [
            'host'     => $host ?: 'localhost',
            'port'     => $port,
            'database' => $name,
            'username' => $user,
            'password' => $pass,
            'charset'  => $charset,
        ];
    }

    public static function getPdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $cfg = self::getConfig();
        if ($cfg['username'] === '' || $cfg['password'] === '') {
            throw new RuntimeException(
                'Database connection failed: identifiants vides. Renseignez app/Config/database.local.php (voir database.local.php.example) ou .env (DB_USER, DB_PASSWORD).'
            );
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $cfg['host'],
            $cfg['port'],
            $cfg['database'],
            $cfg['charset']
        );

        try {
            self::$pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            // Horloge SQL stable (évite expires_at / NOW() incohérents selon le serveur hôte).
            self::$pdo->exec("SET time_zone = '+00:00'");
        } catch (PDOException $e) {
            throw new RuntimeException('Database connection failed: ' . $e->getMessage(), 0, $e);
        }

        return self::$pdo;
    }

    public static function disconnect(): void
    {
        self::$pdo = null;
    }
}
