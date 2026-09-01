<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\OperationLabels;
use App\Support\TacticalGraphicsCatalog;
use PHPUnit\Framework\TestCase;

final class OperationsWorkspaceAssetTest extends TestCase
{
    public function testWorkspaceIsWiredAsCanonicalOperationEntity(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $nav = (string) file_get_contents($root . '/config/navigation.php');
        $catalog = (string) file_get_contents($root . '/app/Services/ConfigurationUpdate/ConfigurationUpdateCatalog.php');
        $dispatch = (string) file_get_contents($root . '/app/Support/DevDispatchCatalog.php');
        $perms = (string) file_get_contents($root . '/app/Authorization/TenantPermissionCatalog.php');
        $index = (string) file_get_contents($root . '/views/operations/workspace/index.php');
        $show = (string) file_get_contents($root . '/views/operations/workspace/show.php');

        self::assertFileExists($root . '/bootstrap/operations_workspace_migration.php');
        self::assertStringContainsString('$router->get(\'/operations\'', $routes);
        self::assertStringContainsString('/api/game/v1/operations/{uuid}/tactical', $routes);
        self::assertStringContainsString("'path' => 'operations'", $nav);
        self::assertStringContainsString('OPERATIONS_WORKSPACE_V1', $catalog);
        self::assertStringContainsString('L’opération devient le dossier de mission', $dispatch);
        self::assertStringContainsString('operations.overlay.publish', $perms);
        self::assertStringContainsString('Espaces opérationnels', $index);
        self::assertStringContainsString('Publier sur la vue terrain', $show);
        self::assertStringNotContainsString('endpoint', $index);
        self::assertStringNotContainsString('JSON', $show);
    }

    public function testIndexMatchesLightPortalAndHasAProperEmptyState(): void
    {
        $root = dirname(__DIR__, 2);
        $index = (string) file_get_contents($root . '/views/operations/workspace/index.php');
        $css = (string) file_get_contents($root . '/public/assets/css/ops-workspace.css');
        $ctrl = (string) file_get_contents($root . '/app/Controllers/Web/OperationWorkspaceController.php');
        $dispatch = (string) file_get_contents($root . '/app/Support/DevDispatchCatalog.php');

        self::assertStringContainsString('ops-ws--index', $index);
        self::assertStringContainsString('ops-ws__empty-card', $index);
        self::assertStringContainsString('Aucune opération n’est ouverte pour le moment', $index);
        self::assertStringContainsString('name="indicatif"', $index);
        self::assertStringContainsString('Pas le nom de la communauté', $index);
        self::assertStringContainsString('placeholder="AEGIS"', $index);
        self::assertStringContainsString('bo-select', $index);
        self::assertStringNotContainsString('name="code"', $index);
        self::assertStringNotContainsString('slug', strtolower($index));

        self::assertStringContainsString('--ops-bg: #f8fafc', $css);
        self::assertStringContainsString('.ops-ws__empty-card', $css);
        self::assertStringContainsString('.ops-ws__op', $css);
        self::assertStringNotContainsString('--ops-bg: #070b12', $css);

        self::assertStringContainsString("\$request->input('indicatif')", $ctrl);
        self::assertStringContainsString('Les espaces opérationnels se lisent comme le reste du portail', $dispatch);
        self::assertSame('AEGIS', OperationLabels::suggestCode('Opération Aegis'));
        self::assertSame('AEGIS', OperationLabels::suggestCode('Aegis'));
        self::assertSame('OPS', OperationLabels::suggestCode('Opération'));
    }

    public function testGraphicsCatalogHasSemanticManeuverObjects(): void
    {
        $axis = TacticalGraphicsCatalog::find('axis');
        $obj = TacticalGraphicsCatalog::find('objective');
        $pl = TacticalGraphicsCatalog::find('phase_line');
        self::assertNotNull($axis);
        self::assertSame('Axe', $axis['label']);
        self::assertNotNull($obj);
        self::assertSame('Objectif', $obj['label']);
        self::assertNotNull($pl);
        self::assertSame('Ligne de phase', $pl['label']);
        self::assertNotEmpty(TacticalGraphicsCatalog::groups()['fire_support']['items']);
    }

    public function testPublicDispatchAvoidsWorkshopJargon(): void
    {
        $root = dirname(__DIR__, 2);
        $dispatch = (string) file_get_contents($root . '/app/Support/DevDispatchCatalog.php');
        $chunk = '';
        if (preg_match('/\$pr\(264,.*?\$pr\(263,/s', $dispatch, $m)) {
            $chunk = strtolower($m[0]);
        }
        self::assertNotSame('', $chunk);
        self::assertStringNotContainsString('sqf', $chunk);
        self::assertStringNotContainsString('endpoint', $chunk);
        self::assertStringNotContainsString('json', $chunk);
        self::assertStringNotContainsString('pbo', $chunk);
    }
}
