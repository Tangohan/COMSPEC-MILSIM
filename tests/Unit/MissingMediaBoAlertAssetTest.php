<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class MissingMediaBoAlertAssetTest extends TestCase
{
    public function testBackOfficeSurfacesMissingMediaAfterMigration(): void
    {
        $scanner = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/Media/MissingUserMediaScanner.php');
        $ops = (string) file_get_contents(dirname(__DIR__, 2) . '/views/admin/organization/operations_center.php');
        $dashCtrl = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Admin/Organization/OrganizationDashboardController.php');
        $dashView = (string) file_get_contents(dirname(__DIR__, 2) . '/views/admin/organization/dashboard.php');
        $memberAlerts = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/Alerts/AccountProfileAlertsBuilder.php');

        self::assertStringContainsString('isBrokenLocalPath', $scanner);
        self::assertStringContainsString('uploads/avatars/', $scanner);
        self::assertStringContainsString('anomalies-medias', $ops);
        self::assertStringContainsString('Photos / portraits à re-téléverser', $ops);
        self::assertStringContainsString('MissingUserMediaScanner', $dashCtrl);
        self::assertStringContainsString('missingMediaCount', $dashCtrl);
        self::assertStringContainsString('Photos après migration', $dashView);
        self::assertStringContainsString('Photo à re-téléverser', $memberAlerts);
    }
}
