<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakGpsRoutesAssetTest extends TestCase
{
    public function testWebMapRouteToolCreatesWaypointRoutesForOperators(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root . '/views/atak.php');
        $gps = (string) file_get_contents($root . '/public/assets/js/atak-gps-routes.js');
        $tools = (string) file_get_contents($root . '/public/assets/js/atak-map-tools.js');
        $sounds = (string) file_get_contents($root . '/public/assets/js/atak-sounds.js');
        $roleplay = (string) file_get_contents($root . '/public/assets/js/atak-roleplay-effects.js');
        $ctab = (string) file_get_contents($root . '/public/assets/js/atak-roleplay-ctab.js');

        self::assertStringContainsString('atak-gps-routes.js', $view);
        self::assertStringContainsString('id="atak-gps-route-box"', $view);
        self::assertStringContainsString('Tracer un itinéraire pour les opérateurs', $view);
        self::assertStringContainsString('Transmettre aux opérateurs', $view);
        self::assertStringContainsString("indexOf('milsymbol.js') === 0", $view);

        self::assertStringContainsString('/api/atak/waypoint-routes', $gps);
        self::assertStringContainsString('/api/atak/waypoints', $gps);
        self::assertStringContainsString('is-reached', $gps);
        self::assertStringContainsString('ATAKGpsRoutes', $gps);

        self::assertStringContainsString('ATAKGpsRoutes.toggle', $tools);
        self::assertStringContainsString('cancelGpsRoutes', $tools);

        self::assertStringContainsString('if (!unlocked) return false;', $sounds);
        self::assertStringNotContainsString("console.log('[atak-roleplay]", $roleplay);
        self::assertStringNotContainsString("console.log('[COMSPEC] Effets roleplay", $ctab);
    }
}
