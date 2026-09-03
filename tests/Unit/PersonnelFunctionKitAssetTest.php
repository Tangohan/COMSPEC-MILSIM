<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PersonnelFunctionKitAssetTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testRoutesViewsAndMigrationExist(): void
    {
        $root = $this->root();
        $routes = (string) file_get_contents($root . '/routes/web.php');
        self::assertStringContainsString('/back-office/personnel-job-roles/kits', $routes);
        self::assertStringContainsString('/back-office/personnel-job-roles/kits/save', $routes);
        self::assertStringContainsString('/back-office/personnel-job-roles/kits/assign', $routes);
        self::assertFileExists($root . '/views/admin/organization/personnel_job_roles/kits.php');
        self::assertFileExists($root . '/public/assets/js/bo-kits-selection.js');
        self::assertFileExists($root . '/app/Services/Personnel/PersonnelFunctionKitCatalog.php');
        self::assertFileExists($root . '/app/Services/Personnel/PersonnelFunctionKitService.php');
        self::assertFileExists($root . '/app/Repositories/TenantFunctionKitRepository.php');
        self::assertFileExists($root . '/migrations/20260902000001_personnel_function_kits.sql');
        self::assertFileExists($root . '/bootstrap/personnel_function_kits_migration.php');
        $sql = (string) file_get_contents($root . '/migrations/20260902000001_personnel_function_kits.sql');
        self::assertStringContainsString('tenant_function_kit_state', $sql);
        $run = (string) file_get_contents($root . '/run-migrations.php');
        self::assertStringContainsString('run_personnel_function_kits_migration', $run);
    }

    public function testConfigurationUpdateIsDeclared(): void
    {
        $root = $this->root();
        $catalog = (string) file_get_contents($root . '/app/Services/ConfigurationUpdate/ConfigurationUpdateCatalog.php');
        $probe = (string) file_get_contents($root . '/app/Services/ConfigurationUpdate/ConfigurationUpdateProbes.php');
        $seed = (string) file_get_contents($root . '/bootstrap/configuration_updates_migration.php');
        $boot = (string) file_get_contents($root . '/app/Services/Community/TenantBootstrapService.php');
        self::assertStringContainsString('FUNCTION_KITS_V1', $catalog);
        self::assertStringContainsString('back-office/personnel-job-roles/kits', $catalog);
        self::assertStringContainsString('Kits d’accès', $catalog);
        self::assertStringContainsString('hasFunctionKitsReviewed', $probe);
        self::assertStringContainsString('FUNCTION_KITS_V1', $seed);
        self::assertStringContainsString('markReviewedKeepingFullCatalog', $boot);
    }

    public function testUiUsesAccessKitsAndClosedChoices(): void
    {
        $root = $this->root();
        $view = (string) file_get_contents($root . '/views/admin/organization/personnel_job_roles/kits.php');
        $css = (string) file_get_contents($root . '/public/assets/css/back-office-catalog.css');
        $js = (string) file_get_contents($root . '/public/assets/js/bo-kits-selection.js');
        self::assertStringContainsString('Qui peut faire quoi', $view);
        self::assertStringContainsString('Attribuer les kits', $view);
        self::assertStringContainsString('Enregistrer les kits', $view);
        self::assertStringContainsString('type="checkbox"', $view);
        self::assertStringContainsString('data-bo-kits-hint', $view);
        self::assertStringContainsString('bo-kits__card-state-on', $view);
        self::assertStringContainsString('Sélectionné', $view);
        self::assertStringContainsString('bo-kits-selection.js', $view);
        self::assertStringContainsString('Qui l’obtient ?', $view);
        self::assertStringContainsString('multi-sélection', $view);
        self::assertStringContainsString('kit_id', $view);
        self::assertStringContainsString('Paramètres de la communauté', file_get_contents($root . '/app/Services/Personnel/PersonnelFunctionKitCatalog.php'));
        self::assertStringContainsString('Lecture et modification', file_get_contents($root . '/app/Services/Personnel/PersonnelFunctionKitCatalog.php'));
        self::assertStringContainsString('Recruter', file_get_contents($root . '/app/Services/Personnel/PersonnelFunctionKitCatalog.php'));
        $body = strtolower($view);
        foreach (['json', 'sql', 'endpoint', 'schema', 'tenant_id', 'infanterie', 'slug'] as $banned) {
            self::assertStringNotContainsString($banned, $body, $banned);
        }
        self::assertStringNotContainsString('.bo-kits__card input {' . "\n" . '  position: absolute;', $css);
        self::assertStringContainsString('appearance: auto', $css);
        self::assertStringContainsString('opacity: 1', $css);
        self::assertStringContainsString('data-bo-kits-hint', $js);
        self::assertStringContainsString('Aucun kit coché pour l’instant.', $js);
        self::assertStringContainsString('sélectionné', $js);
    }

    public function testPortalSurfacesTemporarilyHideKits(): void
    {
        $root = $this->root();
        $sidebar = (string) file_get_contents($root . '/views/partials/ath_sidebar_nav.php');
        $search = (string) file_get_contents($root . '/app/Services/Portal/BackOfficeSearchService.php');
        $updates = (string) file_get_contents($root . '/app/Services/ConfigurationUpdate/ConfigurationUpdateCatalog.php');
        $pages = (string) file_get_contents($root . '/config/back_office_pages.php');
        $fonctions = (string) file_get_contents($root . '/views/admin/effectifs_workspace/fonctions.php');
        self::assertStringNotContainsString('back-office/personnel-job-roles/kits', $sidebar);
        self::assertStringNotContainsString('back-office/personnel-job-roles/kits', $search);
        self::assertStringContainsString('FUNCTION_KITS_V1', $updates);
        self::assertStringContainsString('isApplicable: static fn (int $tenantId): bool => false', $updates);
        self::assertSame(1, substr_count($pages, "'path' => 'back-office/personnel-job-roles/kits'"));
        self::assertSame(1, substr_count($pages, 'back-office/personnel-job-roles/kits'));
        self::assertStringNotContainsString('back-office/personnel-job-roles/kits', $fonctions);
    }
}
