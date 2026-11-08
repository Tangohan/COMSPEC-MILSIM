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

    /**
     * Bouton tonal de l'écran de verrouillage (4.1.1) : encre d'accent sur fond teinté d'accent, au repos et survolé,
     * pour les six accents du joueur (fn_accent) et les trois thèmes. Les formules sont celles de fn_theme (vérifiées ci-dessous).
     */
    public function testBoutonTonalLisibleAvecChaqueAccent(): void
    {
        $theme = str_replace("\r\n", "\n", (string) file_get_contents(self::MAIN . '/functions/ui/fn_theme.sqf'));
        foreach ([
            'case "DAY": { [[1, 1, 1, 1], _acc, 0.20] call _mix };',
            'case "NVG": { [[0, 0, 0, 1], _acc, 0.16] call _mix };',
            'default { [_th get "surface", _acc, 0.26] call _mix };',
            'case "DAY": { [[1, 1, 1, 1], _acc, 0.34] call _mix };',
            'case "NVG": { [[0, 0, 0, 1], _acc, 0.26] call _mix };',
            'default { [_th get "surface", _acc, 0.40] call _mix };',
            'case "DAY": { [_acc, 0.36] call _mul };',
            'case "NVG": { _th get "text" };',
            'default { [_acc, 0.62] call _lift };',
        ] as $line) {
            self::assertStringContainsString($line, $theme);
        }
        $accSrc = (string) file_get_contents(self::MAIN . '/functions/ui/fn_accent.sqf');
        preg_match_all('/\[\[([0-9.]+), ([0-9.]+), ([0-9.]+), 1\], "#/', $accSrc, $m, PREG_SET_ORDER);
        self::assertCount(6, $m);
        $mix = static fn (array $a, array $b, float $k): array => [$a[0] + ($b[0] - $a[0]) * $k, $a[1] + ($b[1] - $a[1]) * $k, $a[2] + ($b[2] - $a[2]) * $k];
        $dark = $this->tokens('default {');
        $nvg = $this->tokens('case "NVG"');
        foreach ($m as $row) {
            $acc = [(float) $row[1], (float) $row[2], (float) $row[3]];
            $cases = [
                'sombre' => [$mix($dark['surface'], $acc, 0.26), $mix($dark['surface'], $acc, 0.40), array_map(static fn (float $v): float => $v + (1 - $v) * 0.62, $acc)],
                'jour' => [$mix([1, 1, 1], $acc, 0.20), $mix([1, 1, 1], $acc, 0.34), array_map(static fn (float $v): float => $v * 0.36, $acc)],
                'nuit JVN' => [$mix([0, 0, 0], [0.36, 0.78, 0.42], 0.16), $mix([0, 0, 0], [0.36, 0.78, 0.42], 0.26), $nvg['text']],
            ];
            foreach ($cases as $name => [$soft, $hi, $ink]) {
                self::assertGreaterThanOrEqual(4.5, round(self::contrast($ink, $soft), 2), "{$name}, accent {$row[0]} : repos");
                self::assertGreaterThanOrEqual(4.5, round(self::contrast($ink, $hi), 2), "{$name}, accent {$row[0]} : survol");
            }
        }
        $lock = (string) file_get_contents(self::MAIN . '/functions/ui/fn_lockScreen.sqf');
        self::assertStringContainsString('_ul ctrlSetTextColor (_th get "onAccentSoft");', $lock);
        self::assertStringNotContainsString('["pill", _unlockRect, _th get "accent"] call _tex;', $lock);
    }

    /**
     * App CAS (fn_firesCff, fn_pageFires) : textes des champs, boutons neutres, panneaux et bouton rouge TIR lisibles à 4,5
     * dans les trois thèmes. L'écran portait des couleurs fixes (encre sombre sur champs sombres, texte noir sur l'accent).
     */
    #[DataProvider('themes')]
    public function testAppCasLisible(string $marker): void
    {
        $t = $this->tokens($marker);
        $dark = $marker !== 'case "DAY"';
        foreach (['bg0', 'bg1', 'surface', 'surfaceAlt', 'surface2', 'field'] as $bg) {
            $c = self::contrast($t['text'], $t[$bg]);
            self::assertGreaterThanOrEqual(4.5, round($c, 2), "text sur {$bg} : contraste " . round($c, 2));
        }
        // Libellés d'aide (textDim) sur les panneaux et le fond de page de l'app.
        foreach (['bg1', 'surface', 'surfaceAlt'] as $bg) {
            self::assertGreaterThanOrEqual(4.5, round(self::contrast($t['textDim'], $t[$bg]), 2), "textDim sur {$bg}");
        }
        // Bouton TIR : fond d'alerte assombri (sombre, JVN) ou tel quel (jour), encre du thème ou blanche (formules de fn_firesCff).
        $bg = $dark ? array_map(static fn (float $v): float => $v * 0.6, $t['alert']) : $t['alert'];
        $ink = $dark ? $t['text'] : $t['onState'];
        self::assertGreaterThanOrEqual(4.5, round(self::contrast($ink, $bg), 2), "TIR : encre sur fond d'alerte");
    }

    public function testAppCasSansCouleurFixe(): void
    {
        $dir = self::MAIN . '/functions/pages/';
        $cff = (string) file_get_contents($dir . 'fn_firesCff.sqf');
        $tabs = (string) file_get_contents($dir . 'fn_pageFires.sqf');
        self::assertStringContainsString('private _cSel = _th get "accentSoftHi";', $cff);
        self::assertStringContainsString('private _cOnSel = _th get "onAccentSoft";', $cff);
        self::assertStringContainsString('format ["[%1]", ctrlText _b]', $cff);
        self::assertStringNotContainsString('_cOnAcc', $cff);
        self::assertStringNotContainsString('private _cInk = COMSPEC_ATAK_Th get "onState";', $cff);
        self::assertStringNotContainsString('[0.03, 0.05, 0.04, 1]', $cff . $tabs);
        self::assertStringContainsString('_th get "onAccentSoft"', $tabs);
        self::assertStringContainsString('Appuyez sur CALCULER après avoir renseigné la pièce et la cible', $cff);
    }

    public function testFiresCourtChoixDesDestinataires(): void
    {
        $dir = self::MAIN . '/functions/tactical/';
        $q = (string) file_get_contents($dir . 'fn_firesQuick.sqf');
        $page = (string) file_get_contents($dir . 'fn_pageQuickMap.sqf');
        foreach (['case "cands"', 'case "destToggle"', 'case "destAll"', 'case "picker"', 'case "search"'] as $a) {
            self::assertStringContainsString($a, $q);
        }
        self::assertStringContainsString('(netId (_x select 1)) in _sel', $q);
        self::assertStringContainsString('["picker"] call comspec_atak_native_fnc_firesQuick', $page);
        self::assertStringContainsString('Aucun pilote ni JTAC avec téléphone en ligne', $page);
        $en = (string) file_get_contents(self::MAIN . '/i18n/en.txt');
        foreach (['DESTINATAIRES : AUCUN EN LIGNE', 'AZ : azimut de la cible en mils, ex. 1600', 'Appuyez sur CALCULER après avoir renseigné la pièce et la cible : la solution de tir apparaît ici.'] as $k) {
            self::assertStringContainsString('["' . $k . '", "', $en);
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
