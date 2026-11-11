<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Jeu de couleurs « nuit » du thème TOC (ATAK natif 4.10.0, feuille de route TOC n° 35) : ajout pur. Mêmes noms de
 * jetons que le jour, mêmes contrastes minimaux, couleurs plus sombres ; les jetons du jour gardent leur valeur publiée.
 * Lecture du code seulement : rien de tout cela n'a tourné en jeu.
 */
final class AtakTocThemeNightTest extends TestCase
{
    private const MAIN = __DIR__ . '/../../mod/COMSPEC_ATAK_Native/Sources/addons/main/';
    /** Jetons du jour tels que publiés (4.5.0 à 4.9.x) : ce lot n'en change aucun. */
    private const DAY = [
        'bg0' => '#070a0d', 'bg1' => '#0d1318', 'bg2' => '#141c23', 'bg3' => '#1c2630', 'line' => '#24303a', 'lineStrong' => '#3a4a57',
        'text' => '#e8eef2', 'text2' => '#aab7c1', 'muted' => '#8494a0', 'accent' => '#34d399', 'accentDim' => '#0e3a2b', 'amber' => '#f5b841',
        'red' => '#ff5f56', 'redDim' => '#3a1110', 'blue' => '#62b0f5', 'friend' => '#6cb8ff', 'hostile' => '#ff6b6b', 'neutral' => '#7ee08a',
        'unknown' => '#f2d36b', 'lost' => '#98a4ae', 'mapTint' => '#a3a8ab', 'mapTintPic' => '#6b7175',
    ];

    private static function src(): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(self::MAIN . 'functions/toc/fn_tocTheme.sqf'));
    }

    /** @return array<string, string> */
    private static function tokens(string $name): array
    {
        self::assertSame(1, preg_match('/private ' . preg_quote($name, '/') . ' = (\[.*?\]);/s', self::src(), $m), $name);
        $t = json_decode($m[1], true);
        self::assertIsArray($t, $name);

        return array_column($t, 1, 0);
    }

    /** @return array{float, float, float} */
    private static function rgb(string $hex): array
    {
        return [hexdec(substr($hex, 1, 2)) / 255, hexdec(substr($hex, 3, 2)) / 255, hexdec(substr($hex, 5, 2)) / 255];
    }

    /** @param array{float, float, float} $c */
    private static function lum(array $c): float
    {
        $l = array_map(static fn (float $v): float => $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4, $c);

        return 0.2126 * $l[0] + 0.7152 * $l[1] + 0.0722 * $l[2];
    }

    /** @param array{float, float, float} $a @param array{float, float, float} $b */
    private static function contrast(array $a, array $b): float
    {
        $x = self::lum($a);
        $y = self::lum($b);

        return (max($x, $y) + 0.05) / (min($x, $y) + 0.05);
    }

    public function testDayTokensKeepTheirNamesAndValues(): void
    {
        self::assertSame(self::DAY, self::tokens('_tokens'));
    }

    public function testNightSetHasTheSameTokensDarker(): void
    {
        $night = self::tokens('_tokensNight');
        self::assertSame(array_keys(self::DAY), array_keys($night), 'mêmes jetons, même ordre');
        foreach ($night as $k => $hex) {
            self::assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $hex, $k);
            self::assertLessThan(self::lum(self::rgb(self::DAY[$k])), self::lum(self::rgb($hex)), "{$k} : plus sombre que le jour");
        }
        // Rien n'éblouit : le texte le plus clair reste sous 40 % de luminance (le jour : 85 %).
        self::assertLessThan(0.4, max(array_map(static fn (string $h): float => self::lum(self::rgb($h)), $night)));
    }

    public function testNightTextContrastOnEveryBackground(): void
    {
        $t = self::tokens('_tokensNight');
        $c = static fn (string $k): array => self::rgb($t[$k]);
        // Mêmes seuils que le thème du jour (AtakTocThemeTest).
        foreach (['bg0', 'bg1', 'bg2', 'redDim'] as $bg) {
            foreach (['text', 'text2', 'muted', 'accent', 'amber', 'red', 'blue', 'friend', 'lost'] as $fg) {
                self::assertGreaterThanOrEqual(4.5, self::contrast($c($fg), $c($bg)), "{$fg} sur {$bg}");
            }
        }
        foreach (['accent', 'amber', 'red', 'blue', 'muted'] as $fg) {
            $pill = [$c($fg)[0] * 0.16 + $c('bg1')[0] * 0.84, $c($fg)[1] * 0.16 + $c('bg1')[1] * 0.84, $c($fg)[2] * 0.16 + $c('bg1')[2] * 0.84];
            self::assertGreaterThanOrEqual(4.5, self::contrast($c($fg), $pill), "pastille {$fg}");
        }
        self::assertGreaterThanOrEqual(4.5, self::contrast($c('bg0'), $c('accent')));
        self::assertGreaterThanOrEqual(4.5, self::contrast($c('accent'), $c('bg2')));
        self::assertGreaterThanOrEqual(4.5, self::contrast($c('text'), $c('bg3')));
        self::assertGreaterThanOrEqual(3.0, self::contrast($c('muted'), $c('bg3')));
    }

    public function testNightIsAdditiveAndFollowsTheSetting(): void
    {
        $src = self::src();
        // Sans variante demandée : couleurs du jour, comme avant.
        self::assertStringContainsString('_a params [["_k", "text"], ["_al", 1], ["_var", "jour"]];', $src);
        self::assertStringContainsString('(_thm get "hex") getOrDefault [_a, "#ff00ff"]', $src);
        self::assertStringContainsString('private _hx = _thm get "hex";', $src);
        self::assertStringContainsString('params [["_var", "jour"]];', $src);
        // Variante : réglage du joueur, automatique = nuit quand le soleil est couché.
        self::assertStringContainsString('private _set = missionNamespace getVariable ["comspec_atak_native_toc_theme", "auto"];', $src);
        self::assertStringContainsString('if (_set in ["jour", "nuit"]) exitWith { _set };', $src);
        self::assertStringContainsString('["jour", "nuit"] select (sunOrMoon < 0.5)', $src);
        self::assertStringContainsString('["cNight", _cN], ["hexNight", _hxN]', $src);
        foreach (['case "variant": ', 'case "tokens": '] as $mode) {
            self::assertStringContainsString($mode, $src);
        }
        $pre = str_replace("\r\n", "\n", (string) file_get_contents(self::MAIN . 'XEH_preInit.sqf'));
        self::assertStringContainsString('[["auto", "jour", "nuit"], ["Automatique (nuit quand le soleil est couché)", "Jour", "Nuit"], 0], 0] call comspec_atak_native_fnc_addSetting;', $pre);
        // Première adoption : la fenêtre du journal, du diagnostic et des relèves.
        self::assertStringContainsString('["rgba", ["bg1", 0.97, "auto"]] call comspec_atak_native_fnc_tocTheme', str_replace("\r\n", "\n", (string) file_get_contents(self::MAIN . 'functions/toc/fn_tocDiag.sqf')));
        $doc = (string) file_get_contents(__DIR__ . '/../../docs/TOC-THEME.md');
        self::assertStringContainsString('_tokensNight', $doc);
        self::assertStringContainsString('"auto"', $doc);
    }
}
