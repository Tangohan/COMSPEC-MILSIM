<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakPoArrivalAssetTest extends TestCase
{
    public function testGameAndOverwatchDetectPoMarkersWithTwentyMetreRadius(): void
    {
        $root = dirname(__DIR__, 2);
        $sqf = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_pollPoMarkers.sqf'
        );
        $loops = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_startSyncLoops.sqf'
        );
        $cfg = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp'
        );
        $controller = (string) file_get_contents($root . '/app/Controllers/Api/AtakApiController.php');
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $overwatch = (string) file_get_contents($root . '/public/assets/js/atak-overwatch-beta.js');
        $view = (string) file_get_contents($root . '/views/atak-overwatch-beta.php');

        self::assertStringContainsString('_radius = 20', $sqf);
        self::assertStringContainsString('_COMSPEC_PO_RING_', $sqf);
        self::assertStringContainsString('Point d’objectif atteint', $sqf);
        self::assertStringContainsString('pollPoMarkers', $loops);
        self::assertStringContainsString('class pollPoMarkers {}', $cfg);
        self::assertStringContainsString('data-tool="po"', $view);
        self::assertStringContainsString('placeReachPoint', $overwatch);
        self::assertStringContainsString('confirmFromOperatorPosition', $controller);
        self::assertStringContainsString('/api/atak/markers/{id}/reached', $routes);
        self::assertStringContainsString('markersPoReached', $routes);
        self::assertStringContainsString("'/api/atak/markers/'", $overwatch);
        self::assertStringContainsString('isPoLabel', $overwatch);
        self::assertStringContainsString('loadPoMarkers', $overwatch);
        self::assertStringContainsString('id="ow-po-markers"', $view);
        self::assertStringContainsString('rayon 20 m', $view);
    }
}
