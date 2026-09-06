<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\MaintenanceGuard;
use PHPUnit\Framework\TestCase;

final class MaintenanceOperationalBypassTest extends TestCase
{
    public function testEntirePublicAndAuthenticatedSiteIsCoveredByMaintenance(): void
    {
        foreach ([
            '/',
            '/forum',
            '/account',
            '/register',
            '/atak',
            '/atak/mobile',
            '/atak/connect',
            '/connect',
            '/connect/ABC123/carte',
            '/login',
            '/login/otp',
            '/tacmap',
            '/map-data/altis/10/12/13.png',
            '/api/atak/position',
            '/api/atak/units',
            '/api/units',
            '/api/chat',
            '/api/map-shapes',
        ] as $path) {
            self::assertFalse(MaintenanceGuard::isInfrastructurePath($path), $path);
        }
    }

    public function testOnlyInfrastructureEndpointsBypassMaintenance(): void
    {
        foreach ([
            '/api/stripe/webhook',
            '/api/health',
            '/api/system/version',
            '/cron/run',
            '/cron/nightly',
            '/maintenance-toggle.php',
            '/admin/system/updates',
            '/admin/system/updates/install',
            '/assets/app.css',
            '/uploads/logo.png',
            '/sw.js',
            '/manifest.webmanifest',
        ] as $path) {
            self::assertTrue(MaintenanceGuard::isInfrastructurePath($path), $path);
        }
    }
}
