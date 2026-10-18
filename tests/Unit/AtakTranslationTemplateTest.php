<?php

declare(strict_types=1);

namespace Tests\Unit;

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

        $fr = require $root . '/lang/fr/site.php';
        $en = require $root . '/lang/en/site.php';
        $keys = static fn (array $l): array => array_values(array_filter(array_keys($l), static fn ($k): bool => str_starts_with((string) $k, 'atakt_')));
        self::assertNotEmpty($keys($fr));
        self::assertSame($keys($fr), $keys($en));
    }
}
