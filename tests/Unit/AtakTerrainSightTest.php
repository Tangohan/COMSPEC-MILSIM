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
