<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Accueil public : l’aperçu de la plateforme (données fictives) est branché, traduit et cohérent.
 */
final class HomeDemoShowcaseTest extends TestCase
{
    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testShowcaseIsIncludedOnTheLandingPage(): void
    {
        $index = (string) file_get_contents(self::root() . '/views/home/index.php');
        self::assertStringContainsString("views/home/partials/demo_showcase.php", $index);
        // Placé après le récit et avant la liste des modules.
        self::assertTrue(strpos($index, 'hi-story') < strpos($index, 'demo_showcase.php'));
        self::assertTrue(strpos($index, 'demo_showcase.php') < strpos($index, 'modules-heading'));
    }

    public function testEveryTabHasItsPanelAndOnlyTheFirstIsVisible(): void
    {
        $view = (string) file_get_contents(self::root() . '/views/home/partials/demo_showcase.php');
        preg_match_all('/data-hd-panel="([a-z]+)"/', $view, $panels);
        self::assertSame(['orbat', 'personnel', 'command', 'training', 'op', 'jnet'], $panels[1]);
        foreach ($panels[1] as $key) {
            self::assertStringContainsString('aria-labelledby="hd-tab-' . $key . '"', $view);
        }
        self::assertSame(5, substr_count($view, 'hidden>'));
        // Démonstration assumée : la mention « données fictives » est visible.
        self::assertStringContainsString("\$d('fake_note')", $view);
        self::assertStringContainsString("\$d('badge')", $view);
    }

    public function testFrenchAndEnglishShareTheSameDemoKeys(): void
    {
        $fr = require self::root() . '/lang/fr/home.php';
        $en = require self::root() . '/lang/en/home.php';
        $demo = static fn (array $a): array => array_values(array_filter(array_keys($a), static fn (string $k): bool => str_starts_with($k, 'demo_')));
        self::assertNotEmpty($demo($fr));
        self::assertSame($demo($fr), $demo($en));

        $view = (string) file_get_contents(self::root() . '/views/home/partials/demo_showcase.php');
        preg_match_all("/\\\$d\\('([a-z0-9_]+)'\\)/", $view, $used);
        foreach (array_unique($used[1]) as $k) {
            self::assertArrayHasKey('demo_' . $k, $fr, 'clé manquante : demo_' . $k);
        }
        // Clés construites dynamiquement (onglets, statuts).
        foreach (['tab_orbat_d', 'tab_jnet', 'st_active', 'st_leave', 'st_trial', 'tr_progress', 'tr_eval', 'tr_done'] as $k) {
            self::assertArrayHasKey('demo_' . $k, $fr);
        }
    }

    public function testTabsAreWiredInTheLandingScript(): void
    {
        $js = (string) file_get_contents(self::root() . '/public/assets/js/home-impact.js');
        self::assertStringContainsString('[data-hd]', $js);
        self::assertStringContainsString('ArrowDown', $js);
        $css = (string) file_get_contents(self::root() . '/public/assets/css/home-impact.css');
        self::assertStringContainsString('.home-impact .hd-panel[hidden]', $css);
    }
}
