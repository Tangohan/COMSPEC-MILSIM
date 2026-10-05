<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\AdminAtakReportRoutingController;
use App\Support\AtakIcemanReportCatalog;
use PHPUnit\Framework\TestCase;

final class AtakNativeReportsAppTest extends TestCase
{
    public function testEveryTypeSentByTheAppIsKnownToTheSite(): void
    {
        $root = dirname(__DIR__, 2);
        $types = (string) file_get_contents($root . '/mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/reports/fn_reportTypes.sqf');
        preg_match_all('/^\s*\["[@A-Z_0-9]+", "[^"]+", "[^"]+", "([A-Z_0-9]*)"/m', $types, $m);

        self::assertGreaterThanOrEqual(12, count($m[1]));
        foreach (array_filter($m[1]) as $code) {
            self::assertTrue(AtakIcemanReportCatalog::isKnown($code), $code);
            self::assertArrayHasKey($code, AdminAtakReportRoutingController::REPORT_TYPES);
        }
    }

    public function testAppIsRegisteredAndWired(): void
    {
        $main = dirname(__DIR__, 2) . '/mod/COMSPEC_ATAK_Native/Sources/addons/main';
        $config = (string) file_get_contents($main . '/config.cpp');

        self::assertStringContainsString('name="Comptes rendus"; page="REPORTS"', $config);
        self::assertStringContainsString('class reportTypes {}; class reportAction {}; class pageReports {};', $config);
        self::assertFileExists($main . '/data/app_reports.paa');
        self::assertStringContainsString('case "REPORTS"', (string) file_get_contents($main . '/functions/ui/fn_pageRender.sqf'));
        self::assertStringContainsString('comspec_atak_native_report', (string) file_get_contents($main . '/XEH_postInitClient.sqf'));
        self::assertStringContainsString('comspec_atak_native_report', (string) file_get_contents($main . '/XEH_postInitServer.sqf'));
    }
}
