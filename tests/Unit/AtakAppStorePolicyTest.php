<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Tactical\AtakExperienceService;
use PHPUnit\Framework\TestCase;

/**
 * App Store du téléphone ATAK natif : apps imposées ou interdites par la communauté (Contrôle serveur).
 */
final class AtakAppStorePolicyTest extends TestCase
{
    public function testPhoneAppsMirrorTheNativeConfigWithoutSystemApps(): void
    {
        $root = dirname(__DIR__, 2);
        $cfg = (string) file_get_contents($root . '/mod/COMSPEC_ATAK_Native/Sources/addons/main/config.cpp');
        $ids = array_column((new AtakExperienceService())->phoneApps(), 'id');
        self::assertNotEmpty($ids);
        self::assertSame(count($ids), count(array_unique($ids)));
        foreach ($ids as $id) {
            self::assertMatchesRegularExpression('/class ' . preg_quote($id, '/') . '\s+\{ name=/', $cfg, $id . ' absent de COMSPEC_ATAK_Apps');
        }
        foreach (['Settings', 'Store', 'Alerts', 'Admin', 'Toc'] as $fixed) {
            self::assertNotContains($fixed, $ids);
        }
        self::assertMatchesRegularExpression('/class Store\s+\{[^}]*system=1/', $cfg);
    }

    public function testSanitizeKeepsOnlyKnownAppsAndRules(): void
    {
        $svc = new AtakExperienceService();
        $policy = $svc->sanitizeAppPolicy([
            'Map' => 'required',
            'Dating' => 'forbidden',
            'Chat' => 'player',
            'Settings' => 'forbidden',
            'Unknown' => 'required',
            'Food' => 'nope',
        ]);
        self::assertSame(['Map' => 'required', 'Dating' => 'forbidden'], $policy);
        self::assertSame([], $svc->sanitizeAppPolicy('Map'));
        self::assertArrayHasKey('apps_policy', $svc->defaults());
    }

    public function testNativeAtakReadsTheRelayedKeys(): void
    {
        $root = dirname(__DIR__, 2);
        $svcSrc = (string) file_get_contents($root . '/app/Services/Tactical/AtakExperienceService.php');
        $sqf = (string) file_get_contents($root . '/mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/ui/fn_appInstall.sqf');
        $ext = (string) file_get_contents($root . '/mod/UptoDate/COMSPECExtension/Extension.cs');
        foreach (['net_apps_required', 'net_apps_forbidden'] as $key) {
            self::assertStringContainsString("'" . $key . "'", $svcSrc);
            self::assertStringContainsString('"' . $key . '"', $sqf);
        }
        // Clés relayées par COMSPEC Link sans nouvelle DLL (préfixe net_).
        self::assertStringContainsString('StartsWith("net_"', $ext);
    }

    public function testServerControlPageHasTheAppsSection(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root . '/views/admin/atak/server_control.php');
        $routes = (string) file_get_contents($root . '/routes/web.php');
        self::assertStringContainsString('id="apps"', $view);
        self::assertStringContainsString("controle-serveur/apps'", $view);
        self::assertStringContainsString("'/back-office/atak/controle-serveur/apps'", $routes);
    }
}
