<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakOverwatchBetaAssetTest extends TestCase
{
    public function testBetaWorkspaceRouteAndCoreInteractionsArePresent(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');
        $view = file_get_contents(dirname(__DIR__, 2) . '/views/atak-overwatch-beta.php');
        $dashboardAside = file_get_contents(dirname(__DIR__, 2) . '/views/partials/dashboard_aside.php');
        $sidebar = file_get_contents(dirname(__DIR__, 2) . '/views/partials/ath_sidebar_nav.php');

        self::assertIsString($routes);
        self::assertIsString($view);
        self::assertIsString($dashboardAside);
        self::assertIsString($sidebar);
        self::assertStringContainsString("'/-ATAK-OVERWATCH-Beta'", $routes);
        self::assertStringContainsString("'/atak-overwatch-beta'", $routes);
        self::assertStringContainsString("url('-ATAK-OVERWATCH-Beta')", $dashboardAside);
        self::assertStringContainsString("'Overwatch Beta'", $sidebar);
        self::assertStringNotContainsString("require base_path('views/atak.php')", $view);
        self::assertStringContainsString('class="ow-shell"', $view);
        self::assertStringContainsString('id="ow-map"', $view);
        self::assertStringContainsString("[AtakController::class, 'overwatchBeta']", $routes);

        $atakController = file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Web/AtakController.php');
        $atakApiController = file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Api/AtakApiController.php');
        $overwatchJs = file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-overwatch-beta.js');
        $gotakJs = file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-overwatch-gotak.js');
        $realtimeJs = file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-realtime.js');
        $p2Js = file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-overwatch-p2.js');
        $recipe = file_get_contents(dirname(__DIR__, 2) . '/scripts/qa/atak-overwatch-beta-recipe.sh');
        self::assertIsString($atakController);
        self::assertIsString($atakApiController);
        self::assertIsString($overwatchJs);
        self::assertIsString($gotakJs);
        self::assertIsString($realtimeJs);
        self::assertIsString($p2Js);
        self::assertIsString($recipe);
        self::assertStringContainsString("\$params['_overwatch_beta'] = true", $atakController);
        self::assertStringContainsString("'atakOverwatchBeta' => !empty(\$params['_overwatch_beta'])", $atakController);
        self::assertStringContainsString('window.ATAK_OVERWATCH_BETA = true', $view);
        self::assertStringContainsString('atak-overwatch-beta.css', $view);
        self::assertStringContainsString("'/api/units?mapId='", $overwatchJs);
        self::assertStringContainsString('L.map(\'ow-map\'', $overwatchJs);
        self::assertStringContainsString('window.MGRS_CRS', $overwatchJs);
        self::assertStringContainsString('athena:atak-fond-look', $overwatchJs);
        self::assertStringContainsString('data-ow-look', $view);
        self::assertStringContainsString('id="ow-settings"', $view);
        self::assertStringContainsString('id="ow-chat"', $view);
        self::assertStringContainsString('window.OverwatchBeta', $overwatchJs);
        self::assertStringContainsString('window.ATAK_CSRF', $view);
        self::assertStringContainsString('atak-overwatch-gotak.js', $view);
        self::assertStringContainsString("'/api/nine-line'", $overwatchJs);
        self::assertStringContainsString('patients_t1_urgent', $overwatchJs);
        self::assertStringContainsString("'/api/sse/notes/web'", $gotakJs);
        self::assertStringContainsString("'/api/atak/terrain/profile'", $gotakJs);
        self::assertStringContainsString("'/api/atak/activity'", $gotakJs);
        self::assertStringContainsString('data-tool="eta"', $view);
        self::assertStringContainsString('id="ow-geofence"', $view);
        self::assertStringContainsString('id="ow-squad-links"', $view);
        self::assertStringContainsString('data-tool="circle"', $view);
        self::assertStringContainsString('data-tool="los"', $view);
        self::assertStringContainsString('data-tool="po"', $view);
        self::assertStringContainsString('placeReachPoint', $overwatchJs);
        self::assertStringContainsString('/api/atak/waypoint-routes', $overwatchJs);
        self::assertStringContainsString('id="ow-group-task-host"', $view);
        self::assertStringContainsString('ow-group-task-form', $overwatchJs);
        self::assertStringContainsString('submitGroupTask', $overwatchJs);
        self::assertStringContainsString('/api/atak/orders', $overwatchJs);
        self::assertStringContainsString('data-squad-task', $overwatchJs);
        self::assertStringContainsString('id="ow-fs-alert-host"', $view);
        self::assertStringContainsString('submitFullscreenAlert', $overwatchJs);
        self::assertStringContainsString("order_type: 'NOTIFY_FULL'", $overwatchJs);
        self::assertStringContainsString('data-tool="rally"', $view);
        self::assertStringContainsString('placeRallyPoint', $overwatchJs);
        self::assertStringContainsString("zone_type: 'RALLY_POINT'", $overwatchJs);
        self::assertStringContainsString('/api/atak/zones', $overwatchJs);
        self::assertStringContainsString('id="ow-rally-markers"', $view);
        self::assertStringContainsString("'tacticalZonesDestroy'", $routes);
        self::assertStringContainsString('id="ow-follow"', $view);
        self::assertStringContainsString('id="ow-po-markers"', $view);
        self::assertStringContainsString("'/api/atak/markers/'", $overwatchJs);
        self::assertStringContainsString('loadPoMarkers', $overwatchJs);
        self::assertStringContainsString("'/api/atak/terrain/los'", $overwatchJs);
        self::assertStringContainsString('data-chat-tab="squads"', $view);
        self::assertStringContainsString('renderSquadLinks', $overwatchJs);
        self::assertStringContainsString('id="ow-disclaimer"', $view);
        self::assertStringContainsString('atak-map-crs.js', $view);
        self::assertStringContainsString('data-view="comms"', $view);
        self::assertStringContainsString("'/api/chat?mapId='", $overwatchJs);
        self::assertStringContainsString("'/api/pings'", $overwatchJs);
        self::assertStringContainsString('renderMap();', $overwatchJs);
        self::assertStringContainsString('renderList();', $overwatchJs);
        self::assertStringContainsString('syncStatus(true)', $overwatchJs);
        self::assertStringContainsString('window.__OVERWATCH_STANDALONE__', $overwatchJs);
        self::assertStringContainsString("'/api/atak/stream'", $routes);
        self::assertStringContainsString('text/event-stream', $atakApiController);
        self::assertStringContainsString("new EventSource(base + '/api/atak/stream", $realtimeJs);
        self::assertStringContainsString('setRealtimeActive(active)', $realtimeJs);
        self::assertSame('support', \App\Support\AtakChatChannel::normalizeKey('SUPPORT'));
        self::assertSame('Support technique', \App\Support\AtakChatChannel::labelFor('support'));
        self::assertStringContainsString("FORMAT = 'athena-overwatch-mission'", $p2Js);
        self::assertStringContainsString('MAX_IMPORT_SHAPES = 50', $p2Js);
        self::assertStringContainsString('ATAKMapShapes.createShape', $p2Js);
        self::assertStringContainsString('stream anonyme refusé', $recipe);
        self::assertStringContainsString("event: units", $recipe);
        self::assertStringNotContainsString('ALPHA 1-1', $view);
        self::assertStringNotContainsString('CONTACT C-018', $view);
    }
}
