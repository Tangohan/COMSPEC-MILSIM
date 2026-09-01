<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class DashboardOrgAnomalyAssetTest extends TestCase
{
    public function testDashboardExposesOrgAnomalyTileAndForm(): void
    {
        $aside = (string) file_get_contents(dirname(__DIR__, 2) . '/views/partials/dashboard_aside.php');
        $form = (string) file_get_contents(dirname(__DIR__, 2) . '/views/partials/dashboard_org_anomaly_form.php');
        $cc = (string) file_get_contents(dirname(__DIR__, 2) . '/views/partials/dashboard_command_center.php');
        $js = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/dashboard-org-anomaly.js');
        $service = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/Community/CommunityReportService.php');
        $notify = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/Community/CommunityReportNotificationService.php');

        self::assertStringContainsString("org-anomaly", $aside);
        self::assertStringContainsString('Signaler une anomalie', $aside);
        self::assertStringContainsString('dashboard_org_anomaly_form.php', $aside);
        self::assertStringContainsString('help_subject', $form);
        self::assertStringContainsString('Transmettre à la gestion', $form);
        self::assertStringContainsString('id="signaler-anomalie"', $cc);
        self::assertStringContainsString('data-dash-rail-open-external="org-anomaly"', $cc);
        self::assertStringContainsString("target_type: 'org_anomaly'", $js);
        self::assertStringContainsString("'org_anomaly'", $service);
        self::assertStringContainsString('listEmailsForTenantAccessDelegation', $notify);
        self::assertStringContainsString('Anomalie transmise à la gestion', $notify);
        self::assertStringNotContainsString('sql_dump', $form);
    }
}
