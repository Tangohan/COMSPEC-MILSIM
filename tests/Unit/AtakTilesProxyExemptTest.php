<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ComspecApiKeyAuth;
use PHPUnit\Framework\TestCase;

final class AtakTilesProxyExemptTest extends TestCase
{
    public function testTilesProxyIsExemptFromTacticalApiKey(): void
    {
        $root = dirname(__DIR__, 2);
        $cfgFile = (string) file_get_contents($root . '/config/tactical_api.php');
        self::assertStringContainsString("'/api/atak/tiles'", $cfgFile);
        self::assertFalse(ComspecApiKeyAuth::pathRequiresProtection('/api/atak/tiles'));
        self::assertTrue(ComspecApiKeyAuth::pathRequiresProtection('/api/atak/position'));
        self::assertFalse(ComspecApiKeyAuth::pathRequiresProtection('/api/atak/ping'));
    }

    public function testTacticalConfigSurvivesNonArrayRequire(): void
    {
        // pathRequiresProtection doit toujours renvoyer un bool sans TypeError.
        self::assertIsBool(ComspecApiKeyAuth::pathRequiresProtection('/api/atak/geo/places'));
        self::assertIsBool(ComspecApiKeyAuth::pathRequiresProtection('/api/system/version'));
    }
}
