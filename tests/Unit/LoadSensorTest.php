<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Watch\LoadLevel;
use App\Services\Watch\WatchFlusher;
use App\Services\Watch\WatchSettings;
use App\Services\Watch\WatchSpool;
use App\Services\Watch\WatchState;
use PHPUnit\Framework\TestCase;

/**
 * Capteur de charge : sans mesure fiable, personne n'est ralenti. Un capteur en panne (état absent, ancien, non fiable,
 * base muette) ne doit couper aucun joueur.
 */
final class LoadSensorTest extends TestCase
{
    private string $dir = '';

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/watch-test-' . bin2hex(random_bytes(4));
        mkdir($this->dir, 0775, true);
        WatchSpool::setDir($this->dir);
        WatchState::forget();
    }

    protected function tearDown(): void
    {
        WatchSpool::setDir(null);
        WatchState::forget();
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->dir);
    }

    /** @return array<string, float|int> */
    private function settings(): array
    {
        return WatchSettings::defaults();
    }

    public function testNoStateOrAStaleOrUnreliableStateAppliesNoLevel(): void
    {
        $now = 1_800_000_000;
        $good = ['at' => $now, 'shed_enabled' => true, 'level' => 3, 'sensor' => ['reliable' => true]];
        self::assertSame(3, WatchState::effectiveLevel($good, $now));
        // Capteur en panne : aucun état.
        self::assertSame(0, WatchState::effectiveLevel(null, $now));
        // État trop ancien (le passage de mesure ne tourne plus).
        self::assertSame(0, WatchState::effectiveLevel(['at' => $now - 500] + $good, $now));
        self::assertSame(0, WatchState::effectiveLevel(['at' => $now - LoadLevel::MAX_AGE_SECONDS - 1] + $good, $now));
        // Mesure non fiable.
        self::assertSame(0, WatchState::effectiveLevel(['sensor' => ['reliable' => false]] + $good, $now));
        self::assertSame(0, WatchState::effectiveLevel(['sensor' => []] + $good, $now));
        // Ralentissement désactivé : le palier se mesure mais ne s'applique pas.
        self::assertSame(0, WatchState::effectiveLevel(['shed_enabled' => false] + $good, $now));
        self::assertSame(3, WatchState::measuredLevel(['shed_enabled' => false] + $good, $now));
        // Horloge qui recule fortement : état douteux.
        self::assertSame(0, WatchState::effectiveLevel(['at' => $now + 600] + $good, $now));
    }

    public function testDecodeRejectsGarbage(): void
    {
        self::assertNull(WatchState::decode(false));
        self::assertNull(WatchState::decode(''));
        self::assertNull(WatchState::decode('pas du json'));
        self::assertNull(WatchState::decode('{"level":3}'));
    }

    public function testTooFewRequestsAndNoLoadGivesNoReliableSignal(): void
    {
        $eval = LoadLevel::evaluate(['requests' => 12, 'p95_ms' => 9000, 'error_pct' => 80.0, 'load_per_core' => null], $this->settings());
        self::assertFalse($eval['reliable']);
        self::assertSame(0, $eval['raw_level']);
        self::assertSame('too_few_requests', $eval['reason']);
    }

    public function testSystemLoadAloneIsAReliableSignal(): void
    {
        $eval = LoadLevel::evaluate(['requests' => 0, 'p95_ms' => null, 'error_pct' => null, 'load_per_core' => 6.0], $this->settings());
        self::assertTrue($eval['reliable']);
        self::assertSame(3, $eval['raw_level']);
        self::assertSame(['load'], $eval['used']);
    }

    public function testRawLevelFollowsTheWorstRatioToItsThreshold(): void
    {
        $s = $this->settings();
        $p95 = (int) $s[WatchSettings::ALERT_P95_MS];
        $base = ['requests' => 100, 'error_pct' => 0.0, 'load_per_core' => null];
        self::assertSame(0, LoadLevel::evaluate(['p95_ms' => $p95 - 1] + $base, $s)['raw_level']);
        self::assertSame(1, LoadLevel::evaluate(['p95_ms' => $p95] + $base, $s)['raw_level']);
        self::assertSame(2, LoadLevel::evaluate(['p95_ms' => (int) ($p95 * 1.5)] + $base, $s)['raw_level']);
        self::assertSame(3, LoadLevel::evaluate(['p95_ms' => (int) ($p95 * 2.5)] + $base, $s)['raw_level']);
        // Les erreurs 5xx comptent aussi.
        $err = (float) $s[WatchSettings::ALERT_ERROR_PCT];
        self::assertSame(2, LoadLevel::evaluate(['requests' => 100, 'p95_ms' => 50, 'error_pct' => $err * 1.6, 'load_per_core' => null], $s)['raw_level']);
    }

    public function testAnUnreliableMeasureResetsTheLevelAtOnce(): void
    {
        self::assertSame(['level' => 0, 'up' => 0, 'down' => 0], LoadLevel::settle(3, false, ['level' => 3, 'up' => 0, 'down' => 0]));
    }

    public function testLevelRisesAfterTwoMeasuresAndFallsOneStepAfterThree(): void
    {
        $st = ['level' => 0, 'up' => 0, 'down' => 0];
        $st = LoadLevel::settle(2, true, $st);
        self::assertSame(0, $st['level']);
        $st = LoadLevel::settle(2, true, $st);
        self::assertSame(2, $st['level']);
        // Une mesure normale isolée ne fait pas redescendre.
        $st = LoadLevel::settle(0, true, $st);
        $st = LoadLevel::settle(0, true, $st);
        self::assertSame(2, $st['level']);
        $st = LoadLevel::settle(0, true, $st);
        self::assertSame(1, $st['level']);
        // Un pic isolé au-dessus remet le compteur de descente à zéro.
        $st = LoadLevel::settle(3, true, $st);
        self::assertSame(1, $st['level']);
        self::assertSame(0, $st['down']);
    }

    public function testPercentileAndLoadPerCore(): void
    {
        self::assertNull(LoadLevel::percentile([], 95));
        self::assertSame(95, LoadLevel::percentile(range(1, 100), 95));
        self::assertSame(7, LoadLevel::percentile([7], 95));
        self::assertSame(1.5, LoadLevel::loadPerCore([6.0, 0.0, 0.0], 4));
        self::assertNull(LoadLevel::loadPerCore(null, 4));
        self::assertNull(LoadLevel::loadPerCore([1.0, 1.0, 1.0], null));
        self::assertNull(LoadLevel::loadPerCore([1.0, 1.0, 1.0], 0));
    }

    public function testSignalsIgnoreShedAdminAndCronRequests(): void
    {
        $rows = [
            ['L', '1', '5', 'GET /c/{slug}', '200', '100', '0'],
            ['L', '1', '5', 'GET /c/{slug}', '500', '300', '0'],
            ['L', '1', '5', 'GET /c/{slug}', '503', '1', '1'],
            ['L', '1', '0', 'GET /admin/system/surcharge', '500', '9000', '0'],
            ['L', '1', '0', 'POST /cron/run', '500', '9000', '0'],
        ];
        $s = WatchFlusher::signals($rows, null, null);
        self::assertSame(2, $s['requests']);
        self::assertSame(50.0, $s['error_pct']);
        self::assertSame(300, $s['p95_ms']);
        self::assertNull($s['load_per_core']);
    }

    public function testFlusherPublishesAnUnreliableStateWhenNothingWasMeasured(): void
    {
        $now = 1_800_000_000;
        $down = static function (): \PDO {
            throw new \RuntimeException('base muette');
        };
        $summary = WatchFlusher::run($now, $down, null, null, true);
        self::assertTrue($summary['ran']);
        self::assertTrue($summary['db_error'] ?? false);
        $state = WatchState::decode(file_get_contents($this->dir . '/state.json'));
        self::assertNotNull($state);
        self::assertFalse($state['sensor']['reliable']);
        self::assertSame(0, $state['level']);
        self::assertFalse($state['shed_enabled'], 'ralentissement désactivé par défaut, base muette comprise');
        self::assertSame(0, WatchState::effectiveLevel($state, $now));
    }

    public function testFlusherMeasuresFromFilesAloneAndStillNeverRaisesTheLevelOnOneSample(): void
    {
        $now = 1_800_000_030;
        for ($i = 0; $i < 60; $i++) {
            WatchSpool::append(WatchSpool::KIND_LOAD, implode('|', ['L', $now - 10, 7, 'GET /c/{slug}', 200, 9000, 0]), $now - 10);
        }
        $down = static function (): \PDO {
            throw new \RuntimeException('base muette');
        };
        WatchFlusher::run($now, $down, null, null, true);
        $state = WatchState::decode(file_get_contents($this->dir . '/state.json'));
        self::assertTrue($state['sensor']['reliable']);
        self::assertSame(1, $state['raw_level'] >= 1 ? 1 : 0);
        // Une seule mesure ne suffit pas à monter (anti-bascule).
        self::assertSame(0, $state['level']);
        WatchFlusher::run($now + 31, $down, null, null, true);
        $state = WatchState::decode(file_get_contents($this->dir . '/state.json'));
        self::assertSame($state['raw_level'], $state['level']);
        self::assertGreaterThan(0, $state['level']);
        // Les réglages n'ayant pas pu être lus, le ralentissement reste désactivé : aucun palier appliqué.
        self::assertFalse($state['shed_enabled']);
        self::assertSame(0, WatchState::effectiveLevel($state, $now + 31));
    }

    public function testSettingsDefaultToShedDisabledAndStayWithinBounds(): void
    {
        $d = WatchSettings::defaults();
        self::assertSame(0, $d[WatchSettings::SHED_ENABLED]);
        $clamped = WatchSettings::fromMap([
            WatchSettings::SHED_ENABLED => '1',
            WatchSettings::SHED_PERCENT => '5000',
            WatchSettings::ALERT_P95_MS => '-4',
            WatchSettings::ALERT_LOAD_PER_CORE => '2,5',
            WatchSettings::SPEED_MPS => 'abc',
        ]);
        self::assertSame(1, $clamped[WatchSettings::SHED_ENABLED]);
        self::assertSame(100, $clamped[WatchSettings::SHED_PERCENT]);
        self::assertSame(500, $clamped[WatchSettings::ALERT_P95_MS]);
        self::assertSame(2.5, $clamped[WatchSettings::ALERT_LOAD_PER_CORE]);
        self::assertSame($d[WatchSettings::SPEED_MPS], $clamped[WatchSettings::SPEED_MPS]);
        // Case à cocher absente du formulaire = désactivé ; champ absent = inchangé.
        $form = WatchSettings::fromForm([WatchSettings::SHED_PERCENT => '40'], [WatchSettings::SHED_ENABLED => 1, WatchSettings::ALERT_P95_MS => 4000], [WatchSettings::SHED_ENABLED]);
        self::assertSame(0, $form[WatchSettings::SHED_ENABLED]);
        self::assertSame(40, $form[WatchSettings::SHED_PERCENT]);
        self::assertSame(4000, $form[WatchSettings::ALERT_P95_MS]);
    }
}
