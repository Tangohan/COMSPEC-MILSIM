<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\AtakToc\PhoneTriangulation;
use PHPUnit\Framework\TestCase;

/**
 * Triangulation d'un téléphone du jeu par des sources du jeu (relais, téléphones alliés) : déterministe (graine),
 * un cercle avec 1 source, deux points avec 2, une position et une zone d'incertitude avec 3 ou plus ; précision selon le
 * nombre de sources et le terrain ; sources hors portée ignorées ; toujours une estimation.
 */
final class PhoneTriangulationTest extends TestCase
{
    private const TARGET = [10000, 12000];

    /** @return list<array{id: string, kind: string, x: float, y: float, range: float}> */
    private static function ring(int $n, float $dist = 600.0, float $range = 2000.0): array
    {
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $a = 2 * M_PI * $i / $n;
            $out[] = ['id' => 's' . $i, 'kind' => 'relay', 'x' => self::TARGET[0] + $dist * cos($a), 'y' => self::TARGET[1] + $dist * sin($a), 'range' => $range];
        }

        return $out;
    }

    private static function error(array $r): float
    {
        return hypot($r['position'][0] - self::TARGET[0], $r['position'][1] - self::TARGET[1]);
    }

    public function testSameInputsAndSeedGiveTheSameResult(): void
    {
        $a = PhoneTriangulation::estimate(self::TARGET, self::ring(5), 1234, 0.3);
        $b = PhoneTriangulation::estimate(self::TARGET, self::ring(5), 1234, 0.3);
        self::assertSame($a, $b);
        $c = PhoneTriangulation::estimate(self::TARGET, self::ring(5), 1235, 0.3);
        self::assertNotSame($a['readings'], $c['readings'], 'une autre graine donne un autre bruit');
        self::assertTrue($a['is_estimate'], 'toujours marqué estimation');
    }

    public function testSourceOrderDoesNotChangeTheResult(): void
    {
        $src = self::ring(4);
        $a = PhoneTriangulation::estimate(self::TARGET, $src, 77);
        $b = PhoneTriangulation::estimate(self::TARGET, array_reverse($src), 77);
        self::assertSame($a['position'], $b['position']);
        self::assertSame($a['radius_m'], $b['radius_m']);
    }

    public function testAddingASourceKeepsTheNoiseOfTheOthers(): void
    {
        $three = PhoneTriangulation::estimate(self::TARGET, self::ring(3), 9);
        $four = PhoneTriangulation::estimate(self::TARGET, array_merge(self::ring(3), [['id' => 'zz', 'x' => 10100, 'y' => 12100, 'range' => 2000]]), 9);
        $byId = array_column($four['readings'], null, 'id');
        foreach ($three['readings'] as $r) {
            self::assertSame($r['distance_m'], $byId[$r['id']]['distance_m'], $r['id']);
            self::assertSame($r['bearing_deg'], $byId[$r['id']]['bearing_deg'], $r['id']);
        }
    }

    public function testNoSourceMeansNoPosition(): void
    {
        $r = PhoneTriangulation::estimate(self::TARGET, [], 1);
        self::assertSame('none', $r['status']);
        self::assertNull($r['position']);
        self::assertNull($r['radius_m']);
        $far = PhoneTriangulation::estimate(self::TARGET, [['id' => 'far', 'x' => 30000, 'y' => 30000, 'range' => 2000]], 1);
        self::assertSame('none', $far['status'], 'source hors de portée : ne l’entend pas');
        self::assertSame(0, $far['heard']);
        self::assertSame(1, $far['sources']);
    }

    public function testOneSourceGivesACircleOnly(): void
    {
        $r = PhoneTriangulation::estimate(self::TARGET, self::ring(1), 5);
        self::assertSame('circle', $r['status']);
        self::assertNull($r['position'], 'une source ne place pas le téléphone');
        self::assertGreaterThan(300.0, $r['radius_m']);
        self::assertLessThan(900.0, $r['radius_m']);
        self::assertSame('faible', $r['accuracy']);
    }

    public function testTwoSourcesGiveTwoCandidatesAndAWideZone(): void
    {
        $two = PhoneTriangulation::estimate(self::TARGET, self::ring(2, 600.0), 5);
        self::assertSame('ambiguous', $two['status']);
        self::assertGreaterThanOrEqual(1, count($two['candidates']));
        self::assertLessThanOrEqual(2, count($two['candidates']));
        $three = PhoneTriangulation::estimate(self::TARGET, self::ring(3, 600.0), 5);
        self::assertGreaterThan($three['radius_m'], $two['radius_m'], 'deux sources : zone plus large que trois');
        // Les deux candidats sont de part et d'autre de la ligne des sources ; l'un des deux est près du téléphone.
        $best = min(array_map(static fn (array $p): float => hypot($p[0] - self::TARGET[0], $p[1] - self::TARGET[1]), $two['candidates']));
        self::assertLessThan(250.0, $best);
    }

    public function testThreeSourcesGiveAPositionInsideTheirUncertaintyZone(): void
    {
        $inside = 0;
        for ($seed = 1; $seed <= 60; $seed++) {
            $r = PhoneTriangulation::estimate(self::TARGET, self::ring(3), $seed * 101);
            self::assertSame('estimate', $r['status']);
            if (self::error($r) <= $r['radius_m']) {
                $inside++;
            }
        }
        self::assertGreaterThanOrEqual(50, $inside, 'la zone d’incertitude contient presque toujours la vraie position');
    }

    public function testMoreSourcesShrinkTheUncertaintyZone(): void
    {
        $mean = static function (int $n): float {
            $sum = 0.0;
            for ($seed = 1; $seed <= 40; $seed++) {
                $sum += PhoneTriangulation::estimate(self::TARGET, self::ring($n), $seed * 31)['radius_m'];
            }

            return $sum / 40;
        };
        $r3 = $mean(3);
        $r6 = $mean(6);
        $r12 = $mean(12);
        self::assertGreaterThan($r6, $r3);
        self::assertGreaterThan($r12, $r6);
    }

    public function testDenseTerrainDegradesPrecisionAndHearing(): void
    {
        $mean = static function (float $terrain): float {
            $sum = 0.0;
            for ($seed = 1; $seed <= 60; $seed++) {
                $r = PhoneTriangulation::estimate(self::TARGET, self::ring(5, 900.0), $seed * 13, $terrain);
                $sum += $r['radius_m'];
            }

            return $sum / 60;
        };
        self::assertGreaterThan($mean(0.0), $mean(1.0), 'terrain couvert : zone plus large');
        self::assertSame(0.0, PhoneTriangulation::linkQuality(2500.0, 2000.0, 0.0), 'hors de portée');
        self::assertGreaterThan(PhoneTriangulation::linkQuality(1500.0, 2000.0, 0.9), PhoneTriangulation::linkQuality(1500.0, 2000.0, 0.0));
    }

    public function testErrorsAreBoundedAndReadingsCarryBearingAndSignal(): void
    {
        $r = PhoneTriangulation::estimate(self::TARGET, self::ring(4), 3);
        foreach ($r['readings'] as $x) {
            self::assertGreaterThanOrEqual(5.0, $x['distance_m']);
            self::assertGreaterThanOrEqual(5.0, $x['error_pct']);
            self::assertLessThanOrEqual(25.0, $x['error_pct']);
            self::assertGreaterThanOrEqual(0.0, $x['bearing_deg']);
            self::assertLessThan(360.0, $x['bearing_deg']);
            self::assertLessThan(0, $x['signal_dbm']);
        }
        self::assertGreaterThanOrEqual(PhoneTriangulation::MIN_RADIUS_M, $r['radius_m']);
        self::assertLessThanOrEqual(PhoneTriangulation::MAX_RADIUS_M, $r['radius_m']);
        // Bruit contrôlé : l'erreur de distance reste dans la marge annoncée de chaque lien.
        foreach ($r['readings'] as $x) {
            $true = hypot($x['x'] - self::TARGET[0], $x['y'] - self::TARGET[1]);
            self::assertLessThanOrEqual($true * ($x['error_pct'] / 100.0) + 0.2, abs($x['distance_m'] - $true));
        }
    }

    public function testAtMostTwelveBestHeardSourcesAreUsed(): void
    {
        $src = self::ring(20, 500.0);
        $r = PhoneTriangulation::estimate(self::TARGET, $src, 8);
        self::assertSame(PhoneTriangulation::MAX_SOURCES, $r['heard']);
        self::assertSame(20, $r['sources']);
    }

    public function testBearingPointsFromSourceToTarget(): void
    {
        // Source à l'ouest du téléphone : relèvement vers l'est (90° ± erreur du lien).
        $r = PhoneTriangulation::estimate(self::TARGET, [['id' => 'w', 'x' => 9500, 'y' => 12000, 'range' => 2000]], 2);
        self::assertEqualsWithDelta(90.0, $r['readings'][0]['bearing_deg'], 16.0);
    }
}
