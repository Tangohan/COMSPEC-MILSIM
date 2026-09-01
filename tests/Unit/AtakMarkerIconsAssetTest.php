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

    public function testSettingsPanelLinksToCommunityIconLibrary(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root . '/views/atak.php');
        $css = (string) file_get_contents($root . '/public/assets/css/atak-c2-shell.css');
        $controller = (string) file_get_contents($root . '/app/Controllers/Web/AtakController.php');
        $geo = (string) file_get_contents($root . '/public/assets/js/atak-geo-live.js');
        $premium = (string) file_get_contents($root . '/public/assets/js/atak-terrain3d-premium.js');

        self::assertStringContainsString('id="atak-settings-icons"', $view);
        self::assertStringContainsString('Icônes de la communauté', $view);
        self::assertStringContainsString('Choisir ou ajouter des icônes', $view);
        self::assertStringContainsString("url('admin/atak-config') . '#marker-icons'", $view);
        self::assertStringContainsString('Déjà dans la bibliothèque', $view);
        self::assertStringContainsString('.atak-settings-icons__cta', $css);
        self::assertStringContainsString("can('admin.organization')", $controller);
        self::assertStringContainsString('Villes et lieux', $geo);
        self::assertStringContainsString('>Routes</span>', $geo);
        self::assertStringNotContainsString('geo_places', $geo);
        self::assertStringNotContainsString('geo_roads', $geo);
        self::assertStringNotContainsString('réseau geo', $geo);
        self::assertStringContainsString('Vue de la carte en relief', $view);
        self::assertStringContainsString('Vue en relief : le sol se soulève', $premium);
        self::assertStringNotContainsString('relief Three.js', $view);
        self::assertStringNotContainsString('relief Three.js', $premium);
        self::assertStringNotContainsString('mesh Three.js', $premium);
        self::assertStringContainsString("querySelector('#atak-terrain-3d-settings > .atak-terrain-3d-hint')", $premium);
    }
}
