<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Thèmes du téléphone (fn_theme) : contraste WCAG des encres sur les fonds, pour SOMBRE, JOUR et NUIT JVN.
 * Le thème JOUR laissait des textes gris clair sur fond blanc (autotest du 8/11, Réglages, capture 077).
 */
final class AtakThemeContrastTest extends TestCase
{
    private const MAIN = __DIR__ . '/../../mod/COMSPEC_ATAK_Native/Sources/addons/main';

    /** @return array<string, array{string}> */
    public static function themes(): array
    {
        return ['jour' => ['case "DAY"'], 'nuit JVN' => ['case "NVG"'], 'sombre' => ['default {']];
    }

    /** @return array<string, array{float, float, float}> */
    private function tokens(string $marker): array
    {
        $src = str_replace("\r\n", "\n", (string) file_get_contents(self::MAIN . '/functions/ui/fn_theme.sqf'));
        $start = strpos($src, '_th = switch (_theme) do {');
        self::assertNotFalse($start);
        $i = strpos($src, $marker, $start);
        self::assertNotFalse($i);
        $block = substr($src, $i, (int) strpos($src, ']' . "\n" . '        };', $i) - $i);
        preg_match_all('/\["(\w+)", \[([0-9.]+), ([0-9.]+), ([0-9.]+), [0-9.]+\]\]/', $block, $m, PREG_SET_ORDER);
        $out = [];
        foreach ($m as $row) {
            $out[$row[1]] = [(float) $row[2], (float) $row[3], (float) $row[4]];
        }

        return $out;
    }

    /** @param array{float, float, float} $c */
    private static function lum(array $c): float
    {
        $ch = static fn (float $v): float => $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;

        return 0.2126 * $ch($c[0]) + 0.7152 * $ch($c[1]) + 0.0722 * $ch($c[2]);
    }

    /** @param array{float, float, float} $a @param array{float, float, float} $b */
    private static function contrast(array $a, array $b): float
    {
        $la = self::lum($a);
        $lb = self::lum($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    #[DataProvider('themes')]
    public function testEncresLisiblesSurLesFonds(string $marker): void
    {
        $t = $this->tokens($marker);
        foreach (['bg1', 'surface', 'surfaceAlt'] as $bg) {
            self::assertArrayHasKey($bg, $t);
            // Texte : 4,5 (WCAG AA). États et encres secondaires : 4,5 aussi, ils portent du texte (« en ligne », valeurs).
            foreach (['text', 'textDim', 'textMuted', 'ok', 'warn', 'alert', 'info'] as $ink) {
                $c = self::contrast($t[$ink], $t[$bg]);
                self::assertGreaterThanOrEqual(4.5, round($c, 2), "{$ink} sur {$bg} : contraste " . round($c, 2));
            }
            // Désactivé : 3 (élément non essentiel).
            self::assertGreaterThanOrEqual(3.0, round(self::contrast($t['off'], $t[$bg]), 2), "off sur {$bg}");
        }
    }

    public function testPictogrammesEtTitresDEtatSuiventLeTheme(): void
    {
        $src = (string) file_get_contents(self::MAIN . '/functions/ui/fn_formRender.sqf');
        self::assertStringContainsString('private _picTint = {', $src);
        self::assertStringContainsString('if !(_th get "dark") then { _col = [(_col select 0) * 0.55', $src);
        self::assertStringNotContainsString('_p ctrlSetTextColor [1, 1, 1, 1];', $src);
    }
}
