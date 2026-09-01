<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class DashboardRhParcoursAssetTest extends TestCase
{
    public function testDashboardRhIsABottomSlideParcours(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root . '/views/partials/dashboard_rh_parcours.php');
        $cc = (string) file_get_contents($root . '/views/partials/dashboard_command_center.php');
        $home = (string) file_get_contents($root . '/app/Controllers/Web/HomeController.php');
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $ctrl = (string) file_get_contents($root . '/app/Controllers/Web/RhWorkspaceController.php');
        $css = (string) file_get_contents($root . '/public/assets/css/dashboard-impact.css');

        self::assertStringContainsString("require base_path('views/partials/dashboard_rh_parcours.php')", $cc);
        self::assertStringNotContainsString('dashboard_member_rh.php', $cc);
        self::assertStringNotContainsString("['show_offers'] = false", $cc);
        $requirePos = strpos($cc, "dashboard_rh_parcours.php");
        $channelsPos = strpos($cc, 'Mes salons suivis');
        self::assertNotFalse($requirePos);
        self::assertNotFalse($channelsPos);
        self::assertGreaterThan($channelsPos, $requirePos);

        self::assertStringContainsString('data-rh-go="absence"', $view);
        self::assertStringContainsString('data-rh-go="elevation"', $view);
        self::assertStringContainsString('data-rh-go="avancement"', $view);
        self::assertStringContainsString('Absence', $view);
        self::assertStringContainsString('Élévation', $view);
        self::assertStringContainsString('Avancement', $view);
        self::assertStringContainsString('Étape 1 sur 2', $view);
        self::assertStringContainsString('dash-rh-parcours__choice', $view);
        self::assertStringNotContainsString('unpkg.com/alpinejs', $home);
        $dash = (string) file_get_contents($root . '/views/dashboard.php');
        self::assertStringNotContainsString('unpkg.com/alpinejs', $dash);
        self::assertStringContainsString('assets/vendor/alpinejs/alpine.min.js', $dash);
        self::assertStringContainsString('return_to" value="dashboard"', $view);
        self::assertStringContainsString('personnel/mon-espace-rh/elevation', $view);
        self::assertStringContainsString('personnel/mon-espace-rh/absences', $view);
        self::assertStringContainsString('personnel/mon-espace-rh/mobilite', $view);
        self::assertStringNotContainsString('slug', strtolower($view));
        self::assertStringNotContainsString('endpoint', strtolower($view));

        self::assertStringContainsString('DashboardRhParcours::build', $home);
        self::assertStringContainsString('requestSelfElevation', $routes);
        self::assertStringContainsString('function storeElevation', $ctrl);
        self::assertStringContainsString('function requestSelfElevation', $ctrl);
        self::assertStringContainsString('dash-rh-parcours__choice', $css);
        self::assertStringContainsString('grid-template-columns: repeat(3, minmax(0, 1fr))', $css);
        self::assertStringContainsString('.dash-rh-parcours__choice[hidden]', $css);
        self::assertStringNotContainsString('rhStep', $cc);
    }

    public function testChoiceSlideDoesNotRenderBothFormsTogether(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root . '/views/partials/dashboard_rh_parcours.php');
        self::assertStringContainsString('data-rh-choice', $view);
        self::assertStringContainsString('data-rh-panel="absence"', $view);
        self::assertStringContainsString('data-rh-panel="elevation"', $view);
        self::assertStringContainsString('data-rh-panel="avancement"', $view);
        self::assertStringContainsString('data-rh-go', $view);
        self::assertStringNotContainsString('rhStep', $view);
        self::assertStringContainsString('Retour au choix', $view);
        self::assertStringNotContainsString('lg:grid-cols-2', $view);
        self::assertStringContainsString('id="dashboard-org-offers"', $view);
        self::assertStringContainsString('id="dashboard-member-rh"', $view);
        $cc = (string) file_get_contents(dirname(__DIR__, 2) . '/views/partials/dashboard_command_center.php');
        self::assertStringNotContainsString('dash-rh-grid', $cc);
    }
}
