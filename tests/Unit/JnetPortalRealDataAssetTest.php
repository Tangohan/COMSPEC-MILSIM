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

    public function testSpacesAndExchangesAreWired(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $controller = (string) file_get_contents($root . '/app/Controllers/Web/JnetPortalController.php');
        $space = (string) file_get_contents($root . '/views/jnet/space.php');
        $composer = (string) file_get_contents($root . '/views/jnet/_exchange_composer.php');
        $exchange = (string) file_get_contents($root . '/views/jnet/_exchange.php');
        $css = (string) file_get_contents($root . '/public/assets/css/jnet_spaces.css');
        $migration = (string) file_get_contents($root . '/migrations/jnet_exchanges.sql');

        self::assertStringContainsString("'/jnet/u/{id}'", $routes);
        self::assertStringContainsString("'/jnet/u/{id}/echanges'", $routes);
        self::assertStringContainsString("'/jnet/echanges/{id}/lu'", $routes);

        self::assertStringContainsString('function unitSpace', $controller);
        self::assertStringContainsString('function postExchange', $controller);
        self::assertStringContainsString('function acknowledgeExchange', $controller);
        foreach (['postExchange', 'acknowledgeExchange'] as $action) {
            $start = strpos($controller, 'function ' . $action);
            self::assertNotFalse($start);
            $body = substr($controller, (int) $start, 900);
            self::assertStringContainsString('Csrf::validate', $body, $action . ' doit valider le jeton CSRF.');
        }
        self::assertStringContainsString('jnet_spaces.css', $controller);

        self::assertStringContainsString('Reçu', $space);
        self::assertStringContainsString('Dans l’unité', $space);
        self::assertStringContainsString('Remonté', $space);
        self::assertStringContainsString('Csrf::field()', $composer);
        self::assertStringContainsString('Csrf::field()', $exchange);

        // Le thème suit les jetons du back-office (clair / nuit) : pas de fond sombre codé en dur.
        self::assertStringContainsString('var(--ath-surface', $css);
        self::assertStringContainsString('--jn-unit', $css);
        self::assertStringNotContainsString('border-radius: 0.75rem', $css);

        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `jnet_exchanges`', $migration);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `jnet_exchange_targets`', $migration);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `jnet_exchange_reads`', $migration);
    }

    public function testSpacesAreGuidedReadableAndGiveLoadingFeedback(): void
    {
        $root = dirname(__DIR__, 2);
        $home = (string) file_get_contents($root . '/views/jnet/home.php');
        $space = (string) file_get_contents($root . '/views/jnet/space.php');
        $embed = (string) file_get_contents($root . '/views/jnet/_bo_content.php');
        $guide = (string) file_get_contents($root . '/views/jnet/_guide.php');
        $js = (string) file_get_contents($root . '/public/assets/js/jnet_spaces.js');
        $dashboard = (string) file_get_contents($root . '/app/Services/Jnet/JnetDashboardService.php');

        // Hiérarchie : arbre des unités, plus de grille plate.
        self::assertStringContainsString('_unit_tree.php', $home);
        self::assertStringContainsString('_unit_tree.php', $space);
        self::assertStringNotContainsString("\$commands", $home);

        // Guide sur les deux écrans.
        self::assertStringContainsString('_guide.php', $home);
        self::assertStringContainsString('_guide.php', $space);
        self::assertStringContainsString('Comment fonctionne JNET', $guide);

        // Retour de chargement et un seul chargement de l'ORBAT par requête.
        self::assertStringContainsString('jnet_spaces.js', $embed);
        self::assertStringContainsString('jn-progress', $js);
        self::assertStringContainsString('aria-busy', $js);
        self::assertStringContainsString('private array $memo', $dashboard);

        // Photo d'opérateur (portrait du dossier), jamais la photo de compte.
        self::assertStringContainsString('personnel_operator_portrait_url', $dashboard);
        self::assertStringNotContainsString('user_media_public_url($row[\'avatar_url\']', $dashboard);
        self::assertStringContainsString('jn-ex__face', (string) file_get_contents($root . '/views/jnet/_exchange.php'));
    }
}
