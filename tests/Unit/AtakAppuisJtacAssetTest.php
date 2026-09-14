<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakAppuisJtacAssetTest extends TestCase
{
    public function testAppuisOpensJtacPanelAndLoadsRequests(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root . '/views/atak.php');
        $nav = (string) file_get_contents($root . '/public/assets/js/atak-section-nav.js');
        $profile = (string) file_get_contents($root . '/public/assets/js/atak-session-profile.js');
        $jtac = (string) file_get_contents($root . '/public/assets/js/atak-jtac.js');
        $chrome = (string) file_get_contents($root . '/public/assets/js/atak-panel-chrome.js');

        self::assertStringContainsString("activateTab('jtac')", $view);
        self::assertStringContainsString('id="tab-jtac"', $view);
        self::assertStringContainsString('Aucun appui en cours', $view);
        self::assertStringContainsString("tab === 'jtac'", $view);
        self::assertStringContainsString('ATAKJTAC.refresh', $view);
        self::assertStringContainsString('atak-jtac.js?v=', $view);

        self::assertStringContainsString("sectionId === 'support'", $nav);
        self::assertStringContainsString("section === 'support' || section === 'qr'", $nav);
        self::assertStringContainsString('extra.tab', $nav);
        self::assertStringContainsString('firstUnpinnedIn(visible) || pick', $nav);

        self::assertStringContainsString("case 'jtac':\n        return true;", $profile);
        self::assertStringContainsString('if (!btn) return false;', $chrome);
        self::assertStringNotContainsString('if (!btn || btn.hidden) return false;', $chrome);

        self::assertStringContainsString('credentials: \'include\'', $jtac);
        self::assertStringContainsString('Aucun appui en cours', $jtac);
        self::assertStringContainsString('function refresh()', $jtac);
        self::assertStringContainsString('/api/cas?mapId=', $jtac);
        self::assertStringContainsString('/api/nine-line', $jtac);
        self::assertStringContainsString('IP / point initial', $view);
        self::assertFileExists($root . '/docs/bugs/2026-09-13-atak-appuis-fenetre-vide.md');
    }
}
