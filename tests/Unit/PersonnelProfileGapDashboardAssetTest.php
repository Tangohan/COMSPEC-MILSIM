<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PersonnelProfileGapDashboardAssetTest extends TestCase
{
    public function testOverviewShowsRapidProfileGapTable(): void
    {
        $ctrl = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Admin/Organization/OrganizationDashboardController.php');
        $dash = (string) file_get_contents(dirname(__DIR__, 2) . '/views/admin/organization/dashboard.php');
        // Widget « Dossiers à compléter » de l'accueil en widgets.
        $partial = (string) file_get_contents(dirname(__DIR__, 2) . '/views/admin/organization/_home_widget_body.php');
        $service = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/Personnel/PersonnelProfileGapScanService.php');

        self::assertStringContainsString('PersonnelProfileGapScanService', $ctrl);
        self::assertStringContainsString('orgProfileGaps', $ctrl);
        self::assertStringContainsString('listForTenant', $ctrl);
        self::assertStringContainsString('_home_widgets.php', $dash);
        self::assertStringContainsString('Dossiers à compléter', $partial);
        self::assertStringContainsString('image opérateur', $partial);
        self::assertStringContainsString('missing_function', $partial);
        self::assertStringContainsString('missing_rank', $partial);
        self::assertStringContainsString('missing_role', $partial);
        self::assertStringContainsString('missing_operator_image', $partial);
        self::assertStringNotContainsString('missing_absence', $partial);
        self::assertStringContainsString('character_portrait_path', $service);
        self::assertStringNotContainsString('personnel_absences', $service, 'une absence est déclarative');
        self::assertStringContainsString('personnel_profile_job_roles', $service);
    }
}
