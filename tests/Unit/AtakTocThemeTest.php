<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Thème des écrans TOC et du PC du poste (refonte du 11/11, « un rendu pro et propre ») : fn_tocTheme est la seule source
 * des couleurs, tailles de texte, polices, régions et colonnes ; les maquettes (tools/toc_mockups.py) lisent les mêmes
 * tables. Vérifié ici, sans le jeu : contraste du texte, tailles minimales à 3-6 m, régions dans le cadre et sans
 * chevauchement (FLASH compris), colonnes qui tiennent, aucune couleur écrite à la main hors du thème, textes FR + EN.
 */
final class AtakTocThemeTest extends TestCase
{
    private const MAIN = __DIR__ . '/../../mod/COMSPEC_ATAK_Native/Sources/addons/main/';
    private const TOC = self::MAIN . 'functions/toc/';

    private static function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents($path));
    }

    /** @return array<int, mixed> */
    private static function table(string $name): array
    {
        $src = self::read(self::TOC . 'fn_tocTheme.sqf');
        self::assertSame(1, preg_match('/private ' . preg_quote($name, '/') . ' = (\[.*?\]);/s', $src, $m), "table {$name} introuvable");
        $t = json_decode($m[1], true);
        self::assertIsArray($t, "table {$name} : JSON invalide (" . json_last_error_msg() . ')');

        return $t;
    }

    /** @return array<string, string> */
    private static function tokens(): array
    {
        $out = [];
        foreach (self::table('_tokens') as [$k, $v]) {
            $out[$k] = $v;
        }

        return $out;
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

    /** @param array{float, float, float} $fg @param array{float, float, float} $bg @return array{float, float, float} */
    private static function over(array $fg, float $alpha, array $bg): array
    {
        return [$fg[0] * $alpha + $bg[0] * (1 - $alpha), $fg[1] * $alpha + $bg[1] * (1 - $alpha), $fg[2] * $alpha + $bg[2] * (1 - $alpha)];
    }

    public function testTokensMatchTheConfigMacrosOfThePc(): void
    {
        $t = self::tokens();
        foreach (['bg0', 'bg1', 'bg2', 'bg3', 'line', 'text', 'text2', 'muted', 'accent', 'amber', 'red', 'friend', 'lost'] as $k) {
            self::assertArrayHasKey($k, $t, "jeton {$k} manquant");
            self::assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $t[$k]);
        }
        $hpp = self::read(self::MAIN . 'ui/toc_theme.hpp');
        preg_match_all('/#define TOC_(\w+) \{([0-9.]+),([0-9.]+),([0-9.]+),1\}/', $hpp, $m, PREG_SET_ORDER);
        self::assertGreaterThan(10, count($m));
        $byUpper = [];
        foreach ($t as $k => $v) {
            $byUpper[strtoupper($k)] = $v;
        }
        foreach ($m as $row) {
            self::assertArrayHasKey($row[1], $byUpper, "TOC_{$row[1]} sans jeton dans fn_tocTheme");
            $c = self::rgb($byUpper[$row[1]]);
            for ($i = 0; $i < 3; $i++) {
                self::assertEqualsWithDelta($c[$i], (float) $row[$i + 2], 0.002, "TOC_{$row[1]} différent du jeton");
            }
        }
        self::assertStringContainsString('#include "toc_theme.hpp"', self::read(self::MAIN . 'ui/controls.hpp'));
    }

    public function testTextContrastOnEveryBackground(): void
    {
        $t = self::tokens();
        $c = static fn (string $k): array => self::rgb($t[$k]);
        // Texte courant (4,5:1 au moins) sur l'écran, les panneaux, les cases en relief et le bandeau FLASH.
        foreach (['bg0', 'bg1', 'bg2', 'redDim'] as $bg) {
            foreach (['text', 'text2', 'muted', 'accent', 'amber', 'red', 'blue', 'friend', 'lost'] as $fg) {
                self::assertGreaterThanOrEqual(4.5, self::contrast($c($fg), $c($bg)), "{$fg} sur {$bg}");
            }
        }
        // Pastilles d'état : texte du jeton sur le jeton à 16 % sur le panneau.
        foreach (['accent', 'amber', 'red', 'blue', 'muted'] as $fg) {
            self::assertGreaterThanOrEqual(4.5, self::contrast($c($fg), self::over($c($fg), 0.16, $c('bg1'))), "pastille {$fg}");
        }
        // Bouton principal du PC (texte BG0 sur l'accent), onglet choisi (accent sur BG2), survol (texte sur BG3).
        self::assertGreaterThanOrEqual(4.5, self::contrast($c('bg0'), $c('accent')));
        self::assertGreaterThanOrEqual(4.5, self::contrast($c('accent'), $c('bg2')));
        self::assertGreaterThanOrEqual(4.5, self::contrast($c('text'), $c('bg3')));
        // Grand texte (titres) : 3:1 au moins, même en sourdine sur le survol.
        self::assertGreaterThanOrEqual(3.0, self::contrast($c('muted'), $c('bg3')));
        // Plaques de la carte (BG0 à 82 %) sur une image claire : texte toujours lisible.
        $plate = self::over($c('bg0'), 0.82, [0.9, 0.9, 0.9]);
        foreach (['text', 'amber', 'lost'] as $fg) {
            self::assertGreaterThanOrEqual(4.5, self::contrast($c($fg), $plate), "plaque {$fg}");
        }
    }

    public function testTypeScaleAndFonts(): void
    {
        $type = [];
        foreach (self::table('_type') as [$k, $px, $font]) {
            $type[$k] = [$px, $font];
        }
        $fonts = [];
        foreach (self::table('_fonts') as [$k, $v]) {
            $fonts[$k] = $v;
        }
        self::assertSame(['sans' => 'RobotoCondensed', 'sansBold' => 'RobotoCondensedBold', 'mono' => 'EtelkaMonospacePro', 'monoBold' => 'EtelkaMonospaceProBold'], $fonts);
        $space = [];
        foreach (self::table('_space') as [$k, $v]) {
            $space[$k] = $v;
        }
        // Écran mural lu à 3-6 m : rien sous le texte courant (28 px virtuels sur 900).
        foreach ($type as $k => [$px, $font]) {
            self::assertGreaterThanOrEqual($space['minText'], $px, "style {$k} sous le minimum mural");
            self::assertArrayHasKey($font, $fonts, "style {$k} : police inconnue");
        }
        self::assertSame(28, $type['body'][0]);
        self::assertGreaterThan($type['title'][0], $type['display'][0]);
        // Tout style employé par le rendu des écrans existe.
        $brd = self::read(self::TOC . 'fn_tocBoard.sqf');
        preg_match_all('/, "(\w+)", (?:"\w+"|_\w+|\[[^\]]*\] select [^,]+)(?:, "[LRCP]"|, \["L", "R"\] select \([^)]*\))?(?:, true)?\] call _text;/', $brd, $m);
        self::assertGreaterThan(10, count($m[1]));
        foreach (array_diff(array_unique($m[1]), array_keys(self::tokens())) as $st) {
            // (style choisi par « select » : la lecture ci-dessus prend alors la couleur, écartée ici)
            self::assertArrayHasKey($st, $type, "style « {$st} » employé dans fn_tocBoard mais absent du thème");
        }
    }

    /** @return iterable<string, array{string}> */
    public static function walls(): iterable
    {
        foreach (['FRAME', 'SPLIT', 'MAP', 'SQUAD', 'FEED', 'NET', 'ORBAT', 'CLOCK', 'LOG', 'EXCHECK', 'KILLS', 'REQ', 'MOSAIC', 'STRIP', 'PLATE'] as $v) {
            yield $v => [$v];
        }
    }

    /**
     * @dataProvider walls
     */
    public function testEveryRegionIsInsideTheFrameWithoutOverlap(string $view): void
    {
        $walls = [];
        foreach (self::table('_walls') as [$k, $regs]) {
            $walls[$k] = $regs;
        }
        $flash = [];
        foreach (self::table('_flashRule') as [$k, $fixed, $shrink]) {
            $flash[$k] = [$fixed, $shrink];
        }
        self::assertArrayHasKey($view, $walls);
        $wide = in_array($view, ['STRIP', 'PLATE'], true);
        $hgt = $wide ? 400 : 900;
        // Régions qui se remplacent (une seule montrée à la fois) ou se superposent à dessein.
        $alt = [
            'FRAME' => [['flash', 'content'], ['flashBar', 'content'], ['flashTxt', 'content'], ['flash', 'flashBar'], ['flash', 'flashTxt'], ['title', 'header'], ['clock', 'header'], ['footRule', 'footL'], ['footRule', 'footR']],
            'SQUAD' => [['memArea', 'memAreaCam'], ['memArea', 'cam'], ['memArea', 'crHead'], ['memArea', 'crSub'], ['memArea', 'crRule'], ['memArea', 'crText'], ['cam', 'crHead'], ['cam', 'crSub'], ['cam', 'crRule'], ['cam', 'crText']],
            'EXCHECK' => [['rule', 'prog']],
        ];
        $ok = static function (string $a, string $b) use ($alt, $view): bool {
            foreach ($alt[$view] ?? [] as [$x, $y]) {
                if (($a === $x && $b === $y) || ($a === $y && $b === $x)) {
                    return true;
                }
            }

            return false;
        };
        foreach ([false, true] as $fl) {
            if ($fl && !isset($flash[$view])) {
                continue;
            }
            $r = [];
            foreach ($walls[$view] as [$k, $x, $y, $w, $h]) {
                if ($fl) {
                    [$fixed, $shrink] = $flash[$view];
                    $isFixed = (bool) array_filter($fixed, static fn (string $p): bool => str_starts_with($k, $p));
                    if (!$isFixed) {
                        $y += 52;
                        if (in_array($k, $shrink, true)) {
                            $h -= 52;
                        }
                    }
                }
                $r[$k] = [$x, $y, $w, $h];
                $tag = $fl ? ' (sous FLASH)' : '';
                self::assertGreaterThan(0, $w, "{$view}.{$k} sans largeur");
                self::assertGreaterThan(0, $h, "{$view}.{$k} sans hauteur{$tag}");
                self::assertTrue($x >= 0 && $y >= 0 && $x + $w <= 1600 && $y + $h <= $hgt, "{$view}.{$k} hors du cadre{$tag}");
                if (!$wide && $view !== 'FRAME') {
                    // Entre l'en-tête (74 px) et le pied (848 px) ; sous un FLASH, sous le bandeau (126 px).
                    self::assertGreaterThanOrEqual($fl ? 126 : 74, $y, "{$view}.{$k} sous l'en-tête{$tag}");
                    self::assertLessThanOrEqual(848, $y + $h, "{$view}.{$k} sur le pied{$tag}");
                }
            }
            $keys = array_keys($r);
            for ($i = 0; $i < count($keys); $i++) {
                for ($j = $i + 1; $j < count($keys); $j++) {
                    [$a, $b] = [$r[$keys[$i]], $r[$keys[$j]]];
                    $over = $a[0] < $b[0] + $b[2] && $b[0] < $a[0] + $a[2] && $a[1] < $b[1] + $b[3] && $b[1] < $a[1] + $a[3];
                    self::assertTrue(!$over || $ok($keys[$i], $keys[$j]), "{$view} : « {$keys[$i]} » chevauche « {$keys[$j]} »" . ($fl ? ' sous FLASH' : ''));
                }
            }
        }
    }

    public function testTableColumnsFitTheirAreaAndHaveEnglish(): void
    {
        $walls = [];
        foreach (self::table('_walls') as [$k, $regs]) {
            foreach ($regs as [$n, $x, $y, $w, $h]) {
                $walls[$k][$n] = [$x, $y, $w, $h];
            }
        }
        $en = self::read(self::MAIN . 'i18n/en.txt');
        // Table de colonnes -> largeur de son tableau.
        $areaW = ['GRP_S' => $walls['SPLIT']['grpArea'][2], 'FLUX_S' => $walls['SPLIT']['fluxArea'][2], 'MEM' => $walls['SQUAD']['memArea'][2],
            'FEED' => $walls['FEED']['area'][2], 'NET' => $walls['NET']['area'][2], 'LOG' => $walls['LOG']['area'][2], 'EXCHECK' => $walls['EXCHECK']['area'][2],
            'KILLS' => $walls['KILLS']['area'][2], 'KILLS_G' => $walls['KILLS']['area'][2], 'REQ' => $walls['REQ']['area'][2], 'TIMER' => $walls['CLOCK']['tArea'][2]];
        foreach (self::table('_cols') as [$name, $cols]) {
            self::assertArrayHasKey($name, $areaW, "colonnes {$name} sans tableau");
            $prevEnd = -1;
            foreach ($cols as [$key, $label, $x, $w, $align, $style]) {
                self::assertContains($align, ['L', 'R', 'C', 'P'], "{$name}.{$key}");
                self::assertLessThanOrEqual($areaW[$name], $x + $w, "{$name}.{$key} dépasse son tableau");
                if ($name !== 'TIMER') {
                    self::assertGreaterThanOrEqual($prevEnd, $x, "{$name}.{$key} chevauche la colonne précédente");
                    $prevEnd = $x + $w;
                }
                if ($label !== '') {
                    // En-tête en légende (28 px, Roboto Condensed : 0,45 x 28 px par caractère au plus) dans sa colonne.
                    self::assertLessThanOrEqual($w - 8, mb_strlen($label) * 0.45 * 28, "{$name} : en-tête « {$label} » coupé");
                    if (!in_array($label, ['TYPE', 'TOTAL'], true)) {
                        self::assertStringContainsString('["' . $label . '", "', $en, "en-tête « {$label} » sans anglais");
                    }
                }
            }
        }
    }

    public function testNoHardCodedColourOutsideTheTheme(): void
    {
        $screen = self::read(self::TOC . 'fn_tocScreen.sqf');
        $a = strpos($screen, '    case "build": {');
        $b = strpos($screen, '    case "title": {');
        self::assertNotFalse($a);
        self::assertNotFalse($b);
        $files = [
            'fn_tocScreen.sqf (build, draw, squadText)' => substr($screen, $a, $b - $a),
            'fn_tocBoard.sqf' => self::read(self::TOC . 'fn_tocBoard.sqf'),
            'fn_tocLaptop.sqf' => self::read(self::TOC . 'fn_tocLaptop.sqf'),
            'fn_tocHallUi.sqf' => self::read(self::TOC . 'fn_tocHallUi.sqf'),
        ];
        foreach ($files as $name => $src) {
            $src = (string) preg_replace(['~/\*.*?\*/~s', '~^\s*//.*$~m'], '', $src);
            foreach (explode("\n", $src) as $n => $line) {
                if (str_contains($line, 'coque')) {
                    continue; // coque du PC portable : couleur de l'objet, pas de l'écran
                }
                self::assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{6}\b/', $line, "{$name} l." . ($n + 1) . ' : couleur #rrggbb hors du thème');
                if (str_contains($line, 'Color')) {
                    self::assertDoesNotMatchRegularExpression('/\[(?:0|1|0?\.\d+|1\.0), (?:0|1|0?\.\d+|1\.0), (?:0|1|0?\.\d+|1\.0), (?:0|1|0?\.\d+)\]/', $line, "{$name} l." . ($n + 1) . ' : couleur RGBA hors du thème');
                }
            }
        }
    }

    public function testBoardTextsHaveEnglishAndNoGamePopup(): void
    {
        $brd = self::read(self::TOC . 'fn_tocBoard.sqf');
        $en = self::read(self::MAIN . 'i18n/en.txt');
        $texts = [];
        // Titres de section, libellés de cases, textes d'une ligne écrits en dur.
        preg_match_all('/\["([^"<]{3,})", "[^"]*"\] call _sectionW/', $brd, $m);
        $texts = array_merge($texts, $m[1]);
        preg_match_all('/\["", "([^"<]{3,})", "[^"]*", "\w+"\] call _section/', $brd, $m);
        $texts = array_merge($texts, $m[1]);
        preg_match_all('/, "([^"<%]{3,})", "(?:heading|body|bodyBold|caption|title|stripLabel)", "\w+"/', $brd, $m);
        $texts = array_merge($texts, $m[1]);
        preg_match_all('/\[[^,\[\]]+, "([a-zà-ÿ][^"]{2,})", "(?:accent|amber|red|muted|text|text2|blue)"\]/u', $brd, $m);
        $texts = array_merge($texts, $m[1]);
        $same = ['ZULU', 'LOCAL', 'FLASH', 'TOTAL', 'LOCAL (MISSION)'];
        $texts = array_values(array_diff(array_unique($texts), $same));
        self::assertGreaterThan(25, count($texts));
        foreach ($texts as $t) {
            self::assertStringContainsString('["' . $t . '", "', $en, "« {$t} » sans anglais dans i18n/en.txt");
        }
        foreach (['fn_tocBoard.sqf', 'fn_tocTheme.sqf'] as $f) {
            self::assertDoesNotMatchRegularExpression('/\b(hint|hintSilent|systemChat|titleText|cutText|BIS_fnc_showNotification)\b/', self::read(self::TOC . $f), $f);
        }
    }

    public function testMockupsReadTheSameTables(): void
    {
        $py = self::read(__DIR__ . '/../../mod/COMSPEC_ATAK_Native/tools/toc_mockups.py');
        foreach (['"_tokens"', '"_type"', '"_walls"', '"_cols"', 'private _grid = createHashMapFromArray', 'private _layout = createHashMapFromArray', 'private _btnDefs'] as $needle) {
            self::assertStringContainsString($needle, $py, "le générateur de maquettes ne lit pas {$needle}");
        }
        $doc = self::read(__DIR__ . '/../../docs/TOC-THEME.md');
        foreach (array_keys(self::tokens()) as $k) {
            self::assertStringContainsString('`' . $k . '`', $doc, "jeton {$k} absent de docs/TOC-THEME.md");
        }
        self::assertStringContainsString('class tocTheme {};', self::read(self::MAIN . 'config.cpp'));
    }
}
