<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\AtakPlanAccess;
use PHPUnit\Framework\TestCase;

final class AtakPlanAccessAssetTest extends TestCase
{
    public function testTacticalApiEnforcesAtakPlanGate(): void
    {
        $root = dirname(__DIR__, 2);
        $ctrl = (string) file_get_contents($root . '/app/Controllers/Api/AtakApiController.php');
        $helper = (string) file_get_contents($root . '/app/Support/AtakPlanAccess.php');
        $ping = (string) file_get_contents($root . '/app/Controllers/Api/AtakPingController.php');
        $iff = (string) file_get_contents($root . '/app/Controllers/Api/IffController.php');

        self::assertStringContainsString('AtakPlanAccess::allows($id)', $ctrl);
        self::assertStringContainsString('AtakPlanAccess::deniedJson($basic)', $ctrl);
        self::assertStringContainsString('AtakPlanAccess::isBasicPath($request->path())', $ctrl);
        self::assertStringContainsString("'atak'", $helper);
        self::assertStringContainsString('feature_unavailable', $helper);
        self::assertStringContainsString('La carte et les liaisons ne sont pas disponibles', $helper);
        self::assertStringNotContainsString('AtakPlanAccess', $ping);
        self::assertStringContainsString('AtakPlanAccess::allows($id)', $iff);
        self::assertStringContainsString('expectedPrefix', $iff);
        self::assertFileExists($root . '/docs/bugs/2026-09-13-atak-api-sans-garde-plan.md');
    }

    public function testDeniedJsonIsHumanReadable(): void
    {
        $response = AtakPlanAccess::deniedJson();
        self::assertSame(403, $response->statusCode());
        $body = $response->body();
        self::assertStringContainsString('feature_unavailable', $body);
        self::assertStringContainsString('La carte et les liaisons ne sont pas disponibles', $body);
        self::assertStringNotContainsString('FeatureGateService', $body);
        self::assertStringNotContainsString('allows', $body);
    }

    public function testDenialReportIsRecognised(): void
    {
        self::assertTrue(AtakPlanAccess::isDenialReport('ÉCHEC · PhotoUpload — http_403', 'ERR|http_403|a.png|feature_unavailable'));
        self::assertFalse(AtakPlanAccess::isDenialReport('ÉCHEC · PhotoUpload — http_403', 'ERR|http_403|a.png'));
        self::assertSame('', AtakPlanAccess::diagnosis(0));
    }

    public function testBasicLinkCoversPositionsReportsAndBriefingOnly(): void
    {
        foreach ([
            '/api/atak/position', '/api/atak/units', '/api/units/12', '/api/atak/reports/5/acknowledge',
            '/api/atak/salute', '/api/atak/briefing-plan', '/api/atak/briefing-slides/3/comments',
            '/api/atak/whoami', '/api/atak/game-session/heartbeat', '/api/atak/telemetry/batch',
            '/sous-dossier/api/atak/position?x=1', '/api/atak/screen-time/batch',
        ] as $path) {
            self::assertTrue(AtakPlanAccess::isBasicPath($path), $path);
        }
        foreach ([
            '/api/recon/images', '/api/intel/photos', '/api/atak/video-feeds', '/api/atak/markers',
            '/api/cas', '/api/atak/positionx', '/api/atak/unitsx', '/api/atak/zones', '/api/atak/explosive-timers',
            '/atak/position', '/api/atak/orders',
        ] as $path) {
            self::assertFalse(AtakPlanAccess::isBasicPath($path), $path);
        }
    }

    public function testDeniedJsonWithBasicLinkNamesTheHigherPlan(): void
    {
        $body = AtakPlanAccess::deniedJson(true)->body();
        self::assertStringContainsString('feature_unavailable', $body);
        self::assertStringContainsString('Standard', $body);
    }
}
