<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class OrganizationCatalogAssetTest extends TestCase
{
    public function testCatalogIsWiredThroughBoShellAndWizard(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $sidebar = (string) file_get_contents($root . '/views/partials/ath_sidebar_nav.php');
        $index = (string) file_get_contents($root . '/views/admin/organization/catalog/index.php');
        $preview = (string) file_get_contents($root . '/views/admin/organization/catalog/preview.php');
        $show = (string) file_get_contents($root . '/views/admin/organization/catalog/show.php');
        $history = (string) file_get_contents($root . '/views/admin/organization/catalog/history.php');
        $hub = (string) file_get_contents($root . '/views/admin/organization/effectifs_hub.php');
        $structure = (string) file_get_contents($root . '/views/admin/organization/structure_hub.php');
        $wizard = (string) file_get_contents($root . '/views/community/create.php');
        $setup = (string) file_get_contents($root . '/views/admin/organization/initial_setup.php');
        $perm = (string) file_get_contents($root . '/app/Authorization/TenantPermissionCatalog.php');
        $catalog = (string) file_get_contents($root . '/app/Services/ConfigurationUpdate/ConfigurationUpdateCatalog.php');
        $search = (string) file_get_contents($root . '/app/Services/Portal/BackOfficeSearchService.php');
        $ctrl = (string) file_get_contents($root . '/app/Controllers/Admin/Organization/OrganizationCatalogController.php');
        $svc = (string) file_get_contents($root . '/app/Services/OrganizationCatalog/OrganizationCatalogService.php');

        self::assertStringContainsString('/back-office/organisation/catalogue', $routes);
        self::assertStringContainsString('/back-office/organisation/catalogue/historique', $routes);
        self::assertStringContainsString('/back-office/organisation/catalogue/modele', $routes);
        self::assertStringContainsString('/back-office/organisation/catalogue/renommer', $routes);
        self::assertStringContainsString('/back-office/organisation/catalogue/actualiser', $routes);
        self::assertStringContainsString('/back-office/organisation/catalogue/retirer', $routes);
        self::assertStringContainsString('/back-office/organisation/catalogue/restaurer', $routes);
        self::assertStringContainsString('OrganizationCatalogController', $routes);
        self::assertStringContainsString('Catalogue de l’organisation', $sidebar);
        self::assertStringContainsString('Administrer cette organisation', $index);
        self::assertStringContainsString('Journal complet', $index);
        self::assertStringContainsString('Enregistrer un modèle de cette organisation', $index);
        self::assertStringContainsString('Appliquer à cette communauté', $preview);
        self::assertStringContainsString('inclure[organigramme]', $preview);
        self::assertStringContainsString('Seront ajoutées', $preview);
        self::assertStringContainsString('Administrer ce modèle', $show);
        self::assertStringContainsString('Actualiser depuis l’organisation actuelle', $show);
        self::assertStringContainsString('Historique des applications', $history);
        self::assertStringContainsString('Détail de cette application', $history);
        self::assertStringContainsString('Réappliquer ce modèle', $history);
        self::assertStringContainsString('org-catalog', $hub);
        self::assertStringContainsString('back-office/organisation/catalogue', $structure);
        self::assertStringContainsString('Démarrer avec un modèle', $wizard);
        self::assertStringContainsString('wizard_catalog_kit_code', $wizard);
        self::assertStringContainsString('Démarrer avec un modèle', $setup);
        self::assertStringContainsString('organization.catalog.manage', $perm);
        self::assertStringContainsString('ORGANIZATION_CATALOG_V1', $catalog);
        self::assertStringContainsString('Catalogue de l’organisation', $search);
        self::assertStringContainsString('Journal du catalogue', $search);
        self::assertStringContainsString('Gate::getInstance()', $ctrl);
        self::assertStringContainsString('createOrganizationRole', $svc);
        self::assertStringContainsString('installHistory', $svc);
        self::assertStringNotContainsString('Container::get(Gate::class)', $ctrl);
    }

    public function testUiAvoidsInternalJargon(): void
    {
        $root = dirname(__DIR__, 2);
        $files = [
            $root . '/views/admin/organization/catalog/index.php',
            $root . '/views/admin/organization/catalog/preview.php',
            $root . '/views/admin/organization/catalog/show.php',
            $root . '/views/admin/organization/catalog/history.php',
        ];
        foreach ($files as $file) {
            $body = strtolower((string) file_get_contents($file));
            foreach (['json', 'slug', 'sql', 'endpoint', 'schema', 'tenant_id'] as $banned) {
                self::assertStringNotContainsString($banned, $body, basename($file) . ' ' . $banned);
            }
        }
    }
}
