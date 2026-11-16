<?php

declare(strict_types=1);

namespace Tests\Contract;

use App\Core\Database;
use App\Repositories\AtakDataRepository;
use App\Repositories\MapShapeRepository;
use App\Services\Atak\MarkerCycle\AtakMarkerCycleFilter;
use App\Services\Atak\MarkerCycle\AtakMarkerCycleService;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\InMemoryMarkerCycleStore;

/**
 * Lecture des tracés et marqueurs avec le cycle de 24 h sur un vrai serveur MySQL ou MariaDB.
 *
 * La connexion de l'application est en utf8mb4_unicode_ci alors que les tables gardent la collation par défaut du serveur :
 * un littéral de date dans COALESCE faisait échouer la requête (erreur 1267, collations mélangées) et les tracés de la
 * carte ne se chargeaient plus. Ignoré sans base de test ; la CI le lance sur MySQL 8 et MariaDB (COMSPEC_TEST_MYSQL_DSN,
 * COMSPEC_TEST_MYSQL_USER, COMSPEC_TEST_MYSQL_PASS). La base est vidée au début. Données fictives uniquement.
 */
final class AtakMarkerCycleReadMysqlTest extends TestCase
{
    private const T = 7;

    private PDO $pdo;

    protected function setUp(): void
    {
        $dsn = (string) getenv('COMSPEC_TEST_MYSQL_DSN');
        if ($dsn === '') {
            self::markTestSkipped('Base MySQL de test non configurée (COMSPEC_TEST_MYSQL_DSN).');
        }
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
        foreach (['atak_markers', 'atak_map_shapes'] as $table) {
            if (preg_match('/CREATE TABLE IF NOT EXISTS `' . $table . '` \(.*?\) ENGINE=InnoDB[^;]*;/s', $schema, $m) !== 1) {
                self::fail('Table ' . $table . ' introuvable dans migrations/schema.sql.');
            }
            $this->pdo->exec($m[0]);
        }
        $store = new InMemoryMarkerCycleStore();
        $store->settingsByTenant[self::T] = ['enabled' => true, 'switch_time' => '12:00', 'duration_days' => 1, 'timezone' => 'Europe/Paris', 'retention_days' => 1095];
        $store->now = '2026-11-15 12:00:00';
        $now = (new \DateTimeImmutable('2026-11-15 13:00:00', new \DateTimeZone('Europe/Paris')))->getTimestamp();
        AtakMarkerCycleFilter::useService(new AtakMarkerCycleService($store, static fn (): int => $now));
    }

    protected function tearDown(): void
    {
        AtakMarkerCycleFilter::useService(null);
    }

    private function insertShape(string $uid, ?string $meta, ?string $at): int
    {
        $st = $this->pdo->prepare('INSERT INTO atak_map_shapes (tenant_id, map_id, shape_uid, type, geometry, meta, created_at, updated_at) VALUES (?, 1, ?, ?, ?, ?, ?, ?)');
        $st->execute([self::T, $uid, 'line', '{"points":[[1,1],[2,2]]}', $meta, $at, $at]);

        return (int) $this->pdo->lastInsertId();
    }

    private function insertMarker(string $data, ?string $at): int
    {
        $st = $this->pdo->prepare('INSERT INTO atak_markers (tenant_id, map_id, layer_id, marker_data, created_at, updated_at) VALUES (?, 1, 1, ?, ?, ?)');
        $st->execute([self::T, $data, $at, $at]);

        return (int) $this->pdo->lastInsertId();
    }

    public function testShapesOfTheCurrentCycleAreServedOnARealServer(): void
    {
        $recent = $this->insertShape('recent', null, '2026-11-15 11:30:00');
        $old = $this->insertShape('old', null, '2026-11-14 09:00:00');
        $oldPinned = $this->insertShape('pinned', '{"web_permanent": true}', '2026-11-14 09:00:00');
        $undated = $this->insertShape('undated', null, '2026-11-14 09:00:00');
        $this->pdo->exec('UPDATE atak_map_shapes SET created_at = NULL, updated_at = NULL WHERE id = ' . $undated);

        $rows = (new MapShapeRepository($this->pdo))->list(self::T, 1);
        $ids = array_map(static fn (array $r): int => (int) $r['id'], $rows);

        self::assertContains($recent, $ids);
        self::assertNotContains($old, $ids);
        self::assertContains($oldPinned, $ids);
        self::assertContains($undated, $ids);
    }

    public function testMarkersOfTheCurrentCycleAreServedOnARealServer(): void
    {
        $recent = $this->insertMarker('{"text":"recent"}', '2026-11-15 11:30:00');
        $old = $this->insertMarker('{"text":"old"}', '2026-11-14 09:00:00');
        $oldPinned = $this->insertMarker('{"text":"pin","web_permanent":true}', '2026-11-14 09:00:00');
        $undated = $this->insertMarker('{"text":"none"}', '2026-11-14 09:00:00');
        $this->pdo->exec('UPDATE atak_markers SET created_at = NULL, updated_at = NULL WHERE id = ' . $undated);

        $rows = (new AtakDataRepository($this->pdo))->getMarkers(self::T, 1);
        $ids = array_map(static fn (array $r): int => (int) $r['id'], $rows);

        self::assertContains($recent, $ids);
        self::assertNotContains($old, $ids);
        self::assertContains($oldPinned, $ids);
        self::assertContains($undated, $ids);
    }
}
