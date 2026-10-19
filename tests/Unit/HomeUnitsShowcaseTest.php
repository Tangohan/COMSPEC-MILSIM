<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Accueil public : cartes « Unités sur la plateforme » (bannière, survol, défilement, compteurs).
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
        // Les KPI gardent leur valeur finale dans le HTML ; le JS ne fait que l’animer.
        self::assertStringContainsString('data-hi-count="<?= (int) $k[\'v\'] ?>"><?= $e($formatInt($k[\'v\'])) ?>', $view);

        $css = (string) file_get_contents(self::root() . '/public/assets/css/home-impact.css');
        // L’apparition échelonnée ne masque les cartes qu’une fois armée par le JS, et jamais en mouvement réduit.
        self::assertMatchesRegularExpression('/prefers-reduced-motion: no-preference\)\s*\{[^@]*\[data-hi-units\]\.is-armed \.hi-units__item/s', $css);
    }

    public function testScriptRespectsReducedMotion(): void
    {
        $js = (string) file_get_contents(self::root() . '/public/assets/js/home-impact.js');
        self::assertStringContainsString('function unitsShowcase()', $js);
        self::assertMatchesRegularExpression('/if \(!reduced\) \{\s*if \(stage\)/', $js);
        self::assertStringContainsString('if (finePointer && !reduced)', $js);
    }
}
