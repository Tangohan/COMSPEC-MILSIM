<?php

declare(strict_types=1);

namespace Tests\Contract;

use App\Core\Database;
use App\Repositories\AtakIngestPlayerStatsRepository;
use App\Services\Tactical\GameSyncFreshnessService;
use App\Support\ShortCache;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Fraîcheur des données du jeu sur un vrai serveur MySQL ou MariaDB (ONLY_FULL_GROUP_BY, collations de la connexion,
 * tables absentes). Ignoré sans base de test ; la CI le lance sur MySQL 8 et MariaDB (COMSPEC_TEST_MYSQL_DSN,
 * COMSPEC_TEST_MYSQL_USER, COMSPEC_TEST_MYSQL_PASS). La base est vidée au début. Données fictives uniquement.
 */
final class GameSyncFreshnessMysqlTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $dsn = (string) getenv('COMSPEC_TEST_MYSQL_DSN');
        if ($dsn === '') {
            self::markTestSkipped('Base MySQL de test non configurée (COMSPEC_TEST_MYSQL_DSN).');
        }
        ShortCache::setEnabled(false);
        $this->pdo = new PDO($dsn, (string) getenv('COMSPEC_TEST_MYSQL_USER'), (string) getenv('COMSPEC_TEST_MYSQL_PASS'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $this->pdo->exec(Database::sessionInitSql());
        $this->pdo->exec("SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($this->pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
            $this->pdo->exec('DROP TABLE `' . $table . '`');
        }
        $schema = (string) file_get_contents(dirname(__DIR__, 2) . '/migrations/schema.sql');
        foreach (['atak_units', 'atak_chat_messages', 'atak_air_assets'] as $table) {
            if (preg_match('/CREATE TABLE IF NOT EXISTS `' . $table . '` \(.*?\) ENGINE=InnoDB[^;]*;/s', $schema, $m) !== 1) {
                self::fail('Table ' . $table . ' introuvable dans migrations/schema.sql.');
            }
            $this->pdo->exec($m[0]);
        }
        foreach (AtakIngestPlayerStatsRepository::schemaSql() as $sql) {
            $this->pdo->exec($sql);
        }
        $reports = (string) file_get_contents(dirname(__DIR__, 2) . '/migrations/2026_07_24_001_atak_tactical_reports.sql');
        if (preg_match('/CREATE TABLE IF NOT EXISTS atak_tactical_reports \(.*?\) ENGINE=InnoDB[^;]*;/s', $reports, $m) !== 1) {
            self::fail('Table atak_tactical_reports introuvable dans sa migration.');
        }
        $this->pdo->exec($m[0]);
    }

    protected function tearDown(): void
    {
        ShortCache::setEnabled(null);
    }

    public function testSummaryAndPlayersRunInStrictMode(): void
    {
        $now = time();
        $at = static fn (int $ago): string => date('Y-m-d H:i:s', $now - $ago);
        $st = $this->pdo->prepare('INSERT INTO atak_ingest_player_daily (tenant_id, day, steam_uid, callsign, last_at) VALUES (?, ?, ?, ?, ?)');
        $st->execute([7, date('Y-m-d', $now), '76561198000000001', 'Viper 1', $at(5)]);
        $st->execute([7, date('Y-m-d', $now - 86400), '76561198000000001', 'Viper 1', $at(80000)]);
        $this->pdo->prepare("INSERT INTO atak_units (tenant_id, map_id, call_sign, updated_at) VALUES (7, 1, 'VIPER 1', ?), (7, 2, 'Viper 1', ?)")->execute([$at(70), $at(20)]);
        $this->pdo->prepare("INSERT INTO atak_chat_messages (tenant_id, map_id, author, body, source, created_at) VALUES (7, 1, 'Viper 1', 'radio', 'game', ?)")->execute([$at(40)]);
        $this->pdo->prepare("INSERT INTO atak_tactical_reports (tenant_id, context_id, report_type, submitter_steam_id, report_timestamp) VALUES (7, 1, 'SITREP', '76561198000000001', ?)")->execute([$at(30)]);

        $svc = new GameSyncFreshnessService($this->pdo);
        $summary = $svc->tenantSummary(7, $now);
        $flows = array_column($summary['flows'], null, 'key');
        self::assertSame(5, $summary['latest_age_s']);
        self::assertSame(20, $flows['position']['age_s']);
        self::assertSame(40, $flows['chat']['age_s']);
        self::assertSame(30, $flows['reports']['age_s']);
        self::assertNull($flows['markers']['age_s'], 'Table absente : aucune donnée, pas d’erreur.');

        $players = $svc->players(7, $now);
        self::assertCount(1, $players);
        self::assertSame('Viper 1', $players[0]['callsign']);
        self::assertSame(['requests' => 5, 'position' => 20, 'chat' => 40, 'reports' => 30], $players[0]['flows']);
    }
}
