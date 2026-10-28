<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\I18n\CommunityLanguages;
use PHPUnit\Framework\TestCase;

/**
 * Traduction communautaire du site (/traduire-site) et rôles de contributeur (/contribuer) : catalogue des textes,
 * contrôles d'une traduction, dictionnaire compilé lu par HtmlTranslator / Translator, routes et pages.
 */
final class SiteCommunityTranslationTest extends TestCase
{
    protected function tearDown(): void
    {
        @unlink(base_path(CommunityLanguages::DIR . '/zz.php'));
        CommunityLanguages::reset();
    }

    public function testCatalogHoldsPageTextsAndKeyedTexts(): void
    {
        $catalog = CommunityLanguages::catalog(dirname(__DIR__, 2));
        self::assertGreaterThan(10000, count($catalog));
        $kinds = array_count_values(array_column($catalog, 3));
        self::assertGreaterThan(1000, $kinds['text'] ?? 0);
        self::assertGreaterThan(100, $kinds['key'] ?? 0);
        $hashes = array_column($catalog, 0);
        self::assertSame(count($hashes), count(array_unique($hashes)));
        self::assertContains(CommunityLanguages::hash('@site.contrib_title'), $hashes);
        self::assertSame('Contribuer à Athena', CommunityLanguages::frenchOf('@site.contrib_title'));
    }

    public function testCheckKeepsValuesMarkupAndPrefix(): void
    {
        self::assertSame([], CommunityLanguages::check('Bonjour %1, il reste %2 places', 'Hola %1, quedan %2 plazas'));
        self::assertSame(['placeholders'], CommunityLanguages::check('Bonjour %1', 'Hola'));
        self::assertSame([], CommunityLanguages::check('" class="ath-row-action">Détails', '" class="ath-row-action">Detalles'));
        self::assertSame(['markup'], CommunityLanguages::check('" class="ath-row-action">Détails', '" class="x">Detalles'));
        self::assertSame(['markup'], CommunityLanguages::check('Lire <a href="/x">la suite</a>', 'Leer la continuación'));
        self::assertSame(['prefix'], CommunityLanguages::check('!!Fichier reçu invalide.', 'Archivo no válido.'));
        self::assertSame(['empty'], CommunityLanguages::check('Bonjour', '  '));
        // Catalogue à clés : valeurs « :nom » du Translator ; « 12:30 » et les liens ne comptent pas.
        self::assertSame([], CommunityLanguages::check('@site.sitetr_progress', ':done / :total textos (:pct %)'));
        self::assertSame(['placeholders'], CommunityLanguages::check('@site.sitetr_progress', ':done / :total textos'));
    }

    public function testCodesAreTwoLettersAndNeverFrench(): void
    {
        self::assertTrue(CommunityLanguages::validCode('es'));
        self::assertTrue(CommunityLanguages::validCode('pt-br'));
        self::assertTrue(CommunityLanguages::validCode('en'));
        self::assertFalse(CommunityLanguages::validCode('fr'));
        self::assertFalse(CommunityLanguages::validCode('../x'));
        self::assertFalse(CommunityLanguages::validCode('ESP'));
        self::assertFalse(CommunityLanguages::hasShippedEnglish('Bonjour', ''));
        self::assertFalse(CommunityLanguages::hasShippedEnglish('Bonjour', 'bonjour'));
        self::assertTrue(CommunityLanguages::hasShippedEnglish('Bonjour', 'Hello'));
        self::assertStringContainsString("'blank'", (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/I18n/SiteTranslationService.php'));
        self::assertStringContainsString("case 'blank'", (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-translate.js'));
    }

    public function testCompiledDictionarySplitsPageTextsAndKeys(): void
    {
        $n = CommunityLanguages::compile('zz', [
            CommunityLanguages::hash('@site.contrib_title') => 'Contribuir a Athena',
            CommunityLanguages::hash('@site.sitetr_progress') => 'sin valores',
            'ffffffffffffffff' => 'inconnu',
        ]);
        self::assertSame(1, $n);
        CommunityLanguages::reset();
        $dict = CommunityLanguages::dictionary('zz');
        self::assertSame(['site.contrib_title' => 'Contribuir a Athena'], $dict['k']);
        self::assertSame([], $dict['t']);
        self::assertGreaterThan(0, CommunityLanguages::version('zz'));
        self::assertSame(['t' => [], 'k' => []], CommunityLanguages::dictionary('../../etc'));
    }

    public function testRoutesPagesAndAdminAreWired(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        foreach (["'/contribuer'", "'/contribuer/demande'", "'/traduire-site'", "'/traduire-site/dico/{code}.js'", "'/traduire-site/{code}/phrases'", "'/traduire-site/propositions/{id}'", "'/admin/system/contributeurs'"] as $path) {
            self::assertStringContainsString($path, $routes);
        }
        // Le dictionnaire JavaScript passe avant la page d'une langue ({code} attraperait « dico »).
        self::assertLessThan(strpos($routes, "'/traduire-site/{code}'"), strpos($routes, "'/traduire-site/dico/{code}.js'"));
        foreach (['views/site/contribute.php', 'views/site/site_translate.php', 'views/site/site_translate_workspace.php', 'views/admin/system/contributors.php', 'public/assets/js/site-translate.js', 'public/assets/css/site-contribute.css', 'migrations/20261025000001_site_contributors_translation.sql'] as $file) {
            self::assertFileExists($root . '/' . $file);
        }
        self::assertStringContainsString("'admin/system/contributeurs'", (string) file_get_contents($root . '/views/partials/platform_admin_sidebar.php'));
        // Les textes de la page de traduction ne passent pas par le traducteur des scripts (ils sont en français exprès).
        self::assertStringContainsString('id="sitetr-list" translate="no"', (string) file_get_contents($root . '/views/site/site_translate_workspace.php'));
    }

    public function testPageKeysExistInBothLanguages(): void
    {
        $root = dirname(__DIR__, 2);
        $fr = require $root . '/lang/fr/site.php';
        $en = require $root . '/lang/en/site.php';
        foreach (array_keys($fr) as $k) {
            if (str_starts_with((string) $k, 'contrib_') || str_starts_with((string) $k, 'sitetr_')) {
                self::assertArrayHasKey($k, $en, $k);
            }
        }
    }
}
