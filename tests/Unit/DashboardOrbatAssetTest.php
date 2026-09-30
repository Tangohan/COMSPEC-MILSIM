<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class DashboardOrbatAssetTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testDashboardOrbatIsWiredWithPortraitsAndExpandControls(): void
    {
        $root = $this->root();
        $support = (string) file_get_contents($root . '/app/Support/DashboardOrbatTree.php');
        $partial = (string) file_get_contents($root . '/views/partials/dashboard_orbat.php');
        $css = (string) file_get_contents($root . '/public/assets/css/dashboard-orbat.css');
        $home = (string) file_get_contents($root . '/app/Controllers/Web/HomeController.php');
        $command = (string) file_get_contents($root . '/views/partials/dashboard_command_center.php');
        $dash = (string) file_get_contents($root . '/views/dashboard.php');
        $canvas = (string) file_get_contents($root . '/views/partials/orbat/orbat_canvas.php');
        $showcase = (string) file_get_contents($root . '/views/community/show_showcase.php');
        $vitrineCss = (string) file_get_contents($root . '/public/assets/css/community-vitrine.css');

        self::assertStringContainsString('DashboardOrbatTree', $support);
        self::assertStringContainsString('batchPortraits', $support);
        self::assertStringContainsString('character_portrait_path', $support);
        self::assertStringContainsString('OrbatRosterPayload::buildForTenant', $support);
        self::assertStringContainsString('viewer_unit_ids', $support);
        self::assertStringContainsString('focus_unit_id', $support);
        self::assertStringContainsString('commander_vacant', $support);
        self::assertStringContainsString('is_mine', $support);
        self::assertStringContainsString('unitIdsForUser', $support);

        self::assertStringContainsString('dash-orbat', $partial);
        self::assertStringContainsString('Chaîne de commandement', $partial);
        self::assertStringContainsString('data-dash-orbat-expand', $partial);
        self::assertStringContainsString('data-dash-orbat-mine', $partial);
        self::assertStringContainsString('data-dash-orbat-search', $partial);
        self::assertStringContainsString('dash-orbat__avatar', $partial);
        self::assertStringContainsString('dash-orbat__chip--members', $partial);
        self::assertStringContainsString('dash-orbat__chip--subs', $partial);
        self::assertStringContainsString('dash-orbat__unit-icon', $partial);
        self::assertStringContainsString('Poste vacant', $partial);
        self::assertStringContainsString('Structure en attente', $partial);
        self::assertStringContainsString('icon_url', $support);
        self::assertStringContainsString('chartIconUrl', $support);
        self::assertStringContainsString('<details', $partial);

        self::assertStringContainsString('.dash-orbat__person--lead', $css);
        self::assertStringContainsString('.dash-orbat__chip--members', $css);
        self::assertStringContainsString('.dash-orbat__unit-mark--icon', $css);
        self::assertStringContainsString('.dash-orbat__btn--accent', $css);
        self::assertStringContainsString('.dash-orbat__node.is-mine', $css);
        self::assertStringContainsString('border-radius: 999px', $css);
        self::assertStringContainsString('max-width: none', $css);
        self::assertStringNotContainsString('purple', strtolower($css));

        self::assertStringContainsString('DashboardOrbatTree::buildForTenant', $home);
        self::assertStringContainsString("'dashboard_orbat'", $home);
        self::assertStringContainsString("'can_view_orbat'", $home);
        self::assertStringContainsString('dashboard_orbat.php', $command);
        self::assertStringContainsString('can_view_orbat', $command);
        self::assertStringContainsString('dashboard-orbat.css', $dash);

        self::assertStringContainsString('focusUnitFromQuery', $canvas);
        self::assertStringContainsString('params.get("unit")', $canvas);
        self::assertStringContainsString('orbat-node-card--focus', $canvas);
        self::assertStringContainsString('dataset.unitId', $canvas);

        self::assertStringContainsString('cl-units--orbat', $showcase);
        self::assertStringContainsString('publicUnitRoots', $showcase);
        self::assertStringContainsString('parent_id', $showcase);
        self::assertStringContainsString('.cl-units--orbat', $vitrineCss);
        self::assertStringContainsString('.cl-orbat-children', $vitrineCss);
    }
}
