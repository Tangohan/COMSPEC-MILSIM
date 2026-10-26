<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\AtakRoleTimeCorrectionRepository as Repo;
use PHPUnit\Framework\TestCase;

final class AtakRoleTimeCorrectionTest extends TestCase
{
    private const ROWS = [
        ['day' => '2026-10-12', 'mission_key' => 'op@altis#1', 'seconds' => 3000],
        ['day' => '2026-10-11', 'mission_key' => '', 'seconds' => 0],
        ['day' => '2026-10-05', 'mission_key' => '', 'seconds' => 3000],
    ];

    public function testMoveAllTakesEveryNonEmptyRow(): void
    {
        $takes = Repo::planTakes(self::ROWS, null);
        self::assertSame([
            ['day' => '2026-10-12', 'mission_key' => 'op@altis#1', 'seconds' => 3000],
            ['day' => '2026-10-05', 'mission_key' => '', 'seconds' => 3000],
        ], $takes);
    }

    public function testPartialMoveTakesMostRecentFirst(): void
    {
        $takes = Repo::planTakes(self::ROWS, 3600);
        self::assertSame(3600, array_sum(array_column($takes, 'seconds')));
        self::assertSame(3000, $takes[0]['seconds']);
        self::assertSame(['day' => '2026-10-05', 'mission_key' => '', 'seconds' => 600], $takes[1]);
    }

    public function testPartialMoveNeverTakesMoreThanAvailable(): void
    {
        self::assertSame(6000, array_sum(array_column(Repo::planTakes(self::ROWS, 99999), 'seconds')));
        self::assertSame([], Repo::planTakes([], null));
    }

    public function testTypedRoleGetsSlotKey(): void
    {
        self::assertSame('SLOT:TACP', Repo::keyForLabel('  TACP '));
        self::assertSame('', Repo::keyForLabel('   '));
        self::assertSame(64, mb_strlen(Repo::keyForLabel(str_repeat('x', 200))));
    }

    public function testFicheWiringIsGuardedAndReversible(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        self::assertStringContainsString("'/personnel/{id}/temps-par-role/deplacer'", $routes);
        self::assertStringContainsString("'/personnel/{id}/temps-par-role/{moveId}/annuler'", $routes);
        $ctrl = (string) file_get_contents($root . '/app/Controllers/Web/PersonnelController.php');
        self::assertStringContainsString("return \$this->canStaffEditPersonnel() || Gate::getInstance()->allows('admin.organization');", $ctrl);
        $view = (string) file_get_contents($root . '/views/personnel/file.php')
            . (string) file_get_contents($root . '/views/partials/personnel/file_playtime.php');
        self::assertStringContainsString('Annuler cette correction', $view);
        self::assertStringContainsString('if ($canCorrectRoleTime):', $view);
    }
}
