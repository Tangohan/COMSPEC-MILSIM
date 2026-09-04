<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class BackOfficeSidebarPermissionFilterTest extends TestCase
{
    public function testPermissionHelpersAndSidebarFilterAreWired(): void
    {
        $helpers = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Support/helpers.php');
        $sidebar = (string) file_get_contents(dirname(__DIR__, 2) . '/views/partials/back_office_sidebar.php');
        $nav = (string) file_get_contents(dirname(__DIR__, 2) . '/views/partials/ath_sidebar_nav.php');

        self::assertStringContainsString('function back_office_nav_permission_rules', $helpers);
        self::assertStringContainsString('function back_office_nav_href_permission_allowed', $helpers);
        self::assertStringContainsString('back_office_nav_href_permission_allowed', $sidebar);
        self::assertStringContainsString('Masquer les entrées sans droit', $sidebar);
        self::assertStringContainsString('Parent sans droit mais enfants autorisés', $nav);
        self::assertStringContainsString('$parentOk = $boHrefAllowed', $nav);
    }

    public function testSidebarDoesNotOverwriteGroupPageViewData(): void
    {
        $sidebar = (string) file_get_contents(dirname(__DIR__, 2) . '/views/partials/back_office_sidebar.php');

        self::assertStringContainsString('foreach ($athNavGroups as $navGroup)', $sidebar);
        self::assertStringNotContainsString('foreach ($athNavGroups as $group)', $sidebar);
    }

    public function testRuleLookupPrefersLongestPathAndDefaultsBo(): void
    {
        require_once dirname(__DIR__, 2) . '/app/Support/helpers.php';
        if (is_file(dirname(__DIR__, 2) . '/app/Support/navigation_menu.php')) {
            require_once dirname(__DIR__, 2) . '/app/Support/navigation_menu.php';
        }

        $users = back_office_nav_rule_for_path('back-office/users');
        self::assertIsArray($users);
        self::assertNotEmpty($users['any_permissions'] ?? $users['permission'] ?? null);

        $corrections = back_office_nav_rule_for_path('back-office/personnel/corrections');
        self::assertIsArray($corrections);
        self::assertContains('personnel.profile.update', $corrections['any_permissions'] ?? []);

        $unknownBo = back_office_nav_rule_for_path('back-office/chemin-inconnu-xyz');
        self::assertIsArray($unknownBo);
        self::assertContains('admin.organization', $unknownBo['any_permissions'] ?? []);
    }
}
