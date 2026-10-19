<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\AtakTranslationPackRepository as Packs;
use App\Support\AtakTranslationTemplate;
use PHPUnit\Framework\TestCase;

/**
 * Modèle de l'atelier public /traduire-atak : lu dans localisation/template/xx.txt, mêmes règles que tools/i18n.py.
 */
final class AtakTranslationTemplateTest extends TestCase
{
    public function testReadsTheBundledTemplate(): void
    {
        $entries = AtakTranslationTemplate::entries(dirname(__DIR__, 2));

        self::assertGreaterThan(1000, count($entries));
        foreach (array_slice($entries, 0, 50) as $e) {
            self::assertNotSame('', $e[0]);
        }
    }

    public function testParsesDoubledQuotesAndSkipsDuplicates(): void
    {
        $raw = "\xEF\xBB\xBF[\n[\"Dit \"\"bonjour\"\"\", \"\", \"Says \"\"hello\"\"\"],\n[\"Dit \"\"bonjour\"\"\", \"x\", \"again\"],\n[\"%1 m\", \"\"]\n]\n";

        self::assertSame([['Dit "bonjour"', 'Says "hello"'], ['%1 m', '']], AtakTranslationTemplate::parse($raw));
    }

    public function testPageIsPublicAndTranslated(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        self::assertStringContainsString("\$router->get('/traduire-atak', [SitePagesController::class, 'translateAtak']);", $routes);
        self::assertStringContainsString("\$router->get('/traduire-atak/modele.json', [SitePagesController::class, 'translateAtakTemplate']);", $routes);
        self::assertStringContainsString("\$router->get('/traduire-atak/p/{id}', [SitePagesController::class, 'translateAtak']);", $routes);
        foreach (['create', 'show', 'history', 'write', 'bulk', 'decide', 'revert', 'settings', 'rotate', 'delete'] as $action) {
            self::assertStringContainsString("AtakTranslationPackApiController::class, '" . $action . "']", $routes);
        }
        self::assertFileExists($root . '/migrations/20261018000003_atak_translation_packs.sql');

        $fr = require $root . '/lang/fr/site.php';
        $en = require $root . '/lang/en/site.php';
        $keys = static fn (array $l): array => array_values(array_filter(array_keys($l), static fn ($k): bool => str_starts_with((string) $k, 'atakt_')));
        self::assertNotEmpty($keys($fr));
        self::assertSame($keys($fr), $keys($en));
    }

    public function testCatalogGivesKeysAndCategories(): void
    {
        $cat = AtakTranslationTemplate::catalog(dirname(__DIR__, 2));

        self::assertSame(count(AtakTranslationTemplate::entries(dirname(__DIR__, 2))), count($cat['entries']));
        self::assertArrayHasKey('other', $cat['categories']);
        [$h, $fr, , $c] = $cat['entries'][0];
        self::assertSame(AtakTranslationTemplate::keyHash($fr), $h);
        self::assertSame(substr(sha1($fr), 0, 16), $h);
        self::assertArrayHasKey($c, $cat['categories']);
        self::assertTrue(AtakTranslationTemplate::hasKey($h, dirname(__DIR__, 2)));
        self::assertFalse(AtakTranslationTemplate::hasKey('0000000000000000', dirname(__DIR__, 2)));
    }

    public function testSharedPackInputsAreValidated(): void
    {
        self::assertSame('de', Packs::validCode('de'));
        self::assertNull(Packs::validCode('fr'));
        self::assertNull(Packs::validCode('DE'));
        self::assertSame('abc123def456', Packs::validPublicId('abc123def456'));
        self::assertNull(Packs::validPublicId('../etc/passwd'));
        self::assertSame('0123456789abcdef', Packs::validKey('0123456789abcdef'));
        self::assertNull(Packs::validKey('0123456789ABCDEF'));
        self::assertSame('Anonyme', Packs::cleanAuthor("  \n "));
        self::assertSame('Ana Maria', Packs::cleanAuthor("Ana\t  Maria"));
        self::assertSame(32, mb_strlen(Packs::cleanAuthor(str_repeat('é', 50))));
        self::assertNull(Packs::cleanText(str_repeat('a', Packs::MAX_TEXT + 1)));
        self::assertNull(Packs::cleanText("\xC3"));
        self::assertSame('ok', Packs::cleanText("o\0k"));
        $t = Packs::newToken();
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{32}$/', $t);
        self::assertSame(64, strlen(Packs::tokenHash($t)));
    }
}
