<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class HeaderGradeOverrideAssetTest extends TestCase
{
    public function testHeaderUsesPersonnelRankOverride(): void
    {
        $header = (string) file_get_contents(dirname(__DIR__, 2) . '/views/partials/athena_caverne_header.php');
        $sidebar = (string) file_get_contents(dirname(__DIR__, 2) . '/views/partials/back_office_sidebar.php');
        $edit = (string) file_get_contents(dirname(__DIR__, 2) . '/views/personnel/edit.php');

        self::assertStringContainsString('headerShortCode', $header);
        self::assertStringContainsString('headerTitle', $header);
        self::assertStringContainsString('headerShortCode', $sidebar);
        // The override field moved into the ORBAT section partial included by the edit page.
        $orbat = (string) file_get_contents(dirname(__DIR__, 2) . '/views/partials/personnel/edit_orbat_section.php');
        self::assertStringContainsString("views/partials/personnel/edit_orbat_section.php", $edit);
        self::assertStringContainsString('name="rank_display_override"', $orbat);
        self::assertStringContainsString('code affiché à côté du grade', $orbat);
    }
}
