<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Tactical\AtakTerrainMath;
use App\Services\Tactical\AtakTerrainSight;
use PHPUnit\Framework\TestCase;

final class AtakTerrainSightTest extends TestCase
{
    public function testProfileReportsClimbOnARidge(): void
    {
        $grid = $this->ridgeGrid();
        $out = AtakTerrainSight::profile($grid, [[0, 250], [500, 250]]);
        self::assertTrue($out['ok']);
        self::assertTrue($out['ready']);
        self::assertGreaterThan(400, $out['distance_m']);
        self::assertGreaterThan(40, $out['climb_m']);
        self::assertGreaterThan(40, $out['descent_m']);
        self::assertSame(100.0, $out['min_z']);
        self::assertSame(200.0, $out['max_z']);
        self::assertSame(100, $out['coverage_pct']);
        self::assertFalse($out['gaps']);
        self::assertGreaterThan(4, count($out['samples']));
    }

    public function testProfileMarksUnsurveyedStretch(): void
    {
        $grid = $this->ridgeGrid();
        $out = AtakTerrainSight::profile($grid, [[-500, 250], [0, 250]]);
        self::assertTrue($out['ok']);
        self::assertNotSame('', (string) ($out['gap_message'] ?? ''));
        self::assertStringContainsString('pas encore relevé', (string) $out['gap_message']);
        self::assertLessThan(100, $out['coverage_pct']);
    }

    public function testLineOfSightIsMaskedByRidge(): void
    {
        $grid = $this->ridgeGrid();
        $out = AtakTerrainSight::lineOfSight($grid, 0, 250, 500, 250, 1.6, 0.0);
        self::assertTrue($out['ok']);
        self::assertSame(AtakTerrainSight::VERDICT_MASKED, $out['verdict']);
        self::assertSame('Masqué par le relief', $out['verdict_label']);
        self::assertNotNull($out['obstruction']);
        self::assertGreaterThan(150, (float) $out['obstruction']['z']);
    }

    public function testLineOfSightIgnoresTheCellUnderTheObserverOnADownhill(): void
    {
        $grid = $this->downhillLipGrid();
        $out = AtakTerrainSight::lineOfSight($grid, 10, 250, 480, 250, 1.6, 0.0);
        self::assertTrue($out['ok']);
        self::assertSame(AtakTerrainSight::VERDICT_CLEAR, $out['verdict']);
        self::assertNull($out['obstruction']);
        self::assertStringContainsString('descente', (string) $out['detail']);
    }

    public function testLineOfSightUsesAircraftAltitudeOverARidge(): void
    {
        $grid = $this->ridgeGrid();
        $out = AtakTerrainSight::lineOfSight($grid, 0, 250, 500, 250, 1.6, 0.0, [], 350.0, 100.0);
        self::assertSame(AtakTerrainSight::VERDICT_CLEAR, $out['verdict']);
        self::assertTrue($out['observer_from_unit']);
        self::assertSame(350.0, $out['observer_z']);
    }
    public function testLineOfSightIsClearOnFlatGround(): void
    {
        $grid = $this->flatGrid(80);
        $out = AtakTerrainSight::lineOfSight($grid, 50, 50, 400, 50, 1.6, 0.0);
        self::assertTrue($out['ok']);
        self::assertSame(AtakTerrainSight::VERDICT_CLEAR, $out['verdict']);
        self::assertSame('Visée dégagée', $out['verdict_label']);
        self::assertNull($out['obstruction']);
        self::assertFalse($out['gaps']);
    }

    public function testLineOfSightUnknownWhenEndsAreOffGrid(): void
    {
        $grid = $this->flatGrid(80);
        $out = AtakTerrainSight::lineOfSight($grid, -200, -200, -100, -100, 1.6, 0.0);
        self::assertTrue($out['ok']);
        self::assertFalse($out['ready']);
        self::assertSame(AtakTerrainSight::VERDICT_UNKNOWN, $out['verdict']);
        self::assertSame(AtakTerrainSight::GAP_MESSAGE, $out['gap_message']);
    }

    public function testLineOfSightIsMaskedByBuildingJustInFront(): void
    {
        $grid = $this->flatGrid(80);
        $out = AtakTerrainSight::lineOfSight($grid, 50, 50, 400, 50, 1.6, 0.0, [
            ['x' => 80, 'y' => 50, 'height' => 8, 'width' => 16, 'depth' => 20, 'z' => 80, 'kind' => 'building'],
        ]);
        self::assertSame(AtakTerrainSight::VERDICT_MASKED, $out['verdict']);
        self::assertSame('Masqué par un bâtiment', $out['verdict_label']);
        self::assertNotNull($out['obstruction']);
        self::assertLessThan(35, (float) $out['obstruction']['d']);
        self::assertGreaterThan(15, (float) $out['obstruction']['d']);
    }

    public function testLineOfSightHitsTheNearWallNotTheBuildingCenter(): void
    {
        $grid = $this->flatGrid(80);
        $out = AtakTerrainSight::lineOfSight($grid, 50, 50, 400, 50, 1.6, 0.0, [
            ['x' => 200, 'y' => 50, 'height' => 20, 'width' => 12, 'depth' => 12, 'z' => 80, 'kind' => 'building'],
        ]);
        self::assertSame(AtakTerrainSight::VERDICT_MASKED, $out['verdict']);
        self::assertLessThan(150, (float) $out['obstruction']['d']);
        self::assertGreaterThan(130, (float) $out['obstruction']['d']);
    }

    public function testViewshedIsOpenOnFlatGround(): void
    {
        $grid = $this->flatGrid(80);
        $out = AtakTerrainSight::viewshed($grid, 250, 250, 200, 1.6, []);
        self::assertTrue($out['ok']);
        self::assertTrue($out['ready']);
        self::assertSame('viewshed', $out['mode']);
        self::assertSame(100, $out['visible_pct']);
        self::assertCount(36, $out['sectors']);
        self::assertTrue($out['sectors'][0]['clear']);
    }

    public function testViewshedIsMaskedByABuilding(): void
    {
        $grid = $this->flatGrid(80);
        $out = AtakTerrainSight::viewshed($grid, 50, 50, 400, 1.6, [
            ['x' => 200, 'y' => 50, 'height' => 20, 'width' => 12, 'depth' => 12, 'z' => 80, 'kind' => 'building'],
        ]);
        $masked = array_values(array_filter($out['sectors'], static fn (array $s): bool => empty($s['clear'])));
        self::assertNotSame([], $masked);
        self::assertLessThan(100, $out['visible_pct']);
    }

    public function testHorizonReportsAPeak(): void
    {
        $grid = $this->ridgeGrid();
        $out = AtakTerrainSight::horizon($grid, 50, 250, 400, 1.6, []);
        self::assertTrue($out['ready']);
        self::assertNotNull($out['peak']);
        self::assertCount(72, $out['samples']);
    }

    public function testSliceListsCrossedBuildings(): void
    {
        $grid = $this->flatGrid(80);
        $out = AtakTerrainSight::slice($grid, [[50, 50], [400, 50]], [
            ['x' => 200, 'y' => 50, 'height' => 12, 'width' => 10, 'depth' => 10, 'z' => 80, 'kind' => 'building'],
        ]);
        self::assertSame('slice', $out['mode']);
        self::assertNotSame([], $out['volumes']);
        self::assertSame('building', $out['volumes'][0]['kind']);
    }

    public function testMeasure3dReportsSpatialDistance(): void
    {
        $grid = $this->ridgeGrid();
        $out = AtakTerrainSight::measure3d($grid, 0, 250, 250, 250);
        self::assertTrue($out['ready']);
        self::assertGreaterThan($out['distance_m'], $out['spatial_m']);
        self::assertNotNull($out['delta_m']);
        self::assertGreaterThan(0, $out['azimuth_deg']);
    }

    public function testCoverageGapsListsMissingCells(): void
    {
        $grid = [
            'heights' => AtakTerrainMath::emptyBlob(11 * 11),
            'cols' => 11,
            'rows' => 11,
            'cell_m' => 50,
            'origin_x' => 0,
            'origin_y' => 0,
        ];
        $gaps = AtakTerrainSight::coverageGaps($grid, 8, 40);
        self::assertNotSame([], $gaps);
        self::assertArrayHasKey('x', $gaps[0]);
    }

    public function testLineOfSightIsMaskedByBuildingNotRelief(): void
    {
        $grid = $this->flatGrid(80);
        $out = AtakTerrainSight::lineOfSight($grid, 50, 50, 400, 50, 1.6, 0.0, [
            ['x' => 200, 'y' => 50, 'height' => 20, 'width' => 12, 'z' => 80, 'kind' => 'building'],
        ]);
        self::assertTrue($out['ok']);
        self::assertSame(AtakTerrainSight::VERDICT_MASKED, $out['verdict']);
        self::assertSame('Masqué par un bâtiment', $out['verdict_label']);
        self::assertSame('Masqué par un bâtiment', $out['cause_label']);
        self::assertNotNull($out['obstruction']);
        $false = array_values(array_filter($out['samples'], static fn ($s) => empty($s['clear'])));
        self::assertNotSame([], $false);
    }

    public function testLineOfSightIsMaskedByCover(): void
    {
        $grid = $this->flatGrid(80);
        $out = AtakTerrainSight::lineOfSight($grid, 50, 50, 400, 50, 1.6, 0.0, [
            ['x' => 220, 'y' => 50, 'height' => 16, 'width' => 10, 'z' => 80, 'kind' => 'forest'],
        ]);
        self::assertSame('Masqué par un couvert', $out['verdict_label']);
        self::assertSame('Masqué par un couvert', $out['cause_label']);
    }

    public function testLineOfSightNotesUnsurveyedCoverWhenSceneEmpty(): void
    {
        $grid = $this->ridgeGrid();
        $out = AtakTerrainSight::lineOfSight($grid, 0, 250, 500, 250, 1.6, 0.0, []);
        self::assertSame('Masqué par le relief', $out['verdict_label']);
        self::assertStringContainsString('Couverts non relevés', (string) $out['detail']);
        self::assertFalse($out['scene_ready']);
    }

    public function testPolylineNeedsTwoPoints(): void
    {
        $grid = $this->flatGrid(80);
        $out = AtakTerrainSight::profile($grid, [[10, 10]]);
        self::assertFalse($out['ready']);
        self::assertSame([], $out['samples']);
    }

    /**
     * @return array<string, mixed>
     */
    private function ridgeGrid(): array
    {
        $cols = 11;
        $rows = 11;
        $vals = [];
        for ($r = 0; $r < $rows; $r++) {
            for ($c = 0; $c < $cols; $c++) {
                $vals[] = ($c === 5) ? 200 : 100;
            }
        }

        return [
            'heights' => AtakTerrainMath::packInt16Le($vals),
            'cols' => $cols,
            'rows' => $rows,
            'cell_m' => 50,
            'origin_x' => 0,
            'origin_y' => 0,
            'filled_cells' => $cols * $rows,
        ];
    }

    /**
     * Première case un peu plus haute, puis descente : le sol sous l’observateur ne doit pas masquer.
     *
     * @return array<string, mixed>
     */
    private function downhillLipGrid(): array
    {
        $cols = 11;
        $rows = 11;
        $vals = [];
        for ($r = 0; $r < $rows; $r++) {
            for ($c = 0; $c < $cols; $c++) {
                if ($c === 0) {
                    $vals[] = 165;
                } elseif ($c === 1) {
                    $vals[] = 178;
                } else {
                    $vals[] = 110;
                }
            }
        }

        return [
            'heights' => AtakTerrainMath::packInt16Le($vals),
            'cols' => $cols,
            'rows' => $rows,
            'cell_m' => 50,
            'origin_x' => 0,
            'origin_y' => 0,
            'filled_cells' => $cols * $rows,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function flatGrid(int $z): array
    {
        $cols = 11;
        $rows = 11;

        return [
            'heights' => AtakTerrainMath::packInt16Le(array_fill(0, $cols * $rows, $z)),
            'cols' => $cols,
            'rows' => $rows,
            'cell_m' => 50,
            'origin_x' => 0,
            'origin_y' => 0,
            'filled_cells' => $cols * $rows,
        ];
    }
}
