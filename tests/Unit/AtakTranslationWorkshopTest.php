<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\AtakTranslationPackRepository as Packs;
use App\Support\AtakTranslationCheck;
use App\Support\AtakTranslationModBuilder;
use App\Support\AtakTranslationTemplate;
use App\Support\AtakTranslationWorkshop;
use PHPUnit\Framework\TestCase;

/**
 * Workshop des langues de l'ATAK natif : contrôle des paquets (mêmes règles que l'atelier en JS), état face au mod,
 * mod prêt à jouer fabriqué par le serveur, identifiant Steam Workshop.
 */
final class AtakTranslationWorkshopTest extends TestCase
{
    public function testCheckMatchesTheWorkshopRules(): void
    {
        self::assertSame([], AtakTranslationCheck::check('Bonjour %1', '')['errors']);
        self::assertSame('err_placeholders', AtakTranslationCheck::check('Distance %1 m', 'Distancia m')['errors'][0][0]);
        self::assertSame('err_newline', AtakTranslationCheck::check('Salut', "Hola\nya")['errors'][0][0]);
        self::assertSame([], AtakTranslationCheck::check('%2 de %1', '%1 of %2')['errors']);
        $w = array_column(AtakTranslationCheck::check('<t>a</t> %1 %%', '<t>b</t> %1 %')['warnings'], 0);
        self::assertSame(['warn_percent', 'warn_lone_percent'], $w);
        self::assertSame(['warn_space'], array_column(AtakTranslationCheck::check(' a', 'b ')['warnings'], 0));
    }

    public function testAuditCountsAgainstTheCurrentTemplate(): void
    {
        $entries = [['h1', 'Un %1', 'One %1'], ['h2', 'Deux', 'Two'], ['h3', 'Trois', 'Three'], ['h4', 'Quatre %1', 'Four %1']];
        $a = AtakTranslationCheck::audit($entries, ['h1' => 'Uno %1', 'h2' => 'Dos', 'h4' => 'Cuatro', 'old' => 'Viejo']);

        self::assertSame(4, $a['total']);
        self::assertSame(2, $a['translated']);
        self::assertSame(1, $a['errors']);
        self::assertSame(1, $a['missing']);
        self::assertSame(1, $a['obsolete']);
        self::assertSame(50, $a['pct']);
        self::assertSame('h4', $a['samples'][0]['h']);
    }

    public function testStatusOfAPublishedVersion(): void
    {
        $release = ['lang_code' => 'es', 'template_fp' => 'old', 'template_total' => 100, 'translated' => 90];
        $s = static fn (array $audit, array $official = ['fr' => 'Français', 'en' => 'English'], string $fp = 'new'): array => AtakTranslationWorkshop::status($release, $audit, $official, $fp);

        self::assertSame('ok', $s(['missing' => 10, 'errors' => 0, 'new' => 0])['key']);
        self::assertSame(['key' => 'outdated', 'new' => 25, 'errors' => 0], $s(['missing' => 35, 'errors' => 0, 'new' => 25]));
        // Modèle de l'époque inconnu : estimation par les phrases manquantes.
        self::assertSame(12, $s(['missing' => 22, 'errors' => 0, 'new' => null])['new']);
        self::assertSame('recheck', $s(['missing' => 10, 'errors' => 3, 'new' => 0])['key']);
        self::assertSame('official', $s(['missing' => 10, 'errors' => 0], ['es' => 'Español'])['key']);
        self::assertSame(0, $s(['missing' => 40, 'errors' => 0], [], 'old')['new']);
    }

    public function testOfficialLanguagesComeFromTheModConfig(): void
    {
        $langs = AtakTranslationTemplate::officialLanguages(dirname(__DIR__, 2));
        self::assertSame('Français', $langs['fr']);
        self::assertSame('English', $langs['en']);
        self::assertSame(
            ['en' => 'English', 'es' => 'Español'],
            AtakTranslationTemplate::parseLanguages("class CfgComspecLanguages {\n    class en { name=\"English\"; file=\"x\"; };\n    class es { name=\"Español\"; gameLanguage[]={\"Spanish\"}; };\n};\nclass CfgFunctions {};")
        );
    }

    public function testPboIsTheBohemiaFormatWithItsChecksum(): void
    {
        $pbo = AtakTranslationModBuilder::buildPbo('comspec_atak_lang_es', [['name' => 'config.cpp', 'data' => "class X {};\n"], ['name' => 'es.txt', 'data' => "[\n]\n"]], 1760000000);

        self::assertStringStartsWith("\0sreV" . str_repeat("\0", 16) . "prefix\0comspec_atak_lang_es\0\0config.cpp\0", $pbo);
        self::assertSame(sha1(substr($pbo, 0, -21), true), substr($pbo, -20));
        self::assertSame("\0", $pbo[strlen($pbo) - 21]);
        // Même octets que buildPbo de public/assets/js/atak-translate.js (empreinte du fichier calculée en JS).
        self::assertSame('1a5103f34bc4d356f02f5e8b0756cf93c308131a', sha1($pbo));
    }

    public function testTxtDropsSentencesThatNoLongerMatchTheMod(): void
    {
        $r = AtakTranslationModBuilder::buildTxt([['a', 'Dit "%1"', 'Says "%1"'], ['b', 'Deux', 'Two'], ['c', 'Trois', '']], ['a' => 'Dice "%1"', 'b' => '', 'c' => "x\ny"]);

        self::assertSame("[\n[\"Dit \"\"%1\"\"\", \"Dice \"\"%1\"\"\", \"Says \"\"%1\"\"\"],\n[\"Deux\", \"\", \"Two\"],\n[\"Trois\", \"\", \"\"]\n]\n", $r['text']);
        self::assertSame(1, $r['written']);
        self::assertSame(1, $r['skipped']);
        self::assertSame([['Dit "%1"', 'Says "%1"'], ['Deux', 'Two'], ['Trois', '']], AtakTranslationTemplate::parse($r['text']));
    }

    public function testPlayerZipIsReadable(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            self::markTestSkipped('ext-zip absente');
        }
        $built = AtakTranslationModBuilder::playerZip([['k1', 'Carte', 'Map']], ['k1' => 'Mapa'], [
            'code' => 'es', 'name' => 'Español', 'game' => 'Spanish', 'author' => 'Equipo', 'version' => '2.2.0',
            'site' => 'https://example.test/traduire-atak', 'release' => 3, 'page' => 'https://example.test/traduire-atak/workshop/abc',
        ], 1760000000);
        self::assertSame('COMSPEC_ATAK_Lang_es_v3.zip', $built['file']);
        $path = (string) tempnam(sys_get_temp_dir(), 'atakz');
        file_put_contents($path, $built['zip']);
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($path) === true);
        self::assertSame(3, $zip->numFiles);
        $pbo = (string) $zip->getFromName('@COMSPEC_ATAK_Lang_es/addons/comspec_atak_lang_es.pbo');
        self::assertStringContainsString('["Carte", "Mapa", "Map"]', $pbo);
        self::assertStringContainsString('class CfgComspecLanguages', $pbo);
        self::assertStringContainsString('gameLanguage[] = {"Spanish"};', $pbo);
        self::assertStringContainsString('v3', (string) $zip->getFromName('@COMSPEC_ATAK_Lang_es/mod.cpp'));
        $zip->close();
        unlink($path);
    }

    public function testSteamWorkshopIdentifier(): void
    {
        self::assertSame('3381241147', Packs::workshopId('3381241147'));
        self::assertSame('3381241147', Packs::workshopId(' https://steamcommunity.com/sharedfiles/filedetails/?id=3381241147&searchtext= '));
        self::assertSame('3381241147', Packs::workshopId('https://steamcommunity.com/workshop/filedetails/?l=french&id=3381241147'));
        self::assertSame('3381241147', Packs::workshopId('steam://url/CommunityFilePage/3381241147'));
        self::assertSame('', Packs::workshopId(''));
        self::assertNull(Packs::workshopId('https://evil.example/?id=3381241147'));
        self::assertNull(Packs::workshopId('12'));
        self::assertSame('https://steamcommunity.com/sharedfiles/filedetails/?id=3381241147', AtakTranslationWorkshop::steamUrl('3381241147'));
        self::assertSame("Ligne 1\n\nLigne 2", Packs::cleanDescription("Ligne 1\r\n\r\n\r\n\r\nLigne 2\x07"));
    }

    public function testPagesAreRoutedAndTranslated(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        foreach (['catalog' => "get('/traduire-atak/workshop'", 'item' => "get('/traduire-atak/workshop/{id}'", 'download' => "get('/traduire-atak/workshop/{id}/telecharger'", 'moderate' => "post('/traduire-atak/workshop/{id}/moderer'"] as $action => $path) {
            self::assertStringContainsString($path . ', [\App\Controllers\Web\AtakTranslationWorkshopController::class, \'' . $action . '\']);', $routes);
        }
        foreach (['publish', 'listing'] as $action) {
            self::assertStringContainsString("AtakTranslationPackApiController::class, '" . $action . "']", $routes);
        }
        self::assertFileExists($root . '/migrations/20261019000010_atak_translation_workshop.sql');

        $fr = require $root . '/lang/fr/site.php';
        $en = require $root . '/lang/en/site.php';
        $keys = static fn (array $l): array => array_values(array_filter(array_keys($l), static fn ($k): bool => str_starts_with((string) $k, 'atakw_')));
        self::assertNotEmpty($keys($fr));
        self::assertSame($keys($fr), $keys($en));
        foreach (['views/site/atak_translate_workshop.php', 'views/site/atak_translate_item.php', 'views/site/atak_translate_tabs.php'] as $view) {
            preg_match_all("/\\\$w\\('([a-z0-9_]+)'[,)]/", (string) file_get_contents($root . '/' . $view), $m);
            foreach (array_unique($m[1]) as $k) {
                self::assertArrayHasKey('atakw_' . $k, $fr, $view);
            }
        }
    }
}
