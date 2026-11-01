<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Api\AtakApiController;
use App\Core\Request;
use App\Services\Tactical\AtakBridgeModulesService;
use PHPUnit\Framework\TestCase;

/**
 * Ancien partage des caméras vers Athena (captures d'écran périodiques) : retiré du site.
 * Les anciennes versions du mod doivent recevoir une réponse propre (410) et voir le module coupé.
 */
final class LegacyCameraShareRetiredTest extends TestCase
{
    public function testOldCameraRosterEndpointAnswersGone(): void
    {
        $ctrl = (new \ReflectionClass(AtakApiController::class))->newInstanceWithoutConstructor();
        $res = $ctrl->videoFeeds(new Request());

        self::assertSame(410, $res->statusCode());
        $body = json_decode($res->body(), true);
        self::assertIsArray($body);
        self::assertSame('gone', $body['error']);
        self::assertSame([], $body['feeds']);
    }

    public function testRetiredModuleIsSentDisabledToTheGame(): void
    {
        $catalogIds = array_column((new AtakBridgeModulesService())->catalog(), 'id');
        self::assertNotContains('video_feeds', $catalogIds);

        $forGame = AtakBridgeModulesService::forGame(['weather' => true]);
        self::assertTrue($forGame['weather']);
        self::assertFalse($forGame['video_feeds']);
    }

    public function testRoutesStayRegisteredForOldMods(): void
    {
        $routes = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');
        self::assertStringContainsString("\$router->get('/api/atak/video-feeds', [AtakApiController::class, 'videoFeeds']);", $routes);
        self::assertStringContainsString("\$router->post('/api/atak/video-feeds', [AtakApiController::class, 'videoFeeds']);", $routes);
    }

    public function testPeriodicPhoneCapturesAreIgnoredOnUpload(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Api/AtakApiController.php');
        self::assertStringContainsString("if (str_starts_with(\$legacyFeed, 'chest:')) {", $src);
    }

    public function testWebPagesNoLongerOfferTheLiveCamera(): void
    {
        $root = dirname(__DIR__, 2);
        $beta = (string) file_get_contents($root . '/public/assets/js/atak-overwatch-beta.js');
        $cams = (string) file_get_contents($root . '/public/assets/js/atak-cams.js');
        $atak = (string) file_get_contents($root . '/views/atak.php');
        self::assertStringNotContainsString('/api/atak/video-feeds', $beta . $cams);
        self::assertStringNotContainsString("openView('cams')", $beta);
        self::assertStringNotContainsString('id="tab-cams"', $atak);
        self::assertStringNotContainsString('helmet-stream', (string) file_get_contents($root . '/public/assets/js/atak-unit-menu.js'));
    }
}
