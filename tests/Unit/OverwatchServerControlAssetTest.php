<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Tactical\AtakExperienceService;
use PHPUnit\Framework\TestCase;

final class OverwatchServerControlAssetTest extends TestCase
{
    public function testCatalogAddsServerRulesWithoutChangingLegacyDefaults(): void
    {
        $svc = new AtakExperienceService();
        $ids = array_column($svc->catalog(), 'id');
        self::assertContains('atak_realism', $ids);
        self::assertContains('radio_proximity', $ids);
        self::assertContains('ace_menus', $ids);
        self::assertContains('show_independent', $ids);
        self::assertContains('athena_feed', $ids);

        $byId = [];
        foreach ($svc->catalog() as $row) {
            $byId[$row['id']] = $row;
        }
        self::assertSame('player', $byId['atak_realism']['default']);
        self::assertSame('player', $byId['radio_proximity']['default']);
        self::assertSame('experience', $byId['realism']['surface']);
        self::assertSame('control', $byId['atak_realism']['surface']);
        self::assertArrayHasKey('realisme', $svc->groupLabels());
    }

    public function testAdminPageAndRoutesExist(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root . '/views/admin/atak/server_control.php');
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $nav = (string) file_get_contents($root . '/views/partials/ath_sidebar_nav.php');
        $ctrl = (string) file_get_contents($root . '/app/Controllers/Admin/AdminOverwatchServerControlController.php');
        $catalog = (string) file_get_contents($root . '/app/Services/ConfigurationUpdate/ConfigurationUpdateCatalog.php');
        $seed = (string) file_get_contents($root . '/bootstrap/configuration_updates_migration.php');
        $apply = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_applyTenantExperience.sqf');
        $ext = (string) file_get_contents($root . '/mod/UptoDate/COMSPECExtension/Extension.cs');
        $cfg = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp');

        self::assertStringContainsString('Contrôle de mission', $view);
        self::assertStringContainsString('Exiger un relais pour la liaison de données', $view);
        self::assertStringContainsString('Fonctions actives', $view);
        self::assertStringNotContainsString('endpoint', strtolower($view));
        self::assertStringNotContainsString('json', strtolower($view));

        self::assertStringContainsString('/back-office/atak/controle-serveur', $routes);
        self::assertStringContainsString('storeRules', $routes);
        self::assertStringContainsString('Contrôle de mission', $nav);
        self::assertStringContainsString('listForTenant', $ctrl);
        self::assertStringContainsString('OVERWATCH_SERVER_CONTROL_V1', $catalog);
        self::assertStringContainsString('OVERWATCH_SERVER_CONTROL_V1', $seed);
        self::assertStringContainsString('back-office/atak/controle-serveur', $catalog);

        self::assertStringContainsString('comspec_overwatch_atak_realism', $apply);
        self::assertStringContainsString('comspec_sse_require_item', $apply);
        self::assertStringContainsString('athena_feed', $ext);
        self::assertStringContainsString('atak_realism', $ext);
        self::assertStringContainsString('1.5.84', $cfg);
        self::assertStringContainsString('2.0.45', $ext);
    }
}
