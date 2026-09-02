<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;
use Throwable;

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
                    'host'     => self::tcpHost((string) ($cfg['host'] ?? '127.0.0.1')),
                    'port'     => (int) ($cfg['port'] ?? 3306),
                    'database' => $cfg['database'] ?? '',
                    'username' => $cfg['username'],
                    'password' => $cfg['password'],
                    'charset'  => $cfg['charset'] ?? 'utf8mb4',
                    'collation' => $cfg['collation'] ?? 'utf8mb4_unicode_ci',
                ];
            }
        }

        $host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: '127.0.0.1';
        $name = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: '';
        $user = $_ENV['DB_USER'] ?? getenv('DB_USER') ?: '';
        $pass = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: '';
        $port = (int) ($_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: 3306);
        $charset = $_ENV['DB_CHARSET'] ?? getenv('DB_CHARSET') ?: 'utf8mb4';
        $collation = $_ENV['DB_COLLATION'] ?? getenv('DB_COLLATION') ?: 'utf8mb4_unicode_ci';

        return [
            'host'     => self::tcpHost((string) ($host ?: '127.0.0.1')),
            'port'     => $port,
            'database' => $name,
            'username' => $user,
            'password' => $pass,
            'charset'  => $charset,
            'collation' => $collation,
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
        // → SQLSTATE[HY000] [2002] Operation not permitted. Forcer TCP 127.0.0.1.
        // Pas de boucle de retry : une connexion refusée pendant un FTP / trop de
        // sessions ne doit pas bloquer le worker. Une seule tentative, puis throw.
        $host = self::tcpHost((string) $cfg['host']);
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $host,
            $cfg['port'],
            $cfg['database'],
            $cfg['charset']
        );

        try {
            self::$pdo = self::connectPdo(
                $dsn,
                $cfg['username'],
                $cfg['password'],
                (string) $cfg['charset'],
                (string) ($cfg['collation'] ?? 'utf8mb4_unicode_ci')
            );
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

    /**
     * Hôte MySQL en TCP. « localhost » / vide / ::1 → 127.0.0.1 (pas de socket Unix).
     */
    public static function tcpHost(string $host): string
    {
        $host = strtolower(trim($host));
        if ($host === '' || $host === 'localhost' || $host === '::1') {
            return '127.0.0.1';
        }

        return $host;
    }

    private static function connectPdo(
        string $dsn,
        string $username,
        string $password,
        string $charset = 'utf8mb4',
        string $collation = 'utf8mb4_unicode_ci'
    ): PDO {
        $sessionSql = self::sessionInitSql($charset, $collation);
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false,
        ];
        // Timeout TCP court (évite de bloquer un worker FPM sur une MySQL morte).
        if (defined('PDO::ATTR_TIMEOUT')) {
            $options[PDO::ATTR_TIMEOUT] = 3;
        }
        if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
            $options[PDO::MYSQL_ATTR_INIT_COMMAND] = $sessionSql;
        }
        $pdo = new ReconnectingPdo($dsn, $username, $password, $options);
        if (!defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
            $pdo->exec($sessionSql);
        }

        return $pdo;
    }

    /**
     * Alignement charset / collation de session + fuseau UTC.
     * Sans collation_connection, un paramètre PDO utf8mb4_general_ci mélangé à une
     * colonne utf8mb4_bin (LOWER/TRIM) déclenche MariaDB 1267.
     */
    public static function sessionInitSql(string $charset = 'utf8mb4', string $collation = 'utf8mb4_unicode_ci'): string
    {
        $charset = self::mysqlCharsetOrCollation($charset, 'utf8mb4');
        $collation = self::mysqlCharsetOrCollation($collation, 'utf8mb4_unicode_ci');

        return "SET character_set_client = '{$charset}', character_set_connection = '{$charset}',"
            . " character_set_results = '{$charset}', collation_connection = '{$collation}',"
            . " time_zone = '+00:00'";
    }

    private static function mysqlCharsetOrCollation(string $value, string $fallback): string
    {
        $value = trim($value);
        if ($value === '' || !preg_match('/^[a-zA-Z0-9_]+$/', $value)) {
            return $fallback;
        }

        return $value;
    }

    /**
     * Session MySQL coupée (wait_timeout, idle FPM, paquet trop gros) — pas une panne durable.
     */
    public static function isLostConnection(Throwable $e): bool
    {
        if ($e instanceof PDOException) {
            $driverCode = (int) ($e->errorInfo[1] ?? 0);
            if (in_array($driverCode, [2006, 2013], true)) {
                return true;
            }
            $code = $e->getCode();
            if ($code === 2006 || $code === 2013 || $code === '2006' || $code === '2013') {
                return true;
            }
        }

        return self::messageLooksLikeLostConnection($e->getMessage());
    }

    public static function messageLooksLikeLostConnection(string $message): bool
    {
        $hay = strtolower($message);

        return str_contains($hay, 'server has gone away')
            || str_contains($hay, 'lost connection')
            || str_contains($hay, 'general error: 2006')
            || str_contains($hay, 'general error: 2013')
            || str_contains($hay, 'sqlstate[hy000] [2006]')
            || str_contains($hay, 'sqlstate[hy000] [2013]');
    }

    /**
     * Recrée la session sur l’instance courante (les dépôts qui la gardent restent valides).
     * Sans instance : no-op — le prochain getPdo() ouvre une connexion neuve.
     */
    public static function reconnect(): void
    {
        if (self::$pdo instanceof ReconnectingPdo) {
            self::$pdo->reconnect();

            return;
        }
        self::$pdo = null;
    }

    /**
     * Exécute une opération ; si la session MySQL est morte, reconnecte une fois puis relance.
     *
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    public static function withReconnect(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (Throwable $e) {
            if (!self::isLostConnection($e)) {
                throw $e;
            }
            self::reconnect();

            return $operation();
        }
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
        return self::withReconnect(function () use ($sql, $params): int {
            $stmt = self::getPdo()->prepare($sql);
            $stmt->execute($params);

            return $this->lastInsertId();
        });
    }

    /**
     * Dernier identifiant auto-incrémenté de la connexion courante.
     * Certains dépôts font execute() puis lastInsertId() au lieu d’insert().
     */
    public function lastInsertId(): int
    {
        return (int) self::getPdo()->lastInsertId();
    }

    /**
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return self::withReconnect(function () use ($sql, $params): array {
            $stmt = self::getPdo()->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return is_array($rows) ? $rows : [];
        });
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        return self::withReconnect(function () use ($sql, $params): ?array {
            $stmt = self::getPdo()->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return is_array($row) ? $row : null;
        });
    }

    /**
     * @param array<string, mixed> $params
     */
    public function execute(string $sql, array $params = []): int
    {
        return self::withReconnect(function () use ($sql, $params): int {
            $stmt = self::getPdo()->prepare($sql);
            $stmt->execute($params);

            return $stmt->rowCount();
        });
    }

    /**
     * Compatibilité des dépôts ATAK : `$db->query($sql, $params)->fetch()` / `fetchAll()`.
     *
     * @param array<int|string, mixed> $params
     */
    public function query(string $sql, array $params = []): \PDOStatement
    {
        return self::withReconnect(function () use ($sql, $params): \PDOStatement {
            $stmt = self::getPdo()->prepare($sql);
            $stmt->execute($params);

            return $stmt;
        });
    }
}
