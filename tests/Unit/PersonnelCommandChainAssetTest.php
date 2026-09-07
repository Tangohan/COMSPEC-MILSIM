<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PersonnelCommandChainAssetTest extends TestCase
{
    public function testEffectifsDeclaresCommandChainSurface(): void
    {
        $root = dirname(__DIR__, 2);
        $shell = (string) file_get_contents($root . '/views/admin/effectifs_workspace/shell.php');
        $view = (string) file_get_contents($root . '/views/admin/effectifs_workspace/chaine.php');
        $member = (string) file_get_contents($root . '/views/admin/effectifs_workspace/member.php');
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $controller = (string) file_get_contents($root . '/app/Controllers/Admin/EffectifsWorkspaceController.php');
        $catalog = (string) file_get_contents($root . '/app/Services/ConfigurationUpdate/ConfigurationUpdateCatalog.php');
        $seed = (string) file_get_contents($root . '/bootstrap/configuration_updates_migration.php');
        $css = (string) file_get_contents($root . '/public/assets/css/back-office-effectifs-workspace.css');
        $settings = (string) file_get_contents($root . '/views/admin/effectifs_workspace/rh_settings.php');

        self::assertStringContainsString("'chaine', 'Chaîne'", $shell);
        self::assertStringContainsString('effectifs/chaine', $routes);
        self::assertStringContainsString('function commandChain', $controller);
        self::assertStringContainsString('function saveCommandChain', $controller);
        self::assertStringContainsString('Chefs d’unité', $view);
        self::assertStringContainsString('Qui relève de qui', $view);
        self::assertStringContainsString('Relève de', $member);
        self::assertStringContainsString('PERSONNEL_COMMAND_CHAIN_V1', $catalog);
        self::assertStringContainsString('PERSONNEL_COMMAND_CHAIN_V1', $seed);
        self::assertStringContainsString('.bo-eff-workspace .eff-chain__select', $css);
        self::assertStringContainsString('Désigner les chefs d’unité', $settings);
        $search = (string) file_get_contents($root . '/app/Services/Portal/BackOfficeSearchService.php');
        self::assertStringContainsString('effectifs/chaine', $search);
        self::assertStringNotContainsString('endpoint', $view);
    }
}
