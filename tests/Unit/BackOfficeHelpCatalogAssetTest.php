<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Portal\BackOfficeHelpCatalog;
use PHPUnit\Framework\TestCase;

final class BackOfficeHelpCatalogAssetTest extends TestCase
{
    public function testEveryGuideAnchorExists(): void
    {
        $root = dirname(__DIR__, 2);
        $siteGuide = '';
        foreach (glob($root . '/views/documentation/site/*.php') ?: [] as $file) {
            $siteGuide .= (string) file_get_contents($file);
        }
        $createGuide = (string) file_get_contents($root . '/views/community/create_guide.php');

        $entries = BackOfficeHelpCatalog::entries();
        self::assertNotEmpty($entries);
        foreach ($entries as $entry) {
            self::assertNotSame('', $entry['title']);
            $fragment = (string) parse_url($entry['href'], PHP_URL_FRAGMENT);
            if ($fragment === '') {
                continue;
            }
            $haystack = str_contains($entry['href'], 'communities/create/guide') ? $createGuide : $siteGuide;
            self::assertTrue(
                str_contains($haystack, 'id="' . $fragment . '"') || str_contains($haystack, "'" . $fragment . "' =>"),
                'Ancre introuvable : ' . $entry['href']
            );
        }
    }

    public function testSearchExposesGuidesAndCreationGuideRoute(): void
    {
        $root = dirname(__DIR__, 2);
        $svc = (string) file_get_contents($root . '/app/Services/Portal/BackOfficeSearchService.php');
        $js = (string) file_get_contents($root . '/public/assets/js/back-office-search.js');
        $routes = (string) file_get_contents($root . '/routes/web.php');

        self::assertStringContainsString('filterGuides', $svc);
        self::assertStringContainsString('BackOfficeHelpCatalog::entries()', $svc);
        self::assertStringContainsString('Aide et guides', $js);
        self::assertStringContainsString('ArrowDown', $js);
        self::assertStringContainsString("'/communities/create/guide'", $routes);
    }
}
