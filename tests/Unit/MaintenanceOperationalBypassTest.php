<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\MaintenanceGuard;
use PHPUnit\Framework\TestCase;

final class MaintenanceOperationalBypassTest extends TestCase
{
    public function testArmaAndAtakWebStayOpen(): void
    {
        foreach ([
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
            self::assertTrue(MaintenanceGuard::isOperationalPath($path), $path);
        }
    }

    public function testPortalAndSseStayClosed(): void
    {
        foreach ([
            '/',
            '/admin',
            '/admin/effectifs',
            '/admin/atak-config',
            '/atak/sse',
            '/atak/sse/dossiers',
            '/forum',
            '/account',
            '/register',
        ] as $path) {
            self::assertFalse(MaintenanceGuard::isOperationalPath($path), $path);
        }
    }
}
