<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Ecosystem\PlatformFieldMetricsService;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Accueil public : téléphones ATAK attribués, heures remontées et kilomètres parcourus (totaux plateforme).
 */
final class PlatformFieldMetricsServiceTest extends TestCase
{
    private string $cache;

    protected function setUp(): void
    {
        $this->cache = sys_get_temp_dir() . '/pfm_' . bin2hex(random_bytes(4)) . '.json';
    }

    protected function tearDown(): void
    {
        @unlink($this->cache);
    }

    private static function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE atak_terminals (id INTEGER PRIMARY KEY, tenant_id INT, user_id INT NULL, terminal_type TEXT)');
        $pdo->exec('CREATE TABLE atak_screen_time (id INTEGER PRIMARY KEY, tenant_id INT, user_id INT, kind TEXT, item_key TEXT, seconds INT)');
        $pdo->exec('CREATE TABLE atak_distance_stats (id INTEGER PRIMARY KEY, tenant_id INT, user_id INT, foot_m INT, land_m INT, sea_m INT, air_m INT, halo_m INT)');

        return $pdo;
    }

    public function testCountsOnlyAssignedModPhonesAndRealTotals(): void
    {
        $pdo = self::pdo();
        $pdo->exec("INSERT INTO atak_terminals (tenant_id, user_id, terminal_type) VALUES
            (1, 10, 'phone'), (1, 11, 'phone'), (2, 12, 'tablet'), (2, NULL, 'phone'), (1, 10, 'web')");
        $pdo->exec("INSERT INTO atak_screen_time (tenant_id, user_id, kind, item_key, seconds) VALUES
            (1, 10, 'play', 'TOTAL', 5400), (2, 12, 'play', 'TOTAL', 9000),
            (1, 10, 'screen', 'TOTAL', 99999), (1, 10, 'role', 'RIFLEMAN', 99999)");
        $pdo->exec('INSERT INTO atak_distance_stats (tenant_id, user_id, foot_m, land_m, sea_m, air_m, halo_m) VALUES
            (1, 10, 4200, 12000, 0, 0, 0), (2, 12, 800, 0, 300, 25000, 1200)');

        $totals = (new PlatformFieldMetricsService($pdo, $this->cache))->totals(true);

        self::assertSame(['phones' => 3, 'play_hours' => 4, 'distance_km' => 43], $totals);
    }

    public function testMissingTablesGiveZeroNotInventedFigures(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $totals = (new PlatformFieldMetricsService($pdo, $this->cache))->totals(true);

        self::assertSame(['phones' => 0, 'play_hours' => 0, 'distance_km' => 0], $totals);
    }

    public function testTotalsAreCachedForFifteenMinutes(): void
    {
        $pdo = self::pdo();
        $pdo->exec("INSERT INTO atak_terminals (tenant_id, user_id, terminal_type) VALUES (1, 10, 'phone')");
        $service = new PlatformFieldMetricsService($pdo, $this->cache);
        self::assertSame(1, $service->totals()['phones']);

        $pdo->exec("INSERT INTO atak_terminals (tenant_id, user_id, terminal_type) VALUES (1, 11, 'phone')");
        self::assertSame(1, $service->totals()['phones'], 'servi depuis le cache');
        self::assertSame(2, $service->totals(true)['phones']);
    }

    public function testHomeShowsOnlyNonZeroFieldFiguresInBothLanguages(): void
    {
        $root = dirname(__DIR__, 2);
        $fr = require $root . '/lang/fr/home.php';
        $en = require $root . '/lang/en/home.php';
        $keys = static fn (array $a): array => array_values(array_filter(array_keys($a), static fn (string $k): bool => str_starts_with($k, 'field_')));
        self::assertSame($keys($fr), $keys($en));
        self::assertNotSame([], $keys($fr));

        $view = (string) file_get_contents($root . '/views/home/index.php');
        self::assertStringContainsString('$fieldShown = array_values(array_filter($fieldStats, static fn (array $f): bool => $f[\'v\'] > 0));', $view);
        // La valeur finale reste dans le HTML ; le JS ne fait que l’animer.
        self::assertStringContainsString('data-hi-count="<?= (int) $f[\'v\'] ?>"><?= $e($formatInt($f[\'v\'])) ?>', $view);
    }
}
