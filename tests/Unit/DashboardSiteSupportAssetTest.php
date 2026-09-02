<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class DashboardSiteSupportAssetTest extends TestCase
{
    public function testDashboardExposesSiteSupportTileAndFormForOrganizers(): void
    {
        $aside = (string) file_get_contents(dirname(__DIR__, 2) . '/views/partials/dashboard_aside.php');
        $form = (string) file_get_contents(dirname(__DIR__, 2) . '/views/partials/dashboard_site_support_form.php');
        $cc = (string) file_get_contents(dirname(__DIR__, 2) . '/views/partials/dashboard_command_center.php');
        $js = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/dashboard-site-support.js');
        $service = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/Community/CommunityReportService.php');
        $notify = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/Community/CommunityReportNotificationService.php');
        $controller = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Api/CommunityReportController.php');

        self::assertStringContainsString("site-support", $aside);
        self::assertStringContainsString('Administration du site', $aside);
        self::assertStringContainsString('dashboard_site_support_form.php', $aside);
        self::assertStringContainsString('$canAdmin', $aside);
        self::assertStringContainsString('compte_fantome', $form);
        self::assertStringContainsString('Transmettre à l’administration du site', $form);
        self::assertStringContainsString('id="contacter-admin-site"', $cc);
        self::assertStringContainsString('data-dash-rail-open-external="site-support"', $cc);
        self::assertStringContainsString("target_type: 'site_support_request'", $js);
        self::assertStringContainsString("'site_support_request'", $service);
        self::assertStringContainsString('site_support_request', $notify);
        self::assertStringContainsString('Demande organisateur → administration site', $notify);
        self::assertStringContainsString('site_support_request', $controller);
        self::assertStringContainsString('admin.organization', $controller);
    }
}
