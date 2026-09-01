<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class DashboardEffectifsSeniorityAssetTest extends TestCase
{
    public function testRapidTableShowsSeniorityColumn(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/partials/dashboard_effectifs_table.php');
        $home = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Web/HomeController.php');
        $summary = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/Personnel/SenioritySummaryService.php');

        self::assertStringContainsString('Ancienneté', $view);
        self::assertStringContainsString('seniority_label', $view);
        self::assertStringContainsString('dash-eff-seniority', $view);
        self::assertStringContainsString('Non renseignée', $view);
        self::assertStringContainsString('dashboardLabelsByUsers', $home);
        self::assertStringContainsString('seniority_label', $home);
        self::assertStringContainsString('tenure_community', $summary);
        self::assertStringContainsString('enlistment_date_resolved', $home);
    }
}
