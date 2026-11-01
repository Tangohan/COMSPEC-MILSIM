<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Accueil public : cartes de communauté et chiffres réels, lisibles sans JavaScript.
 */
final class HomeUnitsShowcaseTest extends TestCase
{
    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testFrenchAndEnglishShareTheUnitsKeys(): void
    {
        $fr = require self::root() . '/lang/fr/home.php';
        $en = require self::root() . '/lang/en/home.php';
        $units = static fn (array $a): array => array_values(array_filter(array_keys($a), static fn (string $k): bool => str_starts_with($k, 'units_')));
        self::assertSame($units($fr), $units($en));

        $view = (string) file_get_contents(self::root() . '/views/home/index.php');
        preg_match_all("/'home\\.(units_[a-z_]+)'/", $view, $used);
        foreach (array_unique($used[1]) as $k) {
            self::assertArrayHasKey($k, $fr, 'clé manquante : ' . $k);
        }
    }

    public function testCardsKeepNamesModeratedAndContentVisibleWithoutJs(): void
    {
        $view = (string) file_get_contents(self::root() . '/views/home/index.php');
        self::assertStringContainsString('name_blur_html($unitName)', $view);
        // Les chiffres gardent leur valeur finale dans le HTML ; le JS ne fait que l’animer.
        self::assertStringContainsString('data-hp-count="<?= (int) $figure[\'n\'] ?>"><?= $e($formatInt((int) $figure[\'n\'])) ?>', $view);

        // Les chiffres d’une carte restent visibles : plus de révélation au survol ni d’apparition masquée.
        $css = (string) file_get_contents(self::root() . '/public/assets/css/home-impact.css');
        self::assertStringNotContainsString('hi-ucard', $css);
        self::assertStringNotContainsString('is-armed .hp-card', $css);
    }

    public function testScriptRespectsReducedMotion(): void
    {
        $js = (string) file_get_contents(self::root() . '/public/assets/js/home-impact.js');
        self::assertStringContainsString('function homeFigures()', $js);
        // Plus d’inclinaison 3D qui suit le pointeur.
        self::assertStringNotContainsString("addEventListener('pointermove'", $js);
    }
}
