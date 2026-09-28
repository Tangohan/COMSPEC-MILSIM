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
        $cfg = require $root . '/config/tactical_api.php';

        self::assertContains('/api/atak/tiles', $cfg['atak_exempt_paths'] ?? []);
        self::assertFalse(ComspecApiKeyAuth::pathRequiresProtection('/api/atak/tiles', $cfg));
        self::assertTrue(ComspecApiKeyAuth::pathRequiresProtection('/api/atak/position', $cfg));
    }
}
