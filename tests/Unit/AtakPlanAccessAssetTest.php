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
        self::assertStringContainsString('AtakPlanAccess::deniedJson()', $ctrl);
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
}
