<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class OperationalBoardPilotageAssetTest extends TestCase
{
    public function testPilotageBoardExposesEventsArticlesAndAtakWithoutDarkHero(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root . '/views/operations/board.php');
        $partial = (string) file_get_contents($root . '/views/operations/partials/board_pilotage.php');
        $controller = (string) file_get_contents($root . '/app/Controllers/Web/OperationalBoardController.php');
        $css = (string) file_get_contents($root . '/public/assets/css/operational-board.css');
        $pages = (string) file_get_contents($root . '/config/back_office_pages.php');

        $nav = (string) file_get_contents($root . '/views/partials/ath_sidebar_nav.php');

        self::assertStringContainsString('class="bo-tbo"', $view);
        self::assertStringContainsString("views/operations/partials/board_pilotage.php", $view);
        self::assertStringContainsString('partials/board_admin_card.php', $view);
        self::assertStringContainsString('Mettre en ligne', (string) file_get_contents($root . '/views/operations/partials/board_admin_card.php'));
        self::assertStringNotContainsString('ops-board__hero', $view);

        self::assertStringContainsString('boardCockpit', $controller);
        self::assertStringContainsString('cockpitPayload', $controller);
        self::assertStringContainsString('upcomingForTenant', $controller);
        self::assertStringContainsString('TenantMiniArticleRepository', $controller);

        self::assertStringContainsString('Événements', $partial);
        self::assertStringContainsString('Articles', $partial);
        self::assertStringContainsString('Poste de situation', $partial);
        self::assertStringContainsString('Carte tactique', $partial);
        self::assertStringContainsString('Ouvrir le registre', $partial);
        self::assertStringContainsString('Ouvrir les articles', $partial);
        self::assertStringContainsString('Ouvrir le poste ATAK', $partial);
        self::assertStringContainsString('Portail missions', $partial);
        self::assertStringContainsString('Effectifs', $partial);
        self::assertStringNotContainsString('endpoint', strtolower($partial));
        self::assertStringNotContainsString('JSON', $partial);
        self::assertStringNotContainsString('slug', strtolower($partial));

        $pageCss = (string) file_get_contents($root . '/public/assets/css/back-office-tableau-operationnel.css');
        self::assertStringContainsString('.ops-board--pilotage', $css);
        self::assertStringContainsString('.bo-tbo-tools', $pageCss);
        self::assertStringContainsString('html[data-bo-theme="dark"] .bo-tbo', $pageCss);
        self::assertStringContainsString('back-office-tableau-operationnel.css', $pages);

        self::assertStringContainsString('Tableau opérationnel', $pages);
        self::assertStringContainsString("['label' => 'Événements'", $pages);
        self::assertStringContainsString("['label' => 'Articles'", $pages);
        self::assertStringContainsString("['label' => 'Poste ATAK'", $pages);
        self::assertStringContainsString('Tableau opérationnel', $nav);
    }
}
