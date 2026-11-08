<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Qualité des données : téléchargement et suppression des photos du jeu et des données SSE d'une communauté.
 * Test de câblage par lecture de fichier : droit dédié, CSRF, confirmation, journal d'audit, filtre par communauté.
 */
final class CommunityDataStorageWiringTest extends TestCase
{
    private function read(string $relative): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $relative);
    }

    public function testRoutesArePostOnlyAndProtected(): void
    {
        $routes = $this->read('routes/web.php');

        self::assertStringContainsString("\$router->post('/back-office/organisation/qualite-donnees/donnees/{key}/telecharger'", $routes);
        self::assertStringContainsString("\$router->post('/back-office/organisation/qualite-donnees/donnees/{key}/supprimer'", $routes);
        self::assertStringNotContainsString("\$router->get('/back-office/organisation/qualite-donnees/donnees", $routes);
    }

    public function testControllerChecksRightsCsrfConfirmationAndAudit(): void
    {
        $controller = $this->read('app/Controllers/Admin/Organization/OrganizationDataQualityController.php');

        self::assertStringContainsString("allows('organization.data.purge')", $controller);
        foreach (['downloadData', 'purgeData'] as $method) {
            self::assertMatchesRegularExpression('/function ' . $method . '\(.*?Csrf::validate/s', $controller);
        }
        self::assertStringContainsString("'confirm'", $controller);
        self::assertStringContainsString('community_data.purged', $controller);
        self::assertStringContainsString('community_data.downloaded', $controller);
        self::assertStringNotContainsString("'email'", $controller);
    }

    public function testPermissionIsCatalogedAndMigrated(): void
    {
        self::assertStringContainsString("'organization.data.purge'", $this->read('app/Authorization/TenantPermissionCatalog.php'));
        self::assertStringContainsString("'4030_organization_data_purge_permission.php'", $this->read('database/steps/registry.php'));
        self::assertFileExists(dirname(__DIR__, 2) . '/database/steps/4030_organization_data_purge_permission.php');
    }

    public function testEverySourceIsScopedToTheCommunity(): void
    {
        $catalog = $this->read('app/Support/PlatformStorageCatalog.php');
        $service = $this->read('app/Services/Organization/CommunityDataStorageService.php');

        self::assertStringContainsString("'tenant_sql' => 't.tenant_id = :t'", $catalog);
        self::assertStringContainsString('SELECT p.id FROM atak_poi p WHERE p.tenant_id = :t', $catalog);
        self::assertStringContainsString("'t' => \$tenantId", $service);
        self::assertStringContainsString('preg_match(\'#(^|/)\.\.(/|$)#\', $rel)', $service);
        self::assertStringContainsString('str_starts_with($real, $realRoot', $service);
    }

    public function testViewHasTabsModalAndNoEmail(): void
    {
        $view = $this->read('views/admin/organization/data_quality.php');
        $js = $this->read('public/assets/js/back-office-data-quality.js');

        self::assertStringContainsString('declare(strict_types=1);', substr($view, 0, 80));
        self::assertStringContainsString('id="dq-stockage"', $view);
        self::assertStringContainsString('data-dq-modal', $view);
        self::assertStringContainsString('name="_csrf_token"', $view);
        self::assertStringNotContainsString('email', strtolower($view));
        self::assertStringContainsString('showTab', $js);
        self::assertStringContainsString('showModal', $js);
    }
}
