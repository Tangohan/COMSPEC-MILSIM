<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\PlayerSessionRepository;
use App\Support\SqlText;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\MysqlInformationSchemaPdo;

/** Jointure texte entre une table historique (collation du serveur) et une table récente (utf8mb4_unicode_ci). */
final class SqlTextColumnEqualsTest extends TestCase
{
    public function testMysqlConvertsTheOtherSideOnly(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $mysql = new MysqlInformationSchemaPdo();
        self::assertSame('ps.steam_uid = (CONVERT(u.steam_id USING utf8mb4) COLLATE utf8mb4_unicode_ci)', SqlText::columnEquals($mysql, 'ps.steam_uid', 'u.steam_id'));
        self::assertSame('ps.steam_uid = u.steam_id', SqlText::columnEquals(new PDO('sqlite::memory:'), 'ps.steam_uid', 'u.steam_id'));

        [$clause] = PlayerSessionRepository::qualifyingExistsClause('u', time(), 3600, $mysql);
        self::assertStringContainsString('ps.steam_uid = (CONVERT(u.steam_id USING utf8mb4) COLLATE utf8mb4_unicode_ci)', $clause);
    }

    public function testRejectsExpressions(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->expectException(InvalidArgumentException::class);
        SqlText::columnEquals(new PDO('sqlite::memory:'), 'ps.steam_uid', 'u.steam_id OR 1=1');
    }
}
