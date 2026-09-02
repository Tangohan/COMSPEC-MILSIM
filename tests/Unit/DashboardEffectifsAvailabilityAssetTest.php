<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class DashboardEffectifsAvailabilityAssetTest extends TestCase
{
    public function testRapidTableShowsAvailabilityColumn(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/partials/dashboard_effectifs_table.php');
        $home = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Web/HomeController.php');
        $repo = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Repositories/CommunityEventRepository.php');

        self::assertStringContainsString('Disponibilité', $view);
        self::assertStringContainsString('90 j', $view);
        self::assertStringContainsString('dash-eff-avail', $view);
        self::assertStringContainsString('availability_90', $view);
        self::assertStringContainsString('Participations annoncées et présences validées sur 90 jours', $view);
        self::assertStringContainsString('availabilityCountsForUsers', $home);
        self::assertStringContainsString('MemberAvailabilityRate::fromCounts', $home);
        self::assertStringContainsString('availability_90', $home);
        self::assertStringContainsString('function availabilityCountsForUsers', $repo);
    }
}
