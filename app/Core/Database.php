<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $pdo = null;

    private static ?self $instance = null;

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
                    'host'     => $cfg['host'] ?? '127.0.0.1',
                    'port'     => (int) ($cfg['port'] ?? 3306),
                    'database' => $cfg['database'] ?? '',
                    'username' => $cfg['username'],
                    'password' => $cfg['password'],
                    'charset'  => $cfg['charset'] ?? 'utf8mb4',
                ];
            }
        }

        $host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: '127.0.0.1';
        $name = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: '';
        $user = $_ENV['DB_USER'] ?? getenv('DB_USER') ?: '';
        $pass = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: '';
        $port = (int) ($_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: 3306);
        $charset = $_ENV['DB_CHARSET'] ?? getenv('DB_CHARSET') ?: 'utf8mb4';

        return [
            'host'     => $host ?: '127.0.0.1',
            'port'     => $port,
            'database' => $name,
            'username' => $user,
            'password' => $pass,
            'charset'  => $charset,
        ];
    }

    /** Accès singleton pour les repositories ATAK Phase 2 (insert/fetchAll/…). */
    public static function getInstance(): self
    {
        return self::$instance ??= new self();
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

        // Hostinger / PHP-FPM : « localhost » tente souvent un socket Unix inexistant
        // → SQLSTATE[HY000] [2002] Operation not permitted. Préférer 127.0.0.1 (TCP).
        $host = $cfg['host'];
        if ($host === 'localhost') {
            $host = '127.0.0.1';
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $host,
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
            $detail = $e->getMessage();
            $hint = '';
            if (str_contains($detail, '2002') || str_contains($detail, 'Operation not permitted')) {
                $hint = ' Vérifiez DB_HOST=127.0.0.1 (pas localhost socket) dans .env / database.local.php, et qu’un déploiement FTP n’est pas en cours.';
            }
            throw new RuntimeException('Database connection failed: ' . $detail . $hint, 0, $e);
        }

        return self::$pdo;
    }

    public static function disconnect(): void
    {
        self::$pdo = null;
        self::$instance = null;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function insert(string $sql, array $params = []): int
    {
        $stmt = self::getPdo()->prepare($sql);
        $stmt->execute($params);

        return (int) self::getPdo()->lastInsertId();
    }

    /**
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = self::getPdo()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = self::getPdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function execute(string $sql, array $params = []): int
    {
        $stmt = self::getPdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }
}
