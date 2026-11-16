<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\AtakDataRepository;
use App\Repositories\MapShapeRepository;
use App\Services\Atak\MarkerCycle\AtakMarkerCycleFilter;
use App\Services\Atak\MarkerCycle\AtakMarkerCycleService;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\InMemoryMarkerCycleStore;
use Tests\Support\TocPurgeFixture;

/**
 * Lecture des marqueurs et tracés avec le cycle de 24 h, exécutée pour de bon (SQLite). Le même fragment SQL est rejoué
 * sur MySQL et MariaDB par AtakMarkerCycleReadMysqlTest : MySQL refusait « COALESCE(date, date, '9999-…') >= ? »
 * (erreur 1267, collations mélangées) et les tracés ne se chargeaient plus.
 */
final class AtakMarkerCycleReadSqlTest extends TestCase
{
    private const T = 7;
    private const CUTOFF = '2026-11-15 11:00:00';

    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = TocPurgeFixture::open();
        // JSON_UNQUOTE n'existe pas en SQLite : un booléen JSON y vaut 1 ou 0, MySQL le rend « true » ou « false ».
        $this->pdo->sqliteCreateFunction('JSON_UNQUOTE', static fn ($v) => match (true) {
            $v === null => null,
            $v === 1 || $v === '1' => 'true',
            $v === 0 || $v === '0' => 'false',
            default => (string) $v,
        }, 1);
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

    public function testCutoffIsTheStartOfTheCurrentCycle(): void
    {
        self::assertSame(self::CUTOFF, AtakMarkerCycleFilter::cutoff(self::T));
    }

    public function testShapesKeepRecentPinnedAndUndatedOnly(): void
    {
        $geo = ['points' => [[1, 1], [2, 2]]];
        $recent = TocPurgeFixture::shape($this->pdo, self::T, 1, 'line', $geo, 'A', null, '2026-11-15 11:30:00');
        $old = TocPurgeFixture::shape($this->pdo, self::T, 1, 'line', $geo, 'A', null, '2026-11-14 09:00:00');
        $oldPinned = TocPurgeFixture::shape($this->pdo, self::T, 1, 'line', $geo, 'A', ['web_permanent' => true], '2026-11-14 09:00:00');
        $undated = TocPurgeFixture::shape($this->pdo, self::T, 1, 'line', $geo, 'A', null, '2026-11-14 09:00:00');
        $this->pdo->exec('UPDATE atak_map_shapes SET created_at = NULL, updated_at = NULL WHERE id = ' . $undated);

        $ids = array_map(static fn (array $r): int => (int) $r['id'], (new MapShapeRepository($this->pdo))->list(self::T, 1));

        self::assertContains($recent, $ids, 'tracé du cycle en cours');
        self::assertNotContains($old, $ids, 'ancien tracé masqué');
        self::assertContains($oldPinned, $ids, 'ancien tracé épinglé conservé');
        self::assertContains($undated, $ids, 'tracé sans date conservé');
    }

    public function testMarkersKeepRecentPinnedAndUndatedOnly(): void
    {
        $recent = TocPurgeFixture::marker($this->pdo, self::T, 1, ['text' => 'recent'], null, '2026-11-15 11:30:00');
        $old = TocPurgeFixture::marker($this->pdo, self::T, 1, ['text' => 'old'], null, '2026-11-14 09:00:00');
        $oldPinned = TocPurgeFixture::marker($this->pdo, self::T, 1, ['text' => 'pin', 'web_permanent' => true], null, '2026-11-14 09:00:00');
        $undated = TocPurgeFixture::marker($this->pdo, self::T, 1, ['text' => 'none'], null, '2026-11-14 09:00:00');
        $this->pdo->exec('UPDATE atak_markers SET created_at = NULL, updated_at = NULL WHERE id = ' . $undated);

        $ids = array_map(static fn (array $r): int => (int) $r['id'], (new AtakDataRepository($this->pdo))->getMarkers(self::T, 1));

        self::assertContains($recent, $ids);
        self::assertNotContains($old, $ids);
        self::assertContains($oldPinned, $ids);
        self::assertContains($undated, $ids);
    }

    public function testNoDateLiteralIsComparedToAColumnThroughCoalesce(): void
    {
        foreach ([AtakMarkerCycleFilter::MARKER_SQL, AtakMarkerCycleFilter::SHAPE_SQL] as $sql) {
            self::assertDoesNotMatchRegularExpression("/COALESCE\([^()]*'\d{4}-\d{2}-\d{2}/", $sql);
            self::assertStringNotContainsString('9999-12-31', $sql);
        }
        $root = dirname(__DIR__, 2);
        foreach (['app/Repositories/MapShapeRepository.php', 'app/Repositories/AtakDataRepository.php', 'app/Services/Atak/MarkerCycle/PdoMarkerCycleStore.php'] as $file) {
            self::assertDoesNotMatchRegularExpression("/COALESCE\([^)]*'\d{4}-\d{2}-\d{2}/", (string) file_get_contents($root . '/' . $file), $file);
        }
    }
}
