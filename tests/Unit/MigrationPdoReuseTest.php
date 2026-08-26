<?php

declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use Throwable;

final class MigrationPdoReuseTest extends TestCase
{
    private string $prevHost;

    private string $prevName;

    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 2) . '/bootstrap/migration_pdo.php';
        $this->prevHost = (string) ($_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: '');
        $this->prevName = (string) ($_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: '');
    }

    protected function tearDown(): void
    {
        if ($this->prevHost === '') {
            unset($_ENV['DB_HOST']);
            putenv('DB_HOST');
        } else {
            $_ENV['DB_HOST'] = $this->prevHost;
            putenv('DB_HOST=' . $this->prevHost);
        }
        if ($this->prevName === '') {
            unset($_ENV['DB_NAME']);
            putenv('DB_NAME');
        } else {
            $_ENV['DB_NAME'] = $this->prevName;
            putenv('DB_NAME=' . $this->prevName);
        }
        parent::tearDown();
    }

    public function testLocalhostIsRemappedToTcpLoopback(): void
    {
        self::assertSame('127.0.0.1', migration_normalize_mysql_host('localhost'));
        self::assertSame('127.0.0.1', migration_normalize_mysql_host('LocalHost'));
        self::assertSame('127.0.0.1', migration_normalize_mysql_host(''));
        self::assertSame('127.0.0.1', migration_normalize_mysql_host('127.0.0.1'));
        self::assertSame('mysql.example.com', migration_normalize_mysql_host('mysql.example.com'));
    }

    public function testDsnNeverUsesUnixSocketWhenEnvSaysLocalhost(): void
    {
        $_ENV['DB_HOST'] = 'localhost';
        putenv('DB_HOST=localhost');
        $_ENV['DB_NAME'] = 'u416380327_BDD_PROD';
        putenv('DB_NAME=u416380327_BDD_PROD');

        $dsn = migration_mysql_dsn();
        self::assertStringContainsString('host=127.0.0.1', $dsn);
        self::assertStringNotContainsString('host=localhost', $dsn);
        self::assertStringNotContainsString('unix_socket', $dsn);
        self::assertStringContainsString('dbname=u416380327_BDD_PROD', $dsn);
    }

    public function testReconnectReusesAlivePipelinePdo(): void
    {
        $pdo = $this->makeAlivePdoStub();
        $id = spl_object_id($pdo);
        migration_reconnect_pdo($pdo);
        self::assertSame($id, spl_object_id($pdo), 'Une session encore vivante ne doit pas ouvrir une seconde connexion.');
    }

    public function testConnectBlockedIsNotTreatedAsGoneAwayRetry(): void
    {
        $msg = 'SQLSTATE[HY000] [2002] Operation not permitted';
        self::assertTrue(migration_is_connect_blocked($msg));
        self::assertFalse(migration_is_lost_connection($msg));
    }

    public function testIntelligenceMigrationReusesHelperAndDoesNotOpenLocalhostSocket(): void
    {
        $path = dirname(__DIR__, 2) . '/bootstrap/atak_modules_schema_migration.php';
        $src = (string) file_get_contents($path);
        self::assertStringContainsString('migration_reconnect_pdo', $src);
        self::assertStringContainsString('migration_pdo.php', $src);
        self::assertStringNotContainsString('mysql:host={$host}', $src);
        self::assertStringContainsString('extensions intelligence', $src);
        self::assertStringContainsString('connectWarnLogged', $src);
        self::assertMatchesRegularExpression('/break;/', $src);
    }

    public function testIntelligenceStepLogsConnectFailureOnceThenStops(): void
    {
        $migrate = require dirname(__DIR__, 2) . '/bootstrap/atak_modules_schema_migration.php';
        self::assertIsCallable($migrate);

        $pdo = $this->makeThrowingPdoStub();

        ob_start();
        try {
            $migrate($pdo);
        } catch (Throwable) {
            // information_schema après les fichiers — hors périmètre de ce test.
        }
        $out = (string) ob_get_clean();

        self::assertSame(
            1,
            substr_count($out, '[ATTENTION] extensions intelligence'),
            "Un échec de connexion ne doit pas répéter le même avertissement pour chaque instruction.\n" . $out
        );
        self::assertLessThan(
            20,
            $pdo->execCalls,
            'La boucle d’instructions doit s’arrêter dès la première erreur de connexion, pas tout le fichier.'
        );
    }

    private function makeAlivePdoStub(): PDO
    {
        return new class extends PDO {
            public function __construct()
            {
            }

            public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
            {
                return new class extends PDOStatement {
                    public function __construct()
                    {
                    }
                };
            }
        };
    }

    private function makeThrowingPdoStub(): PDO
    {
        return new class extends PDO {
            public int $execCalls = 0;

            public function __construct()
            {
            }

            public function exec(string $statement): int|false
            {
                $this->execCalls++;
                throw new PDOException('SQLSTATE[HY000] [2002] Operation not permitted');
            }
        };
    }
}
