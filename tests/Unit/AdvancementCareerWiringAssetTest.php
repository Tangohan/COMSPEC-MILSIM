<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AdvancementCareerWiringAssetTest extends TestCase
{
    public function testAdvancementRoutesViewsAndContainerAreWired(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $container = (string) file_get_contents($root . '/app/Core/Container.php');
        $migrations = (string) file_get_contents($root . '/run-migrations.php');
        $cron = (string) file_get_contents($root . '/app/Services/Cron/CronSchedule.php');
        $nav = (string) file_get_contents($root . '/views/partials/ath_sidebar_nav.php');
        $catalog = (string) file_get_contents($root . '/app/Authorization/TenantPermissionCatalog.php');
        $bootstrap = (string) file_get_contents($root . '/app/Services/Community/TenantBootstrapService.php');

        self::assertStringContainsString('personnel_career_advancement_migration.php', $migrations);
        self::assertStringContainsString('run_personnel_career_advancement_migration', $migrations);
        self::assertStringContainsString("'advancement_seniority'", $cron);

        self::assertStringContainsString("'/back-office/organisation/grades'", $routes);
        self::assertStringContainsString("'/back-office/rh/avancement'", $routes);
        self::assertStringContainsString("'/back-office/referentiels/decorations'", $routes);
        self::assertStringContainsString("'/back-office/referentiels/dotation'", $routes);
        self::assertStringContainsString("'/back-office/organisation/disponibilite'", $routes);
        self::assertStringContainsString("'/api/advancement/grades'", $routes);
        self::assertStringContainsString("'/api/advancement/campaigns/{id}/publish'", $routes);

        self::assertStringContainsString('AdvancementEligibilityService', $container);
        self::assertStringContainsString('AdvancementSeniorityCronJob', $container);
        self::assertStringContainsString('GradeScaleSeedService', $container);
        self::assertStringContainsString('CareerFileService', $container);
        self::assertStringContainsString('UnitReadinessService', $container);

        self::assertStringContainsString('personnel.advancement.manage', $catalog);
        self::assertStringContainsString('personnel.awards.manage', $catalog);
        self::assertStringContainsString('personnel.equipment.manage', $catalog);
        self::assertStringContainsString('seedForTenant', $bootstrap);

        self::assertStringContainsString('back-office/rh/avancement', $nav);
        self::assertStringContainsString('back-office/organisation/grades', $nav);

        self::assertFileExists($root . '/views/admin/organization/advancement/grades_index.php');
        self::assertFileExists($root . '/views/admin/organization/advancement/grade_form.php');
        self::assertFileExists($root . '/views/admin/organization/advancement/campaigns_index.php');
        self::assertFileExists($root . '/views/admin/organization/advancement/campaign_form.php');
        self::assertFileExists($root . '/views/admin/organization/advancement/campaign_show.php');
        self::assertFileExists($root . '/views/admin/organization/advancement/commission.php');
        self::assertFileExists($root . '/views/admin/organization/advancement/awards_index.php');
        self::assertFileExists($root . '/views/admin/organization/advancement/equipment_index.php');
        self::assertFileExists($root . '/views/admin/organization/advancement/equipment_show.php');
        self::assertFileExists($root . '/views/admin/organization/advancement/readiness.php');
        self::assertFileExists($root . '/views/admin/member_situation/carriere.php');
        self::assertFileExists($root . '/views/admin/member_situation/decorations.php');
        self::assertFileExists($root . '/views/admin/member_situation/dotation.php');
        self::assertFileExists($root . '/bootstrap/personnel_career_advancement_migration.php');
        self::assertFileExists($root . '/tests/Unit/AdvancementEligibilityServiceTest.php');
    }
}
