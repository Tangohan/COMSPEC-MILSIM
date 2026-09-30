<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AdvancementCareerWiringAssetTest extends TestCase
{
    public function testCareerDecorationsAndReadinessAreWiredAlongsideMainAdvancement(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $container = (string) file_get_contents($root . '/app/Core/Container.php');
        $migrations = (string) file_get_contents($root . '/run-migrations.php');
        $cron = (string) file_get_contents($root . '/app/Services/Cron/CronSchedule.php');
        $nav = (string) file_get_contents($root . '/views/partials/ath_sidebar_nav.php');
        $catalog = (string) file_get_contents($root . '/app/Authorization/TenantPermissionCatalog.php');
        $bootstrap = (string) file_get_contents($root . '/app/Services/Community/TenantBootstrapService.php');
        $careerMigration = (string) file_get_contents($root . '/bootstrap/personnel_career_advancement_migration.php');

        self::assertStringContainsString('personnel_career_advancement_migration.php', $migrations);
        self::assertStringContainsString('run_personnel_career_advancement_migration', $migrations);
        self::assertStringContainsString('advancement_grade_migration.php', $migrations);
        self::assertStringContainsString("'advancement_seniority'", $cron);

        self::assertStringContainsString("'/back-office/organisation/grades'", $routes);
        self::assertStringContainsString("'/back-office/rh/avancement'", $routes);
        self::assertStringContainsString("'/back-office/referentiels/decorations'", $routes);
        self::assertStringContainsString("'/back-office/referentiels/dotation'", $routes);
        self::assertStringContainsString("'/back-office/organisation/disponibilite'", $routes);
        self::assertStringContainsString("'/api/avancement/grades'", $routes);
        self::assertStringContainsString("'/api/avancement/campagnes/{id}/publier'", $routes);
        self::assertStringNotContainsString("'/api/advancement/grades'", $routes);

        self::assertStringContainsString('award_definitions', $careerMigration);
        self::assertStringContainsString('personnel_awards', $careerMigration);
        self::assertStringContainsString('equipment_item_definitions', $careerMigration);
        self::assertStringContainsString('personnel_equipment_assignments', $careerMigration);
        self::assertStringNotContainsString('CREATE TABLE grade_definitions', $careerMigration);
        self::assertStringNotContainsString('CREATE TABLE advancement_campaigns', $careerMigration);

        self::assertStringContainsString('AdvancementEligibilityService', $container);
        self::assertStringContainsString('AdvancementSeniorityCronJob', $container);
        self::assertStringContainsString('GradeScaleTemplateService', $container);
        self::assertStringContainsString('CareerFileService', $container);
        self::assertStringContainsString('UnitReadinessService', $container);
        self::assertStringContainsString('AwardReferentielController', $container);
        self::assertStringContainsString('EquipmentReferentielController', $container);
        self::assertStringNotContainsString('GradeScaleSeedService', $container);
        self::assertStringNotContainsString('AdvancementGradeController', $container);

        self::assertStringContainsString('personnel.advancement.manage', $catalog);
        self::assertStringContainsString('personnel.awards.manage', $catalog);
        self::assertStringContainsString('personnel.equipment.manage', $catalog);
        self::assertStringContainsString('seedForNewTenant', $bootstrap);

        self::assertStringContainsString('back-office/rh/avancement', $nav);
        self::assertStringContainsString('back-office/organisation/grades', $nav);
        self::assertStringContainsString('back-office/referentiels/decorations', $nav);
        self::assertStringContainsString('back-office/referentiels/dotation', $nav);
        self::assertStringContainsString('back-office/ma-situation/carriere', $nav);

        self::assertFileExists($root . '/views/admin/advancement/grades_index.php');
        self::assertFileExists($root . '/views/admin/advancement/grade_form.php');
        self::assertFileExists($root . '/views/admin/advancement/campaigns_index.php');
        self::assertFileExists($root . '/views/admin/advancement/campaign_form.php');
        self::assertFileExists($root . '/views/admin/advancement/campaign_show.php');
        self::assertFileExists($root . '/views/admin/advancement/commission.php');
        self::assertFileDoesNotExist($root . '/views/admin/organization/advancement/grades_index.php');
        self::assertFileExists($root . '/views/admin/organization/advancement/awards_index.php');
        self::assertFileExists($root . '/views/admin/organization/advancement/equipment_index.php');
        self::assertFileExists($root . '/views/admin/organization/advancement/equipment_show.php');
        self::assertFileExists($root . '/views/admin/organization/advancement/readiness.php');
        self::assertFileExists($root . '/views/admin/member_situation/carriere.php');
        self::assertFileExists($root . '/views/admin/member_situation/decorations.php');
        self::assertFileExists($root . '/views/admin/member_situation/dotation.php');
        self::assertFileExists($root . '/bootstrap/personnel_career_advancement_migration.php');
        self::assertFileExists($root . '/bootstrap/advancement_grade_migration.php');
        self::assertFileExists($root . '/tests/Unit/AdvancementEligibilityServiceTest.php');
        self::assertFileDoesNotExist($root . '/app/Controllers/Admin/Organization/AdvancementGradeController.php');
        self::assertFileDoesNotExist($root . '/app/Repositories/PersonnelGradeHistoryRepository.php');
    }
}
