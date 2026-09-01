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
        $partial = (string) file_get_contents(dirname(__DIR__, 2) . '/views/partials/org_dashboard_profile_gaps.php');
        $service = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/Personnel/PersonnelProfileGapScanService.php');

        self::assertStringContainsString('PersonnelProfileGapScanService', $ctrl);
        self::assertStringContainsString('orgProfileGaps', $ctrl);
        self::assertStringContainsString('listForTenant', $ctrl);
        self::assertStringContainsString('org_dashboard_profile_gaps.php', $dash);
        self::assertStringContainsString('Profils à compléter', $partial);
        self::assertStringContainsString('Image opérateur', $partial);
        self::assertStringContainsString('Non indiquée', $partial);
        self::assertStringContainsString('missing_function', $partial);
        self::assertStringContainsString('missing_rank', $partial);
        self::assertStringContainsString('missing_role', $partial);
        self::assertStringContainsString('missing_operator_image', $partial);
        self::assertStringContainsString('missing_absence', $partial);
        self::assertStringContainsString('character_portrait_path', $service);
        self::assertStringContainsString('has_active_absence', $service);
        self::assertStringContainsString('personnel_profile_job_roles', $service);
    }
}
