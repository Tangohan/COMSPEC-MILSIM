<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Portal\SearchCatalogLocale;
use PHPUnit\Framework\TestCase;

final class SearchCatalogLocaleTest extends TestCase
{
    /** @var array{set: bool, locale: mixed} */
    private array $saved = ['set' => false, 'locale' => null];

    protected function setUp(): void
    {
        $this->saved = ['set' => array_key_exists('__app_locale', $GLOBALS), 'locale' => $GLOBALS['__app_locale'] ?? null];
    }

    protected function tearDown(): void
    {
        if ($this->saved['set']) {
            $GLOBALS['__app_locale'] = $this->saved['locale'];
        } else {
            unset($GLOBALS['__app_locale']);
        }
    }

    public function testFrenchCatalogIsUntouched(): void
    {
        $GLOBALS['__app_locale'] = 'fr';
        $items = [['title' => 'Effectifs', 'subtitle' => 'Tableur des membres', 'href' => '/x', 'keywords' => 'effectifs']];

        self::assertSame($items, SearchCatalogLocale::localize($items));
    }

    public function testEnglishKeepsFrenchWordsSearchable(): void
    {
        $GLOBALS['__app_locale'] = 'en';
        $out = SearchCatalogLocale::localize([['title' => 'Effectifs', 'subtitle' => 'Tableur des membres', 'href' => '/x', 'keywords' => 'roster']]);

        self::assertStringContainsString('Effectifs', $out[0]['keywords']);
        self::assertStringContainsString('Tableur des membres', $out[0]['keywords']);
        self::assertSame('/x', $out[0]['href']);
    }

    public function testBothSearchesLocalizeTheirCatalogs(): void
    {
        $root = dirname(__DIR__, 2);
        self::assertStringContainsString('SearchCatalogLocale::localize(', (string) file_get_contents($root . '/app/Services/Portal/BackOfficeSearchService.php'));
        self::assertStringContainsString('SearchCatalogLocale::localize(', (string) file_get_contents($root . '/app/Services/Portal/PortalSearchService.php'));
    }
}
