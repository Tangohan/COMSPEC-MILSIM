<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class JnetPortalRealDataAssetTest extends TestCase
{
    public function testDashboardServiceDoesNotInventDemoContent(): void
    {
        $service = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/Jnet/JnetDashboardService.php');

        self::assertStringContainsString('Aucun contenu de démonstration', $service);
        self::assertStringContainsString('loadPublishedArticles', $service);
        self::assertStringContainsString('loadPublishedDocuments', $service);
        self::assertStringContainsString('buildIntelFeed', $service);
        self::assertStringContainsString('personnelFilterOptions', $service);
        self::assertStringNotContainsString('demoPersonnel', $service);
        self::assertStringNotContainsString('demoTargets', $service);
        self::assertStringNotContainsString('demoIntelFeed', $service);
        self::assertStringNotContainsString('demoSubUnits', $service);
        self::assertStringNotContainsString('ABU KARIM', $service);
        self::assertStringNotContainsString('MILLER, John', $service);
        self::assertStringNotContainsString('Prêts — Discrets — Efficaces', $service);
        self::assertStringNotContainsString('SECRET // REL COMSPEC', $service);
        self::assertStringNotContainsString('unitAssets', $service);
    }

    public function testViewsExposeRealActionsAndHonestEmptyStates(): void
    {
        $root = dirname(__DIR__, 2);
        $home = (string) file_get_contents($root . '/views/jnet/home.php');
        $unit = (string) file_get_contents($root . '/views/jnet/unit.php');
        $library = (string) file_get_contents($root . '/views/jnet/library.php');
        $targets = (string) file_get_contents($root . '/views/jnet/targets.php');
        $personnel = (string) file_get_contents($root . '/views/jnet/personnel.php');
        $embed = (string) file_get_contents($root . '/views/jnet/_bo_content.php');
        $controller = (string) file_get_contents($root . '/app/Controllers/Web/JnetPortalController.php');
        $css = (string) file_get_contents($root . '/public/assets/css/jnet_portal.css');
        $embedCss = (string) file_get_contents($root . '/public/assets/css/jnet_bo_embed.css');

        self::assertStringContainsString('jnet-shortcuts', $home);
        self::assertStringContainsString('Articles de l’unité', $home);
        self::assertStringContainsString('Documents publiés', $home);
        self::assertStringContainsString('Aucune opération engagée', $home);
        self::assertStringContainsString('Aucun article publié', $home);
        self::assertStringNotContainsString('Mission board', $home);

        self::assertStringContainsString('L’organigramme n’est pas encore renseigné', $unit);
        self::assertStringNotContainsString('Structure de démonstration', $unit);
        self::assertStringNotContainsString('Moyens de l’unité', $unit);

        self::assertStringContainsString('Aucun document, article ou parcours', $library);
        self::assertStringContainsString("url('articles')", $library);

        self::assertStringContainsString('Aucun dossier ouvert', $targets);
        self::assertStringContainsString('personnelFilters', $personnel);

        self::assertStringNotContainsString('jnet-beta', $embed);
        self::assertStringNotContainsString('contenus peut être illustrative', $embed);

        self::assertStringContainsString('buildLibrary', $controller);
        self::assertStringContainsString('buildExploitation', $controller);
        self::assertStringContainsString('personnelFilterOptions', $controller);

        self::assertStringContainsString('.jnet-shortcuts', $css);
        self::assertStringContainsString('.jnet-linklist', $css);
        self::assertStringContainsString('.jnet-shortcut', $embedCss);
    }
}
