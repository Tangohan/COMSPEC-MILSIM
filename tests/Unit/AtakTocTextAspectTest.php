<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Format des caractères sur les écrans TOC qui ne sont pas en 16:9 (retour de TTRD du 11/11, capture d'une salle : le
 * bandeau LED « MESSAGE » lu « E S S », lettres étirées ; plaques « ÉLÉMENT 1 » coupées à « ÉLÉME » ; écran d'escouade
 * « 1 perdus » sans aucun membre listé).
 * Cause : dans une texture d'interface, le moteur dessine le texte comme sur un écran 4:3 (largeur d'un caractère =
 * hauteur x 3/4 x chasse, en part de la largeur de la texture). Sur une dalle de rapport R il est étiré de R / (4/3).
 * Remède vérifié ici, par lecture du code SQF et des tables JSON qu'il contient : une image par caractère, dans une
 * case calculée depuis le vrai rapport de la dalle (fn_tocText : tables _panels, _fits, _glyphs, _k), règle
 * d'ajustement (tient : fixe ; trop long : réduit ; plus long : défilement sans couture) et plaques réduites puis « … ».
 */
final class AtakTocTextAspectTest extends TestCase
{
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';
    private const TOC = self::MAIN . 'functions/toc/';

    private static function root(): string
    {
        return dirname(__DIR__, 2) . '/';
    }

    private static function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(self::root() . $path));
    }

    /** Table JSON du code SQF : « // ---- TABLE _nom » … « private _nom = [ … ]; » … « // ---- FIN ». */
    private static function table(string $src, string $name): array
    {
        self::assertSame(1, preg_match('/\/\/ ---- TABLE ' . preg_quote($name, '/') . ' .*?private ' . preg_quote($name, '/') . ' = (\[.*?\]);\n\s*\/\/ ---- FIN/s', $src, $m), $name);
        $data = json_decode($m[1], true);
        self::assertIsArray($data, $name . ' : JSON strict attendu (' . json_last_error_msg() . ')');

        return $data;
    }

    /** Format de fn_tocScreen (_formats) pour un rapport de dalle : [rapport maximal, format, texture, l / h, …]. */
    private static function formatOf(array $formats, float $ratio): array
    {
        foreach ($formats as $f) {
            if ($ratio < $f[0]) {
                return $f;
            }
        }

        return $formats[0];
    }

    /** Ligne de _panels qui a cette texture. */
    private static function panelOf(array $panels, string $spec): ?array
    {
        foreach ($panels as $p) {
            if (sprintf('#(rgb,%d,%d,1)', $p[3], $p[4]) === $spec) {
                return $p;
            }
        }

        return null;
    }

    /**
     * Même règle que _planOf (fn_tocText.sqf) : [0 fixe une ligne | 1 fixe deux lignes | 2 défilement, hauteur de case].
     * $wi : largeur de la fenêtre du texte ; $ux : largeur d'une « hauteur de dalle » (1 / rapport pour tout le display).
     */
    private static function plan(string $text, float $wi, float $ux, float $cellW, array $fit, bool $always = false): array
    {
        [, $f1, $fMin, $f2] = $fit;
        $chars = mb_str_split($text);
        $n = count($chars);
        $w1 = $n * $cellW * $ux;
        if ($always) {
            return [2, $f1];
        }
        $fFit = $wi / max($w1, 1e-6);
        if ($fFit >= $f1) {
            return [0, $f1];
        }
        if ($f2 > 0 && $fFit < $f2) {
            $best = -1;
            for ($i = 1; $i <= $n - 2; $i++) {
                if ($chars[$i] === ' ' && ($best < 0 || abs($i - $n / 2) < abs($best - $n / 2))) {
                    $best = $i;
                }
            }
            if ($best > 0) {
                $fL = min($f2, $fFit * $n / max($best, $n - $best - 1));
                if ($fL >= $fMin) {
                    return [1, $fL];
                }
            }
        }
        if ($fFit >= $fMin) {
            return [0, $fFit];
        }

        return [2, $f1];
    }

    /** Fenêtre du texte d'un panneau plein display : largeur (part de la largeur de dalle) pour un rapport et une règle. */
    private static function window(float $ratio, array $fit): float
    {
        [, , , , $ins, $bth, $padX] = $fit;

        return 1 - 2 * ($ins + $bth + $padX) / $ratio;
    }

    /** Même règle que « label » (fn_tocText.sqf) : [nombre de caractères montrés, réduction, coupé avec « … »]. */
    private static function label(int $n, float $rw, float $rh, float $asp, float $cellW, float $min): array
    {
        $cw = $cellW * $rh / $asp;
        $fit = min($rw / (max($n, 1) * $cw), 1.0);
        if ($fit >= $min) {
            return [$n, $fit, false];
        }
        $keep = max((int) floor($rw / ($cw * $min)) - 1, 1);

        return [$keep + 1, $min, true];
    }

    public function testEngineTextIsStretchedOnEveryPanelSoLedTextUsesOneImagePerCharacter(): void
    {
        $txt = self::read(self::TOC . 'fn_tocText.sqf');
        $k = array_column(self::table($txt, '_k'), 1, 0);
        // Le moteur dessine le texte d'une texture d'interface pour un écran 4:3.
        self::assertEqualsWithDelta(4 / 3, $k['uiBox'], 0.001);
        $panels = self::table($txt, '_panels');
        self::assertSame(['COL', 'STD', 'STRIP', 'LED8', 'LED16', 'LED16LOW'], array_column($panels, 0));
        foreach ($panels as [$name, $kind, $ratio, $texW, $texH]) {
            self::assertContains($kind, ['LED', 'STD', 'COL'], $name);
            // Texture en puissances de 2, pixels (presque) carrés une fois posée sur la dalle.
            foreach ([$texW, $texH] as $px) {
                self::assertSame(0, $px & ($px - 1), "$name : $px");
            }
            $pixelAspect = ($texW / $texH) / $ratio;
            self::assertGreaterThan(0.88, $pixelAspect, "$name : pixels trop étroits");
            self::assertLessThan(1.13, $pixelAspect, "$name : pixels trop larges");
            // Étirement du texte du jeu sur cette dalle : R / (4/3). Jamais 1 : aucune hauteur de police ne le corrige.
            $stretch = $ratio / $k['uiBox'];
            self::assertGreaterThan(0.25, abs($stretch - 1), "$name : étirement du texte du jeu $stretch");
            if ($kind === 'LED') {
                self::assertGreaterThanOrEqual(3.0, $stretch, "$name : bandeau, texte du jeu illisible");
            }
        }
        // D'où une image par caractère, dans une case calculée depuis le rapport RÉEL de la dalle.
        self::assertStringContainsString('private _ux = _rw / (_pxA max 0.1);', $txt);
        self::assertStringContainsString('private _step = _cellW * _f1 * _ux;', $txt);
        self::assertStringContainsString('[_d, _c, _lc, _cellW * _fz * _ux, _fz * _rh, _rgba] call _fill;', $txt);
        self::assertStringContainsString('_dsp ctrlCreate ["RscPicture", -1, _grp]', $txt);
        self::assertStringContainsString('_c ctrlSetPosition [_forEachIndex * _cw, 0, _cw, _ch];', $txt);
        // L'écran passe le rapport de la dalle (premier élément du format), pas celui de la texture.
        self::assertStringContainsString('_fmt params [["_ratio", 1.778], ["_spec", ""], "", ["_kind", "STD"]];', $txt);
        self::assertMatchesRegularExpression('/\[_d, controlNull, \[0, 0, 1, 1\], _ratio, \["LED", _kind\] select \(_kind in \["STD", "COL"\]\), _cfg, .*"COMSPEC_ATAK_TxtUi", _o, _texH\] call _render;/', $txt);
        // Case d'un caractère : sur la dalle elle a le format de l'image (cellW), quel que soit le format.
        $fits = array_column(self::table($txt, '_fits'), null, 0);
        foreach ($panels as [$name, $kind, $ratio, $texW, $texH]) {
            [, $f1, $fMin] = $fits[$kind];
            $cellWidthOfPanel = $f1 * $k['cellW'] / $ratio;           // part de la largeur de dalle
            $physical = ($cellWidthOfPanel * $ratio) / $f1;            // largeur / hauteur réelles de la case
            self::assertEqualsWithDelta($k['cellW'], $physical, 1e-9, $name);
            // En pixels de texture : même format à la déformation des pixels près, et assez de pixels pour être lisible.
            $pxW = $cellWidthOfPanel * $texW;
            $pxH = $f1 * $texH;
            self::assertEqualsWithDelta($k['cellW'] * ($texW / $texH) / $ratio, $pxW / $pxH, 1e-9, $name);
            self::assertGreaterThanOrEqual($k['minPx'], $fMin * $texH, "$name : plus petite case trop petite");
            self::assertLessThanOrEqual(256 * 1.6, $pxH, "$name : case plus grande que l'image du caractère (floue)");
        }
        self::assertStringContainsString('if (_texH > 0) then { _fMin = (_fMin max ((_kv get "minPx") / _texH)) min _f1; };', $txt);
    }

    public function testEveryScreenClassMapsToAPanelOfItsTrueRatio(): void
    {
        $txt = self::read(self::TOC . 'fn_tocText.sqf');
        $scr = self::read(self::TOC . 'fn_tocScreen.sqf');
        $hpp = self::read(self::MAIN . 'toc_screens3d.hpp');
        $panels = self::table($txt, '_panels');
        $formats = self::table($scr, '_formats');
        // Chaque texture de fn_tocScreen a sa ligne, du même type de panneau, au rapport de dalle dans la plage du format.
        $low = 0.0;
        foreach ($formats as [$max, $kind, $spec]) {
            $p = self::panelOf($panels, $spec);
            self::assertNotNull($p, "texture $spec absente de _panels");
            self::assertSame($kind === 'STRIP' ? 'LED' : $kind, $p[1], $spec);
            self::assertGreaterThanOrEqual($low, $p[2], $spec);
            self::assertLessThan($max, $p[2], $spec);
            $low = $max;
        }
        // Repli 2048 d'un bandeau 16:1 dont la texture 4096 est refusée.
        self::assertSame(1, preg_match('/_spec = "(#\(rgb,2048,128,1\))"; \};\n\s*\[_rat, _spec, _pxA, _kind, _ok, _def\]/', $scr, $m));
        $lowTex = self::panelOf($panels, $m[1]);
        self::assertNotNull($lowTex);
        self::assertEqualsWithDelta(16.0, $lowTex[2], 0.001);
        // Classes d'écran : rapport déclaré (comspec_screenRatio, comspec_screenPanelRatio ; 16:9 sans indication).
        preg_match_all('/class (COMSPEC_ATAK_Screen_\w+): (\w+) \{(.*?)\n\};/s', $hpp, $cls, PREG_SET_ORDER);
        $ratios = [];
        $parents = [];
        foreach ($cls as [, $name, $parent, $body]) {
            $parents[$name] = $parent;
            if (preg_match('/comspec_screenPanelRatio\[\]=\{([^}]*)\}/', $body, $pm)) {
                $ratios[$name] = array_map('floatval', explode(',', $pm[1]));
            } elseif (preg_match('/comspec_screenRatio=([\d.]+);/', $body, $rm)) {
                $ratios[$name] = [(float) $rm[1]];
            }
        }
        $ratioOf = static function (string $name) use (&$ratios, &$parents): array {
            for ($i = 0; $i < 6 && $name !== ''; $i++) {
                if (isset($ratios[$name])) {
                    return $ratios[$name];
                }
                $name = $parents[$name] ?? '';
            }

            return [1.778];
        };
        $expect = [
            'COMSPEC_ATAK_Screen_LedText8' => ['LED8'], 'COMSPEC_ATAK_Screen_LedText8_Ceiling' => ['LED8'],
            'COMSPEC_ATAK_Screen_LedText16' => ['LED16'], 'COMSPEC_ATAK_Screen_LedText16_Ceiling' => ['LED16'],
            'COMSPEC_ATAK_Screen_ClockStrip' => ['STRIP'], 'COMSPEC_ATAK_Screen_NamePlate' => ['STRIP'],
            'COMSPEC_ATAK_Screen_Led220' => ['STD'], 'COMSPEC_ATAK_Screen_Led220_Truss' => ['STD'],
            'COMSPEC_ATAK_Screen_Curved' => ['STD', 'COL', 'COL'], 'COMSPEC_ATAK_Screen_Curved_Wall' => ['STD', 'COL', 'COL'],
        ];
        foreach ($expect as $class => $names) {
            self::assertArrayHasKey($class, $parents, "classe $class absente de toc_screens3d.hpp");
            $got = [];
            foreach ($ratioOf($class) as $ratio) {
                $p = self::panelOf($panels, self::formatOf($formats, $ratio)[2]);
                self::assertNotNull($p, "$class : rapport $ratio sans panneau");
                // Le rapport de la table est celui de la dalle de la classe : les cases en sortent sans étirement.
                self::assertEqualsWithDelta($ratio, $p[2], 0.01, "$class : rapport de dalle");
                $got[] = $p[0];
            }
            self::assertSame($names, $got, $class);
        }
    }

    public function testFitRulesShortTextIsStaticLongTextShrinksThenScrolls(): void
    {
        $txt = self::read(self::TOC . 'fn_tocText.sqf');
        $k = array_column(self::table($txt, '_k'), 1, 0);
        $fits = array_column(self::table($txt, '_fits'), null, 0);
        self::assertSame(['LED', 'STD', 'COL'], array_keys($fits));
        foreach ($fits as $kind => [, $f1, $fMin, $f2, $ins, $bth, $padX]) {
            self::assertGreaterThan($fMin, $f1, $kind);
            self::assertGreaterThanOrEqual(0.5 * $f1, $fMin, "$kind : jamais moins de la moitié de la grande taille");
            // La case (et deux lignes s'il y en a) tient dans la hauteur, à l'intérieur de la bordure.
            self::assertLessThanOrEqual(1 - 2 * ($ins + $bth), $f1, $kind);
            self::assertLessThanOrEqual(1 - 2 * ($ins + $bth), 2.08 * $f2, $kind);
            self::assertTrue($f2 === 0 || ($f2 < $f1 && $f2 >= $fMin), $kind);
            self::assertGreaterThan(0, $padX, $kind);
        }
        self::assertSame(0, $fits['LED'][3], 'un bandeau reste sur une ligne');
        // La règle du code.
        self::assertStringContainsString('if (_always) exitWith { [2, _f1, []] };', $txt);
        self::assertStringContainsString('private _fFit = _wi / (_w1 max 1e-6);', $txt);
        self::assertStringContainsString('if (_fFit >= _f1) exitWith { [0, _f1, []] };', $txt);
        self::assertStringContainsString('if (_fFit >= _fMin) exitWith { [0, _fFit, []] };', $txt);
        self::assertStringContainsString('private _w1 = (count (toArray _x)) * _cellW * _ux;', $txt);
        // Rejouée : « ÉLÉMENT » et « MESSAGE » tiennent, fixes et à la grande taille, sur 4:1, 8:1 et 16:1.
        $c = $k['cellW'];
        foreach ([4.0, 8.0, 16.0] as $r) {
            foreach (['ÉLÉMENT', 'MESSAGE', 'TOC'] as $word) {
                self::assertSame([0, $fits['LED'][1]], self::plan($word, self::window($r, $fits['LED']), 1 / $r, $c, $fits['LED']), "$word sur $r:1");
            }
        }
        // Le texte de la salle posée (« {poste} · {dtg} », 24 caractères) : fixe sur le bandeau 8:1, réduit pour tenir.
        [$mode, $f] = self::plan('MESSAGE · 112037Z NOV 26', self::window(8.0, $fits['LED']), 1 / 8, $c, $fits['LED']);
        self::assertSame(0, $mode);
        self::assertLessThan($fits['LED'][1], $f);
        self::assertGreaterThanOrEqual($fits['LED'][2], $f);
        self::assertEqualsWithDelta(self::window(8.0, $fits['LED']), 24 * $c * $f / 8, 1e-9, 'réduit exactement à la largeur de la fenêtre');
        // Bien plus long : défilement, à la grande taille ; « toujours défiler » : même un mot.
        $long = 'RASSEMBLEMENT DE TOUS LES CHEFS DE GROUPE EN SALLE DE BRIEFING À 14H00 ZULU';
        self::assertSame([2, $fits['LED'][1]], self::plan($long, self::window(8.0, $fits['LED']), 1 / 8, $c, $fits['LED']));
        self::assertSame([2, $fits['LED'][1]], self::plan('TOC', self::window(8.0, $fits['LED']), 1 / 8, $c, $fits['LED'], true));
        // Écran 16:9 : une ligne, puis deux lignes (plus grandes qu'une ligne réduite), puis défilement.
        $std = $fits['STD'];
        self::assertSame(0, self::plan('TOC BRAVO', self::window(1.778, $std), 1 / 1.778, $c, $std)[0]);
        [$mode, $f] = self::plan('BRIEFING À 14H00 ZULU EN SALLE', self::window(1.778, $std), 1 / 1.778, $c, $std);
        self::assertSame(1, $mode);
        self::assertGreaterThanOrEqual($std[2], $f);
        self::assertSame(2, self::plan($long . ' ' . $long, self::window(1.778, $std), 1 / 1.778, $c, $std)[0]);
        // Seuils monotones : plus le texte est long, plus on va de « fixe pleine taille » à « réduit » puis « défile ».
        $last = [0, 9.0];
        for ($n = 1; $n <= 80; $n++) {
            [$mode, $f] = self::plan(str_repeat('M', $n), self::window(8.0, $fits['LED']), 1 / 8, $c, $fits['LED']);
            self::assertGreaterThanOrEqual($last[0], $mode, "$n caractères");
            if ($mode === 0) {
                self::assertLessThanOrEqual($last[1], $f, "$n caractères");
                self::assertLessThanOrEqual(self::window(8.0, $fits['LED']) + 1e-9, $n * $c * $f / 8, "$n caractères débordent");
            }
            $last = [$mode, $mode === 0 ? $f : $last[1]];
        }
    }

    public function testMarqueeLoopsSeamlesslyClippedAtThePanelEdges(): void
    {
        $txt = self::read(self::TOC . 'fn_tocText.sqf');
        // Fenêtre : groupe de contrôles (coupe nette aux bords), deux bandes dedans, déplacées d'un bloc.
        self::assertStringContainsString('private _clip = ["RscControlsGroupNoScrollbars", _g] call _mk;', $txt);
        self::assertStringContainsString('private _ga = ["RscControlsGroupNoScrollbars", _clip] call _mk;', $txt);
        self::assertStringContainsString('private _gb = ["RscControlsGroupNoScrollbars", _clip] call _mk;', $txt);
        self::assertStringContainsString('_clip ctrlSetPosition [(_ins + _bth + _padX) * _ux, (_ins + _bth) * _rh, _wi, _hi]; _clip ctrlCommit 0;', $txt);
        // Deux copies espacées de la boucle (texte + blanc, jamais moins que la fenêtre) : sans couture.
        self::assertStringContainsString('private _loop = (_tw + _gap) max _wi;', $txt);
        self::assertStringContainsString('_cb ctrlSetPosition [_px + _lp, (_hi - _lh) / 2, _tw, _lh]; _cb ctrlCommit 0;', $txt);
        self::assertStringContainsString('if (_am isEqualTo 1) then { _cb ctrlSetPosition [_px + _loop, _ya, _wa, _ha]; _cb ctrlCommit 0; };', $txt);
        // Le blanc et la vitesse sont des nombres entiers de cases : départ sur un bord de caractère.
        self::assertStringContainsString('private _gap = (_kv get "gapChars") * _step;', $txt);
        self::assertStringContainsString('private _t0 = (floor (_tNow * _spd / _step)) * _step / _spd;', $txt);
        $k = array_column(self::table($txt, '_k'), 1, 0);
        $fits = array_column(self::table($txt, '_fits'), null, 0);
        $step = $k['cellW'] * $fits['LED'][1] / 8;
        foreach ($k['cps'] as $cps) {
            $spd = $cps * $step;
            foreach ([0.0, 0.13, 1.7, 12.345, 59.99] as $t) {
                $t0 = floor($t * $spd / $step) * $step / $spd;
                self::assertLessThanOrEqual($t, $t0);
                self::assertLessThan(1 / $cps + 1e-9, $t - $t0, 'au plus un caractère de retard sur l\'heure du serveur');
                $cells = ($t0 * $spd) / $step;
                self::assertEqualsWithDelta(round($cells), $cells, 1e-6, 'départ sur un bord de caractère');
            }
        }
        // Une largeur de texte exacte : chasse fixe, donc nombre de caractères x case (plus d'estimation).
        self::assertStringContainsString('(count _lc) * _cellW * _fz * _ux', $txt);
        // Un texte de même longueur (heure qui avance) ne relance pas le défilement.
        self::assertStringContainsString('private _sig = [[_txt, count _cps] select _gl, _kind, _speed, _always, _multi, _mi, round (_rw * 1e4), _colK, _gl];', $txt);
    }

    public function testEveryGlyphOfTheTableHasItsImage(): void
    {
        $txt = self::read(self::TOC . 'fn_tocText.sqf');
        $gen = self::read('mod/COMSPEC_ATAK_Native/tools/gen_toc_led_glyphs.py');
        $glyphs = self::table($txt, '_glyphs');
        $k = array_column(self::table($txt, '_k'), 1, 0);
        // Mêmes plages et même case que le générateur.
        self::assertSame(1, preg_match('/^RANGES = (\[\[.*?\]\])$/ms', $gen, $m));
        self::assertSame($glyphs, json_decode(str_replace("\n", ' ', $m[1]), true));
        self::assertSame(1, preg_match('/^ASC, DESC = (\d+), (\d+)$/m', $gen, $m));
        self::assertEqualsWithDelta((int) $m[1] / ((int) $m[1] + (int) $m[2]), $k['capBot'], 0.001);
        self::assertGreaterThan(0.4, $k['cellW']);
        self::assertLessThan(0.6, $k['cellW']);
        self::assertLessThan($k['capBot'], $k['capTop']);
        // Capitales à peu près centrées dans la case : centrer la case centre le texte.
        self::assertEqualsWithDelta(0.5, ($k['capTop'] + $k['capBot']) / 2, 0.02);
        $dir = self::root() . self::MAIN . 'data/toc/led/';
        self::assertStringContainsString('"\z\comspec_atak_native\addons\main\data\toc\led\%1.paa"', $txt);
        self::assertFileExists($dir . '0.paa', 'image du caractère inconnu');
        $n = 0;
        $prev = 32;
        foreach ($glyphs as [$lo, $hi]) {
            self::assertGreaterThan($prev, $lo, 'plages croissantes, sans recouvrement');
            self::assertGreaterThanOrEqual($lo, $hi);
            for ($code = $lo; $code <= $hi; $code++) {
                self::assertFileExists($dir . $code . '.paa', 'caractère ' . mb_chr($code));
                $n++;
            }
            $prev = $hi;
        }
        self::assertGreaterThanOrEqual(200, $n);
        // Aucune image en trop (une image hors table ne serait jamais montrée).
        self::assertCount($n + 1, glob($dir . '*.paa'));
        // Le français des écrans est couvert : majuscules accentuées, ligatures, ponctuation des messages.
        $has = static function (int $code) use ($glyphs): bool {
            foreach ($glyphs as [$lo, $hi]) {
                if ($code >= $lo && $code <= $hi) {
                    return true;
                }
            }

            return false;
        };
        foreach (mb_str_split('AZaz09ÉÈÊÀÂÇÎÔÙÛéèêàâçîôùûœŒ·…«»‹›’-+:/°') as $ch) {
            self::assertTrue($has(mb_ord($ch)), "« $ch » sans image");
        }
        // Un caractère sans image (chinois, cyrillique) : le texte du jeu, mis en page à sa vraie largeur (3/4).
        self::assertStringContainsString('private _allGlyphs = { params ["_cps"]; (_cps findIf { !(_x in _blank) && {!([_x] call _hasGlyph)} }) < 0 };', $txt);
        self::assertStringContainsString('private _ck = (_kv get "charK") / (_kv get "uiBox");', $txt);
    }

    public function testNamePlateAndClockStripUseLedLabelsShrunkThenEllipsis(): void
    {
        $txt = self::read(self::TOC . 'fn_tocText.sqf');
        $brd = self::read(self::TOC . 'fn_tocBoard.sqf');
        $thm = self::read(self::TOC . 'fn_tocTheme.sqf');
        $k = array_column(self::table($txt, '_k'), 1, 0);
        $walls = [];
        foreach (self::table($thm, '_walls') as [$view, $regions]) {
            $walls[$view] = array_column($regions, null, 0);
        }
        self::assertSame(1, preg_match('/case "STRIP": \{(.*?)\n            \};\n            case "PLATE": \{(.*?)\n            \};\n        \};/s', $brd, $m));
        [, $strip, $plate] = $m;
        // Plus aucun texte du jeu sur une face 4:1 (étiré 3 fois) : tout passe par les caractères à LED.
        foreach ([$strip, $plate] as $block) {
            self::assertStringNotContainsString('call _text', $block);
            self::assertStringContainsString('["labelBegin", [_d, [[0, 0, 1600, 400], "bg0"] call _box]] call comspec_atak_native_fnc_tocText;', $block);
            self::assertStringContainsString('_texW / _texH', $block);
        }
        self::assertSame(10, substr_count($strip, '] call _led;'));
        self::assertStringContainsString('"P_name", ("name" call _rg) call _toR, _texW / _texH, toUpper ([_gname, _name] select (_gname isEqualTo "")), "text", "L", 0.5]] call comspec_atak_native_fnc_tocText;', $plate);
        // Fond noir des panneaux à LED, posé par fn_tocText (aucune couleur écrite en dur dans fn_tocBoard).
        self::assertStringContainsString('_face ctrlSetBackgroundColor _ledBg;', $txt);
        // Règle « label » : case à la hauteur du rectangle, large de cellW / rapport ; réduite, puis « … ».
        self::assertStringContainsString('private _cw = _cellW * _rh / (_asp max 0.1);', $txt);
        self::assertStringContainsString('private _fit = (_rw / (((count _cps) max 1) * _cw)) min 1;', $txt);
        self::assertStringContainsString('private _keepN = ((floor (_rw / (_cw * _fit))) - 1) max 1;', $txt);
        self::assertStringContainsString('_cps pushBack 8230;', $txt);
        // Rejouée sur la plaque (1600 x 400 virtuels, région « name ») : rectangles en parts du display.
        [, , , $nw, $nh] = $walls['PLATE']['name'];
        $rw = $nw / 1600;
        $rh = $nh / 400;
        foreach (['ÉLÉMENT 1' => [9, 1.0, false], 'ALPHA 1-1' => [9, 1.0, false]] as $name => $want) {
            self::assertSame($want, self::label(mb_strlen($name), $rw, $rh, 4.0, $k['cellW'], 0.5), $name);
        }
        [$shown, $scale, $cut] = self::label(mb_strlen('SECTION APPUI MORTIER 81'), $rw, $rh, 4.0, $k['cellW'], 0.5);
        self::assertSame([24, false], [$shown, $cut], 'nom long : réduit, entier');
        self::assertGreaterThanOrEqual(0.5, $scale);
        self::assertLessThan(1.0, $scale);
        [$shown, $scale, $cut] = self::label(mb_strlen('GROUPEMENT TACTIQUE INTERARMES DE RECONNAISSANCE NORD'), $rw, $rh, 4.0, $k['cellW'], 0.5);
        self::assertTrue($cut, 'nom trop long : « … »');
        self::assertSame(0.5, $scale);
        self::assertLessThanOrEqual($rw + 1e-9, $shown * $k['cellW'] * $rh / 4.0 * $scale, 'le nom coupé tient dans la plaque');
        self::assertGreaterThanOrEqual(20, $shown);
        // Bandeau horloge : heures et chrono tiennent dans leur région sans être coupés.
        foreach (['zDig' => '20:41:17', 'lDig' => '12:03', 'cDig' => 'H+00:35:42', 'zSub' => 'DTG 112041Z NOV 26', 'lSub' => '11 NOV 2035'] as $region => $sample) {
            [, , , $w, $h] = $walls['STRIP'][$region];
            [$shown, $scale, $cut] = self::label(mb_strlen($sample), $w / 1600, $h / 400, 4.0, $k['cellW'], 0.6);
            self::assertFalse($cut, "$region : « $sample » coupé");
            self::assertSame(mb_strlen($sample), $shown);
        }
    }

    public function testSquadMembersAreListedEvenWhenLost(): void
    {
        $brd = self::read(self::TOC . 'fn_tocBoard.sqf');
        $thm = self::read(self::TOC . 'fn_tocTheme.sqf');
        // La région du tableau des membres est demandée par son nom (texte) : passée en tableau, elle n'était pas trouvée,
        // valait [0, 0, 0, 0] et le tableau sans hauteur ne montrait aucune ligne.
        self::assertStringContainsString('["MEM", [], (["memArea", "memAreaCam"] select _cam) call _rg, _sqNodes apply {', $brd);
        self::assertStringNotContainsString('[["memArea", "memAreaCam"] select _cam] call _rg', $brd);
        self::assertDoesNotMatchRegularExpression('/\[\[[^\[\]]*\] select [^\[\]]*\] call _rg/', $brd, 'une région se demande par son nom, jamais par un tableau');
        self::assertStringContainsString('private _rg = { ["region", [_lv, _this, _isFl]] call comspec_atak_native_fnc_tocTheme };', $brd);
        // Tous les états sont listés, « perdu » compris : aucune ligne n'est filtrée par l'état.
        self::assertStringContainsString('private _stLbl = ["en direct", "ancien", "perdu", "hors ligne"];', $brd);
        self::assertStringContainsString('[["•", _stTok select _f], [_cs, "text"], [[_p] call _gridOf, "text2"], [_stLbl select _f, _stTok select _f]]', $brd);
        // Au moins une ligne de 40 px dans chaque région, caméra du chef comprise, même sous un FLASH (52 px de moins).
        $squad = [];
        foreach (self::table($thm, '_walls') as [$view, $regions]) {
            if ($view === 'SQUAD') {
                $squad = array_column($regions, null, 0);
            }
        }
        foreach (['memArea', 'memAreaCam'] as $region) {
            self::assertGreaterThanOrEqual(1, intdiv($squad[$region][4] - 52, 40), $region);
        }
        // Colonnes du tableau : quatre, comme les lignes construites (pastille, indicatif, grille, état).
        $cols = array_column(self::table($thm, '_cols'), 1, 0);
        self::assertSame(['dot', 'cs', 'grid', 'state'], array_column($cols['MEM'], 0));
    }
}
