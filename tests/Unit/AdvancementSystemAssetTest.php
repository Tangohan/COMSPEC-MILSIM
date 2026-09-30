<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AdvancementSystemAssetTest extends TestCase
{
    public function testAvancementEstBrancheAuxMigrationsAuxRoutesEtALaCreationDeCommunaute(): void
    {
        $root = dirname(__DIR__, 2);
        $migration = (string) file_get_contents($root . '/bootstrap/advancement_grade_migration.php');
        $runner = (string) file_get_contents($root . '/run-migrations.php');
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $container = (string) file_get_contents($root . '/app/Core/Container.php');
        $bootstrap = (string) file_get_contents($root . '/app/Services/Community/TenantBootstrapService.php');
        $nav = (string) file_get_contents($root . '/views/partials/ath_sidebar_nav.php');
        $qualifications = (string) file_get_contents($root . '/views/admin/member_situation/qualifications.php');

        self::assertStringContainsString('grade_definitions', $migration);
        self::assertStringContainsString('personnel_grade_history', $migration);
        self::assertStringContainsString('advancement_campaigns', $migration);
        self::assertStringContainsString('advancement_candidacies', $migration);
        self::assertStringContainsString('advancement_commissions', $migration);
        self::assertStringContainsString('function run_advancement_grade_migration', $migration);
        self::assertStringContainsString('run_advancement_grade_migration($pdo)', $runner);

        self::assertStringContainsString("'/back-office/organisation/grades'", $routes);
        self::assertStringContainsString("'/back-office/rh/avancement'", $routes);
        self::assertStringContainsString("'/api/avancement/campagnes/{id}/publier'", $routes);
        self::assertStringContainsString("'/back-office/ma-situation/avancement'", $routes);
        self::assertStringContainsString('AdvancementEligibilityService::class', $container);
        self::assertStringContainsString('AdvancementSeniorityCronJob::class', $container);
        self::assertStringContainsString('seedForNewTenant', $bootstrap);
        self::assertStringContainsString('ensureForTenant', (string) file_get_contents($root . '/app/Services/Advancement/GradeScaleTemplateService.php'));
        self::assertStringContainsString('completeForTenant', (string) file_get_contents($root . '/app/Controllers/Admin/Organization/AdvancementAdminController.php'));
        self::assertStringContainsString('ensureForTenant', (string) file_get_contents($root . '/app/Controllers/Admin/Organization/AdvancementAdminController.php'));
        self::assertStringContainsString('ensureForAllTenants', $runner);

        self::assertStringContainsString('Mon avancement', $nav);
        self::assertStringContainsString('MA SITUATION', $nav);
        self::assertStringContainsString("'label' => 'Grades'", $nav);
        self::assertStringContainsString('bo-qual-card', $qualifications);
        self::assertStringContainsString('Générer le brevet', $qualifications);
        self::assertStringContainsString('Expire dans', $qualifications);
    }
}
