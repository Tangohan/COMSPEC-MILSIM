<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class BackOfficeCooperationNavigationAssetTest extends TestCase
{
    public function testAllCooperationToolsAreExposedInBackOfficeNavigation(): void
    {
        $root = dirname(__DIR__, 2);
        $sidebar = (string) file_get_contents($root . '/views/partials/back_office_sidebar.php');
        $nav = (string) file_get_contents($root . '/views/partials/ath_sidebar_nav.php');

        self::assertStringContainsString("str_starts_with(\$p, 'back-office/cooperation/')", $sidebar);
        self::assertStringContainsString("'label' => 'Coopérations inter-unités'", $nav);
        self::assertStringContainsString("'label' => 'Toutes les coopérations'", $nav);
        self::assertStringContainsString("'label' => 'Nouvelle coopération'", $nav);
        self::assertStringContainsString("'label' => 'Types & modèles'", $nav);
        self::assertStringContainsString("'label' => 'Messages d’annonce'", $nav);
    }

    public function testCooperationNavigationUsesFeatureSpecificPermissions(): void
    {
        require_once dirname(__DIR__, 2) . '/app/Support/helpers.php';

        $missions = back_office_nav_rule_for_path('back-office/cooperation/missions');
        self::assertContains('cooperation.missions.view', $missions['any_permissions'] ?? []);
        self::assertContains('cooperation.missions.create', $missions['any_permissions'] ?? []);

        $create = back_office_nav_rule_for_path('back-office/cooperation/missions/create');
        self::assertContains('cooperation.missions.create', $create['any_permissions'] ?? []);
        self::assertNotContains('cooperation.missions.view', $create['any_permissions'] ?? []);

        $catalog = back_office_nav_rule_for_path('back-office/cooperation/catalog');
        self::assertContains('cooperation.catalog.manage', $catalog['any_permissions'] ?? []);

        $announcements = back_office_nav_rule_for_path('back-office/cooperation/announcements');
        self::assertContains('cooperation.announcements.manage', $announcements['any_permissions'] ?? []);
    }
}
