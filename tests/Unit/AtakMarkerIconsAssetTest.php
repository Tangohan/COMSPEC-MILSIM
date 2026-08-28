<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Tactical\AtakMarkerIconsService;
use PHPUnit\Framework\TestCase;

final class AtakMarkerIconsAssetTest extends TestCase
{
    public function testAdminAndMapWireTenantIcons(): void
    {
        $root = dirname(__DIR__, 2);
        $admin = (string) file_get_contents($root . '/views/admin/atak-config/index.php');
        $map = (string) file_get_contents($root . '/public/assets/js/atak-map.js');
        $view = (string) file_get_contents($root . '/views/atak.php');
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $svc = (string) file_get_contents($root . '/app/Services/Tactical/AtakMarkerIconsService.php');

        self::assertStringContainsString('Apparence des symboles sur la carte', $admin);
        self::assertStringContainsString('marker-icons', $routes);
        self::assertStringContainsString('ATAK_TENANT_MARKER_ICONS', $view);
        self::assertStringContainsString('function tenantMarkerUrl(kind)', $map);
        self::assertStringContainsString('function customMarkerDivIcon', $map);
        self::assertStringContainsString('marker_icons_config', $svc);
        self::assertArrayHasKey('player', AtakMarkerIconsService::KINDS);
        self::assertArrayHasKey('ai_friend', AtakMarkerIconsService::KINDS);
        self::assertArrayHasKey('phone', AtakMarkerIconsService::KINDS);
        self::assertStringContainsString('atak-phone-icon.js', $view);
        self::assertStringContainsString('ATAKPhoneIcon', $map);
        self::assertStringContainsString('Géolocalisation téléphone', $svc);
    }
}
