<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use PDOException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DatabaseLostConnectionTest extends TestCase
{
    protected function tearDown(): void
    {
        Database::disconnect();
        parent::tearDown();
    }

    public function testDetectsMysqlGoneAwaySqlstate(): void
    {
        $e = new PDOException('SQLSTATE[HY000]: General error: 2006 MySQL server has gone away');
        $e->errorInfo = ['HY000', 2006, 'MySQL server has gone away'];

        self::assertTrue(Database::isLostConnection($e));
        self::assertTrue(Database::messageLooksLikeLostConnection($e->getMessage()));
    }

    public function testDetectsLostConnectionDuringQuery(): void
    {
        $e = new PDOException('SQLSTATE[HY000]: General error: 2013 Lost connection to MySQL server during query');
        $e->errorInfo = ['HY000', 2013, 'Lost connection to MySQL server during query'];

        self::assertTrue(Database::isLostConnection($e));
    }

    public function testDetectsDriverCodesWithoutEnglishMessage(): void
    {
        $e = new PDOException('SQLSTATE[HY000]: General error: 2006');
        $e->errorInfo = ['HY000', 2006, ''];

        self::assertTrue(Database::isLostConnection($e));
    }

    public function testIgnoresUnrelatedDatabaseErrors(): void
    {
        $e = new PDOException('SQLSTATE[42S02]: Base table or view not found: 1146');
        $e->errorInfo = ['42S02', 1146, "Table 'x.y' doesn't exist"];

        self::assertFalse(Database::isLostConnection($e));
        self::assertFalse(Database::messageLooksLikeLostConnection($e->getMessage()));
    }

    public function testWithReconnectRetriesOnceThenSucceeds(): void
    {
        $calls = 0;
        $result = Database::withReconnect(static function () use (&$calls): string {
            $calls++;
            if ($calls === 1) {
                $e = new PDOException('SQLSTATE[HY000]: General error: 2006 MySQL server has gone away');
                $e->errorInfo = ['HY000', 2006, 'MySQL server has gone away'];
                throw $e;
            }

            return 'ok';
        });

        self::assertSame('ok', $result);
        self::assertSame(2, $calls);
    }

    public function testWithReconnectDoesNotRetryUnrelatedErrors(): void
    {
        $calls = 0;
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('schema');

        try {
            Database::withReconnect(static function () use (&$calls): never {
                $calls++;
                throw new RuntimeException('schema');
            });
        } finally {
            self::assertSame(1, $calls);
        }
    }

    public function testWithReconnectDoesNotLoopWhenRetryAlsoFails(): void
    {
        $calls = 0;
        $this->expectException(PDOException::class);

        try {
            Database::withReconnect(static function () use (&$calls): never {
                $calls++;
                $e = new PDOException('SQLSTATE[HY000]: General error: 2006 MySQL server has gone away');
                $e->errorInfo = ['HY000', 2006, 'MySQL server has gone away'];
                throw $e;
            });
        } finally {
            self::assertSame(2, $calls);
        }
    }

    public function testErrorHintHidesSqlstateFromVisitor(): void
    {
        $hint = athena_error_hint('SQLSTATE[HY000]: General error: 2006 MySQL server has gone away');
        self::assertNotSame('', $hint);
        self::assertStringNotContainsString('SQLSTATE', $hint);
        self::assertStringNotContainsString('PDO', $hint);
        self::assertStringNotContainsString('2006', $hint);
        self::assertStringContainsString('réessayez', mb_strtolower($hint));
    }

    public function testTcpHostRemapsLocalhostAndEmptyToLoopback(): void
    {
        self::assertSame('127.0.0.1', Database::tcpHost('localhost'));
        self::assertSame('127.0.0.1', Database::tcpHost(' LocalHost '));
        self::assertSame('127.0.0.1', Database::tcpHost(''));
        self::assertSame('127.0.0.1', Database::tcpHost('::1'));
        self::assertSame('127.0.0.1', Database::tcpHost('127.0.0.1'));
        self::assertSame('db.internal', Database::tcpHost('db.internal'));
    }

    public function testGetPdoDoesNotRetryLoopOnConnectFailure(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Core/Database.php');
        self::assertStringNotContainsString('$attempts = 3', $src);
        self::assertStringContainsString('function tcpHost', $src);
        self::assertStringContainsString('déploiement FTP n’est pas en cours', $src);
    }
}
