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
        $requirePos = strpos($cc, "dashboard_rh_parcours.php");
        $channelsPos = strpos($cc, 'Mes salons suivis');
        self::assertNotFalse($requirePos);
        self::assertNotFalse($channelsPos);
        self::assertGreaterThan($channelsPos, $requirePos);

        self::assertStringContainsString('rhStep === \'choice\'', $view);
        self::assertStringContainsString('Absence', $view);
        self::assertStringContainsString('Élévation', $view);
        self::assertStringContainsString('Avancement', $view);
        self::assertStringContainsString('Étape 1 sur 2', $view);
        self::assertStringContainsString('dash-rh-parcours__choice', $view);
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
    }

    public function testChoiceSlideDoesNotRenderBothFormsTogether(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root . '/views/partials/dashboard_rh_parcours.php');
        self::assertStringContainsString("x-show=\"rhStep === 'choice'\"", $view);
        self::assertStringContainsString("x-show=\"rhStep === 'absence'\"", $view);
        self::assertStringContainsString("x-show=\"rhStep === 'elevation'\"", $view);
        self::assertStringContainsString("x-show=\"rhStep === 'avancement'\"", $view);
        self::assertStringContainsString('Retour au choix', $view);
        self::assertStringNotContainsString('lg:grid-cols-2', $view);
    }
}
