<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Admin\PlatformDataVolumeService;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\MysqlDdlSqlitePdo;

/**
 * Volume de données par jour : ce que chaque chiffre mesure, période bornée, aucune valeur inventée.
 * Base SQLite en mémoire, données fictives.
 */
final class PlatformDataVolumeServiceTest extends TestCase
{
    /** 2026-11-08 12:00 UTC */
    private const NOW = 1_794_139_200;

    private function pdo(bool $withTables = true): PDO
    {
        $pdo = new MysqlDdlSqlitePdo();
        $pdo->exec('CREATE TABLE tenants (id INTEGER PRIMARY KEY, name TEXT, slug TEXT)');
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT, display_name TEXT, callsign TEXT, steam_id TEXT, deleted_at TEXT)');
        $pdo->exec("INSERT INTO tenants (id, name, slug) VALUES (1, 'Unité Alpha', 'alpha'), (2, 'Unité Bravo', 'bravo')");
        $pdo->exec("INSERT INTO users (id, email, display_name, callsign, steam_id) VALUES
            (1, 'alice@example.test', 'Alice Test', 'Viper', '76561198000000001')");
        if (!$withTables) {
            return $pdo;
        }
        $pdo->exec('CREATE TABLE atak_ingest_traffic (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, map_id INTEGER, bucket_ts TEXT, bytes INTEGER, photo_bytes INTEGER, requests INTEGER)');
        $pdo->exec('CREATE TABLE atak_ingest_player_daily (tenant_id INTEGER, day TEXT, steam_uid TEXT, callsign TEXT, bytes INTEGER, photo_bytes INTEGER, requests INTEGER, events_accepted INTEGER, events_rejected INTEGER, last_at TEXT)');
        $pdo->exec('CREATE TABLE recon_images (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, created_at TEXT)');
        $pdo->exec("INSERT INTO atak_ingest_traffic (tenant_id, map_id, bucket_ts, bytes, photo_bytes, requests) VALUES
            (1, 1, '2026-11-08 08:00:00', 1000, 400, 2),
            (1, 1, '2026-11-08 08:00:10', 500, 0, 1),
            (1, 2, '2026-11-07 23:59:50', 700, 0, 3),
            (2, 1, '2026-11-07 10:00:00', 2000, 0, 5),
            (2, 1, '2026-10-01 10:00:00', 99999, 0, 9)");
        $pdo->exec("INSERT INTO atak_ingest_player_daily VALUES
            (1, '2026-11-08', '76561198000000001', 'Viper', 1500, 400, 3, 40, 1, '2026-11-08 08:00:10'),
            (1, '2026-11-07', '76561198000000001', 'Viper', 700, 0, 3, 10, 0, '2026-11-07 23:59:50'),
            (2, '2026-11-07', '76561198000000002', 'Rookie', 2000, 0, 5, 20, 0, '2026-11-07 10:00:00'),
            (2, '2026-11-07', 'cs:Ghost', 'Ghost', 50, 0, 1, 0, 0, '2026-11-07 10:00:00')");
        $pdo->exec("INSERT INTO recon_images (tenant_id, created_at) VALUES (1, '2026-11-08 09:00:00'), (1, '2026-11-08 09:05:00'), (2, '2026-11-07 09:05:00')");

        return $pdo;
    }

    private function service(PDO $pdo): PlatformDataVolumeService
    {
        return new PlatformDataVolumeService($pdo, static fn (): int => self::NOW);
    }

    public function testPeriodIsLimitedToAShortWhitelist(): void
    {
        foreach ([0, -5, 3, 365, 'x', null] as $bad) {
            self::assertSame(7, PlatformDataVolumeService::normalizePeriod($bad));
        }
        self::assertSame(31, PlatformDataVolumeService::normalizePeriod('31'));
        self::assertSame(14, PlatformDataVolumeService::normalizePeriod(14));
        self::assertLessThanOrEqual(31, max(PlatformDataVolumeService::PERIODS));
        $days = $this->service($this->pdo())->days(7);
        self::assertCount(7, $days);
        self::assertSame('2026-11-08', $days[6]);
        self::assertSame('2026-11-02', $days[0]);
    }

    public function testBytesAreFormattedInReadableUnits(): void
    {
        self::assertSame('0 o', PlatformDataVolumeService::formatBytes(0));
        self::assertSame('999 o', PlatformDataVolumeService::formatBytes(999));
        self::assertSame('1,5 Ko', PlatformDataVolumeService::formatBytes(1500));
        self::assertSame('2,0 Mo', PlatformDataVolumeService::formatBytes(2_000_000));
        self::assertSame('12 Go', PlatformDataVolumeService::formatBytes(12_300_000_000));
    }

    public function testDailyTotalsAddUpWhatEachSourceMeasuresAndNothingElse(): void
    {
        $totals = $this->service($this->pdo())->dailyTotals(7);
        self::assertSame(1500, $totals['2026-11-08']['bytes']);
        self::assertSame(400, $totals['2026-11-08']['photo_bytes']);
        self::assertSame(3, $totals['2026-11-08']['requests']);
        self::assertSame(1, $totals['2026-11-08']['communities']);
        self::assertSame(2, $totals['2026-11-08']['photos'], 'Nombre de photos, pas un poids.');
        self::assertSame(2700, $totals['2026-11-07']['bytes']);
        self::assertSame(2, $totals['2026-11-07']['communities']);
        self::assertSame(0, $totals['2026-11-03']['bytes']);
        self::assertArrayNotHasKey('2026-10-01', $totals, 'Hors période : jamais compté.');

        $one = $this->service($this->pdo())->dailyTotals(7, 2);
        self::assertSame(2000, $one['2026-11-07']['bytes']);
        self::assertSame(0, $one['2026-11-08']['bytes']);
    }

    public function testMissingSourcesStayEmptyInsteadOfInventingZeros(): void
    {
        $service = $this->service($this->pdo(false));
        self::assertSame(['communities' => false, 'players' => false, 'photos' => false], $service->sources());
        $totals = $service->dailyTotals(7);
        self::assertNull($totals['2026-11-08']['photos'], 'Photos non mesurées : aucune valeur affichée.');
        self::assertSame([], $service->byCommunity(7)['rows']);
        self::assertSame([], $service->byAccount(7)['rows']);
        self::assertSame([], $service->bytesBySteam(['76561198000000001']));
    }

    public function testByCommunityGivesOneColumnPerDayBiggestFirst(): void
    {
        $result = $this->service($this->pdo())->byCommunity(7);
        self::assertCount(7, $result['days']);
        self::assertSame(['Unité Alpha', 'Unité Bravo'], array_column($result['rows'], 'name'));
        $alpha = $result['rows'][0];
        self::assertSame(2200, $alpha['total']);
        self::assertSame(1500, $alpha['cells']['2026-11-08']);
        self::assertSame(700, $alpha['cells']['2026-11-07']);
        self::assertSame(400, $alpha['photo_bytes']);
        self::assertFalse($result['truncated']);
    }

    public function testByAccountLinksSteamPlayersToAccountsAndKeepsOthersAsNotLinked(): void
    {
        $result = $this->service($this->pdo())->byAccount(7);
        $byLabel = [];
        foreach ($result['rows'] as $row) {
            $byLabel[$row['label']] = $row;
        }
        self::assertTrue($byLabel['Viper']['linked']);
        self::assertSame('alice@example.test', $byLabel['Viper']['email_key']);
        self::assertSame(2200, $byLabel['Viper']['total']);
        self::assertSame(1500, $byLabel['Viper']['cells']['2026-11-08']);
        self::assertSame(40 + 10, $byLabel['Viper']['events']);
        self::assertFalse($byLabel['Rookie']['linked'], 'Steam ID sans compte : visible mais non relié.');
        self::assertFalse($byLabel['Ghost']['linked']);
        self::assertSame(['Viper', 'Rookie', 'Ghost'], array_column($result['rows'], 'label'), 'Classement par volume décroissant.');

        $bravo = $this->service($this->pdo())->byAccount(7, 2);
        self::assertSame(['Rookie', 'Ghost'], array_column($bravo['rows'], 'label'));
    }

    public function testBytesBySteamIsLimitedToTheRequestedIdsAndWindow(): void
    {
        $out = $this->service($this->pdo())->bytesBySteam(['76561198000000001', ' ', '76561198000000099'], 30);
        self::assertSame(['76561198000000001' => 2200], $out);
        self::assertSame([], $this->service($this->pdo())->bytesBySteam([]));
    }

    public function testDataPageIsReadOnlyAndBehindTheSameProtectionAsTheDirectory(): void
    {
        $root = dirname(__DIR__, 2);
        $ctrl = (string) file_get_contents($root . '/app/Controllers/Admin/System/SystemUsersDataController.php');
        self::assertStringNotContainsString('->post(', $ctrl);
        self::assertStringNotContainsString('INSERT', $ctrl);
        $svc = (string) file_get_contents($root . '/app/Services/Admin/PlatformDataVolumeService.php');
        self::assertDoesNotMatchRegularExpression('/\b(INSERT|UPDATE|DELETE)\b\s/', $svc);
    }
}
