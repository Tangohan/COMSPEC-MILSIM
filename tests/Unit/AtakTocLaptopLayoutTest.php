<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * PC du poste TOC (fn_tocLaptop, fn_tocDraw, fn_tocHallUi) : fenêtre cassée en 4.4.0 (retour de TTRD, 11/11) : barre de dessin
 * sombre sans libellés, barre d'outils de la carte sous les onglets, infobulle sur le bouton voisin, image satellite noire la
 * nuit et rendu arrêté 3 s après avoir quitté l'onglet SAT. Causes : « private _h = _H * 0.03 » (le SQF ne distingue pas _h de
 * _H : police à un demi-pixel), positions écrites à la main dans chaque onglet, image « jour » rendue de nuit, bail de 3 s.
 * Ces vérifications l'auraient vu : noms qui ne diffèrent que par la casse, contrôle sans mise en page, bouton sans libellé
 * ou sans anglais, rectangles qui se chevauchent ou sortent de leur zone. Le contrôle en jeu (« audit ») complète.
 */
final class AtakTocLaptopLayoutTest extends TestCase
{
    private const TOC = __DIR__ . '/../../mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/toc/';
    private const EN = __DIR__ . '/../../mod/COMSPEC_ATAK_Native/Sources/addons/main/i18n/en.txt';
    private const FILES = ['fn_tocLaptop.sqf', 'fn_tocDraw.sqf', 'fn_tocHallUi.sqf'];
    /** Fonds et contrôles posés hors des lignes (image satellite, son groupe de découpe et message d'attente : posés sur sBack). */
    private const UNLAID = ['shellOut', 'shellIn', 'screen', 'brand', 'titleBg', 'titleLine', 'rBg', 'sClip', 'sPic', 'sWait'];

    private function src(string $file): string
    {
        $s = str_replace("\r\n", "\n", (string) file_get_contents(self::TOC . $file));

        return (string) preg_replace(['~/\*.*?\*/~s', '~^\s*//.*$~m'], '', $s);
    }

    /** @return array<string, float> */
    private function grid(): array
    {
        self::assertMatchesRegularExpression('/private _grid = createHashMapFromArray \[(.*?)\n\];/s', $this->src('fn_tocLaptop.sqf'));
        preg_match('/private _grid = createHashMapFromArray \[(.*?)\n\];/s', $this->src('fn_tocLaptop.sqf'), $m);
        preg_match_all('/\["(\w+)", ([0-9.]+)\]/', $m[1], $p, PREG_SET_ORDER);
        $g = [];
        foreach ($p as $row) {
            $g[$row[1]] = (float) $row[2];
        }

        return $g;
    }

    /** @return array<string, list<array{0: string|float|int, 1: list<float|int>, 2: list<string|list<mixed>>}>> */
    private function layouts(): array
    {
        preg_match('/private _layout = createHashMapFromArray (\[\n.*?\n        \]);/s', $this->src('fn_tocLaptop.sqf'), $m);
        self::assertNotEmpty($m, 'table _layout introuvable');
        $rows = json_decode($m[1], true);
        self::assertIsArray($rows, 'table _layout illisible : ' . json_last_error_msg());
        $out = [];
        foreach ($rows as [$k, $v]) {
            $out[$k] = $v;
        }

        return $out;
    }

    /** @return list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: int}> */
    private function buttons(): array
    {
        preg_match('/private _btnDefs = (\[\n.*?\n        \]);/s', $this->src('fn_tocLaptop.sqf'), $m);
        self::assertNotEmpty($m, 'table _btnDefs introuvable');
        $rows = json_decode($m[1], true);
        self::assertIsArray($rows, 'table _btnDefs illisible : ' . json_last_error_msg());

        return $rows;
    }

    private static function cellKey(mixed $cell): string
    {
        return is_array($cell) ? (string) $cell[0] : (string) $cell;
    }

    /** @return array<string, true> */
    private function english(): array
    {
        $en = str_replace("\r\n", "\n", (string) file_get_contents(self::EN));
        preg_match_all('/^\["((?:[^"]|"")*)",/m', $en, $k);

        return array_fill_keys(array_map(static fn (string $v): string => str_replace('""', '"', $v), $k[1]), true);
    }

    public function testNoNameDiffersOnlyByCase(): void
    {
        foreach (self::FILES as $file) {
            $src = $this->src($file);
            // Aide du fichier (colonne 0) : déclarée une fois, casse comprise.
            preg_match_all('/^private (_\w+) = \{/m', $src, $helpers);
            preg_match_all('/private (_\w+)\b|"(_[A-Za-z]\w*)"/', $src, $decl, PREG_SET_ORDER);
            $count = [];
            $spell = [];
            foreach ($decl as $d) {
                $n = $d[1] !== '' ? $d[1] : $d[2];
                $count[strtolower($n)] = ($count[strtolower($n)] ?? 0) + 1;
                $spell[strtolower($n)][$n] = true;
            }
            foreach ($helpers[1] as $h) {
                self::assertSame(1, $count[strtolower($h)] ?? 0, "{$file} : l'aide {$h} est redéclarée (casse ignorée par le SQF)");
            }
            // Deux noms qui ne diffèrent que par la casse sont une seule variable (cause du 4.4.0 : _h / _H, _w / _W).
            foreach ($spell as $low => $forms) {
                self::assertCount(1, $forms, "{$file} : " . implode(' / ', array_keys($forms)) . ' sont une seule variable en SQF');
            }
            self::assertDoesNotMatchRegularExpression('/\b_[HW]\b/', $src, "{$file} : _H / _W interdits (même variable que _h / _w)");
        }
        self::assertStringContainsString('private _tr = { _this call comspec_atak_native_fnc_tr };', $this->src('fn_tocLaptop.sqf'));
    }

    public function testEveryControlIsCreatedInTheRegistryAndLaidOut(): void
    {
        $lap = $this->src('fn_tocLaptop.sqf');
        // Créations : _mk du chargement, « mk » pour fn_tocDraw / fn_tocHallUi, et l'image satellite dans son groupe de
        // découpe (le HUD du capteur est créé par fn_tocSat « hudMake »).
        self::assertSame(3, substr_count($lap, 'ctrlCreate ['));
        foreach (['fn_tocDraw.sqf', 'fn_tocHallUi.sqf'] as $f) {
            self::assertStringNotContainsString('ctrlCreate', $this->src($f), "{$f} crée ses contrôles hors du registre du PC");
            self::assertStringNotContainsString('ctrlSetPosition [_x0', $this->src($f));
        }
        $created = [];
        preg_match_all('/\["(?:COMSPEC_\w+|RscPicture)", "([^"]+)"(?:, true)?\] call _mk\b/', $lap, $m);
        $created = array_merge($created, $m[1]);
        preg_match_all('/\["([^"]+)"(?:, [^\n]*?)?\] call (?:_stx|_lblOf|_combo|_edit)\b/', $lap, $m);
        $created = array_merge($created, $m[1]);
        foreach (['fn_tocDraw.sqf', 'fn_tocHallUi.sqf'] as $f) {
            preg_match_all('/\["COMSPEC_\w+", "(\w+)"(?:, "[^"]*")?\] call _mkc\b/', $this->src($f), $m);
            $created = array_merge($created, $m[1]);
        }
        $created = array_merge($created, array_map(static fn (array $b): string => $b[0], $this->buttons()));
        self::assertSame(count($created), count(array_unique($created)), 'clé créée deux fois : ' . implode(', ', array_diff_assoc($created, array_unique($created))));
        $laid = [];
        foreach ($this->layouts() as $rows) {
            foreach ($rows as $row) {
                foreach ($row[2] as $cell) {
                    $k = self::cellKey($cell);
                    if ($k !== '') {
                        $laid[$k] = true;
                    }
                }
            }
        }
        foreach ($created as $k) {
            self::assertTrue(isset($laid[$k]) || in_array($k, self::UNLAID, true), "contrôle « {$k} » sans mise en page : il ne serait jamais montré");
        }
        foreach (array_keys($laid) as $k) {
            self::assertContains($k, $created, "mise en page de « {$k} », contrôle jamais créé");
        }
        // Montré seulement si la mise en page en cours le nomme ; seule _place pose (plus de positions à la main par onglet).
        self::assertStringContainsString('{ (_ui get _x) ctrlShow (_x in _on); } forEach (_st getOrDefault ["keys", []]);', $lap);
        preg_match('/case "load": \{(.*?)\n    case "mk": \{/s', $lap, $load);
        self::assertSame(7, substr_count($load[1], 'ctrlSetPosition'), 'au chargement, seuls les fonds sont posés à la main');
    }

    public function testEveryButtonHasACaptionAndEnglish(): void
    {
        $en = $this->english();
        foreach ($this->buttons() as [$key, $to, $long, $short, $tip, $prim]) {
            self::assertContains($to, ['L', 'T', 'D', 'H'], $key);
            self::assertNotSame('', trim($long), "{$key} : bouton sans libellé");
            self::assertNotSame('', trim($tip), "{$key} : bouton sans aide");
            foreach (array_filter([$long, $short, $tip]) as $s) {
                self::assertArrayHasKey($s, $en, "{$key} : « {$s} » sans anglais dans i18n/en.txt");
            }
            if ($to === 'T') {
                self::assertSame('tab' . $long, $key);
            }
        }
        // Libellés, aides des champs et aides des onglets : FR + EN.
        $lap = $this->src('fn_tocLaptop.sqf');
        preg_match_all('/\["\w+", "([^"]+)"\] call _lblOf/', $lap, $l);
        preg_match_all('/\["\w+", "([^"]+)"(?:, "COMSPEC_\w+")?\] call _edit/', $lap, $e);
        preg_match_all('/\["\w+", \[[^\n]*\], "([^"]+)"\] call _combo/', $lap, $c);
        preg_match('/private _tabHelp = createHashMapFromArray \[(.*?)\n            \];/s', $lap, $th);
        preg_match_all('/\["[^"]+", "([^"]+)"\]/', $th[1] ?? '', $h);
        $texts = array_merge($l[1], $e[1], $c[1], $h[1]);
        foreach (['fn_tocDraw.sqf', 'fn_tocHallUi.sqf'] as $f) {
            preg_match_all('/\["COMSPEC_\w+", "\w+", "([^"]+)"\] call _mkc/', $this->src($f), $t);
            $texts = array_merge($texts, $t[1]);
        }
        self::assertGreaterThan(40, count($texts));
        self::assertCount(14, $h[1], 'aide de chaque onglet');
        foreach ($texts as $s) {
            self::assertArrayHasKey($s, $en, "« {$s} » sans anglais dans i18n/en.txt");
        }
    }

    public function testHelpReplacesTooltipsAndNoAlertOutsideTheWindow(): void
    {
        foreach (array_merge(self::FILES, ['fn_tocSat.sqf']) as $f) {
            $src = $this->src($f);
            self::assertDoesNotMatchRegularExpression('/\b(hint|hintSilent|systemChat|titleText|cutText|BIS_fnc_showNotification)\b/', $src, $f);
            if ($f !== 'fn_tocSat.sqf') {
                self::assertStringNotContainsString('ctrlSetTooltip', $src, "{$f} : infobulle (elle couvre le contrôle voisin) ; aide sur la ligne d'aide");
            }
        }
        $lap = $this->src('fn_tocLaptop.sqf');
        self::assertStringContainsString('_c ctrlAddEventHandler ["MouseEnter", { ["hover", [_this select 0, true]] call comspec_atak_native_fnc_tocLaptop; }];', $lap);
        self::assertStringContainsString('case "help": {', $lap);
    }

    /**
     * Tailles d'interface et formats d'écran. Coordonnées d'Arma : safeZoneH = 1 / échelle, safeZoneW = safeZoneH × format × 3/4,
     * pixelW / pixelH = 3/4. Le PC est en fractions de safeZoneH (largeurs × pixelW / pixelH) : en hauteurs d'écran (« h »), il ne
     * dépend que du format ; l'échelle d'interface ne change rien (preuve : mêmes rectangles pour les trois tailles).
     *
     * @return iterable<string, array{float, float}>
     */
    public static function screens(): iterable
    {
        yield 'très petite 4:3' => [0.47, 4 / 3];
        yield 'normale 16:9' => [0.7, 16 / 9];
        yield 'très grande 21:9' => [1.0, 21 / 9];
        yield 'normale 16:10' => [0.7, 16 / 10];
    }

    /**
     * Zones du PC en hauteurs d'écran (même calcul que « load »).
     *
     * @return array<string, array{float, float, float, float}>
     */
    private function regions(float $aspect): array
    {
        $g = $this->grid();
        $gap = $g['gap'];
        $x0 = 0.025 * $aspect;
        $y0 = 0.022;
        $w0 = 0.95 * $aspect;
        $h0 = 0.956;
        $xi = $x0 + $g['pad'];
        $yi = $y0 + $g['pad'];
        $wi = $w0 - 2 * $g['pad'];
        $hi = $h0 - 2 * $g['pad'] - $g['brand'];
        $by = $yi + $g['title'] + $gap;
        $bh = $hi - $g['title'] - $g['bar'] - 2 * $gap;
        $pw = min(max($wi * $g['panel'], $g['panelMin']), $g['panelMax']);
        $px = $xi + $wi - $pw;
        $cx = $px + $gap;
        $cw = $pw - 2 * $gap;
        $tabsH = 3 * $g['tab'] + 2 * $gap;
        $cy = $by + $gap + $tabsH + 2 * $gap;

        return [
            'inner' => [$xi, $yi, $wi, $hi],
            'rTitle' => [$xi + 2 * $gap, $yi + 0.001, $wi - 4 * $gap, $g['title'] - 0.005],
            'rBar' => [$xi, $yi + $hi - $g['bar'], $wi, $g['bar']],
            'rLeft' => [$xi + $gap, $by, ($px - $gap) - ($xi + $gap), $bh],
            'rTabs' => [$cx, $by + $gap, $cw, $tabsH],
            'rHelp' => [$cx, $by + $bh - $gap - $g['help'], $cw, $g['help']],
            'rRight' => [$cx, $cy, $cw, ($by + $bh - $gap - $g['help'] - 2 * $gap) - $cy],
        ];
    }

    /**
     * Même calcul que _place (fn_tocLaptop) : [clé => [x, y, l, h, hauteur de ligne]].
     *
     * @param list<array<int, mixed>> $rows
     * @param array{float, float, float, float} $r
     * @return array<string, array{float, float, float, float, float}>
     */
    private function place(array $rows, array $r, array $g): array
    {
        [$rx, $ry, $rw, $rh] = $r;
        $gap = $g['gap'];
        $fixed = 0.0;
        $flex = 0.0;
        foreach ($rows as $row) {
            is_string($row[0]) ? $fixed += $g[$row[0]] : $flex += $row[0];
        }
        $fu = max(0, $rh - $fixed - max(0, count($rows) - 1) * $gap) / max($flex, 0.0001);
        $out = [];
        $y = $ry;
        foreach ($rows as [$hk, $wts, $cells]) {
            $hRow = is_string($hk) ? $g[$hk] : $fu * $hk;
            if (!is_string($hk)) {
                self::assertGreaterThanOrEqual($g['flexMin'], $hRow, 'ligne extensible trop basse : ' . json_encode($cells, JSON_UNESCAPED_UNICODE));
            }
            $fw = 0.0;
            $share = 0.0;
            foreach ($wts as $w) {
                $w < 0 ? $fw -= $w : $share += $w;
            }
            $free = max(0, $rw - $fw - (count($wts) - 1) * $gap);
            $xs = [];
            $ws = [];
            $x = $rx;
            foreach ($wts as $w) {
                $cw = $w < 0 ? -$w : $free * $w / max($share, 0.0001);
                $xs[] = $x;
                $ws[] = $cw;
                $x += $cw + $gap;
            }
            $col = 0;
            foreach ($cells as $cell) {
                $key = self::cellKey($cell);
                $span = is_array($cell) ? (int) ($cell[1] ?? 1) : 1;
                $hc = is_array($cell) ? (string) ($cell[2] ?? '') : '';
                $last = min($col + $span - 1, count($wts) - 1);
                $ch = $hc !== '' ? $g[$hc] : $hRow;
                if ($key !== '') {
                    $out[$key] = [$xs[$col], $y + ($hRow - $ch) / 2, $xs[$last] + $ws[$last] - $xs[$col], $ch, $hRow];
                }
                $col = $last + 1;
            }
            $y += $hRow + $gap;
        }

        return $out;
    }

    /**
     * @dataProvider screens
     */
    public function testEveryTabFitsWithoutOverlap(float $scale, float $aspect): void
    {
        $g = $this->grid();
        $reg = $this->regions($aspect);
        self::assertGreaterThan(0, $scale);
        // Zones : titre au-dessus du corps, gauche et panneau côte à côte, aide et ligne d'état en bas, tout dans l'écran du PC.
        foreach (['rTitle', 'rBar', 'rLeft', 'rTabs', 'rHelp', 'rRight'] as $k) {
            self::assertTrue(self::inside($reg[$k], $reg['inner']), "{$k} hors de l'écran du PC");
            self::assertGreaterThan(0.05, $reg[$k][2], "{$k} trop étroit");
        }
        self::assertFalse(self::overlap($reg['rLeft'], $reg['rTabs']), 'carte sous les onglets');
        self::assertFalse(self::overlap($reg['rLeft'], $reg['rRight']));
        self::assertFalse(self::overlap($reg['rTabs'], $reg['rRight']));
        self::assertFalse(self::overlap($reg['rRight'], $reg['rHelp']));
        self::assertFalse(self::overlap($reg['rTitle'], $reg['rLeft']));
        self::assertFalse(self::overlap($reg['rBar'], $reg['rLeft']));
        $zone = static fn (string $k): string => match (true) {
            $k === 'F:TITLE' => 'rTitle', $k === 'F:TABS' => 'rTabs', $k === 'F:HELP' => 'rHelp', $k === 'F:BAR' => 'rBar',
            str_starts_with($k, 'L:') => 'rLeft', default => 'rRight',
        };
        $buttons = [];
        foreach ($this->buttons() as $b) {
            $buttons[$b[0]] = $b;
        }
        $labels = [];
        preg_match_all('/\["(\w+)", "([^"]+)"\] call _lblOf/', $this->src('fn_tocLaptop.sqf'), $lm, PREG_SET_ORDER);
        foreach ($lm as $m) {
            $labels[$m[1]] = $m[2];
        }
        $layouts = $this->layouts();
        foreach (['GROUPES', 'ORBAT', 'FLUX', 'ORDRES', 'CAMP', 'RÉSEAU', 'SAT', 'CHRONOS', 'RADIO', 'SALLE', 'MAIN COURANTE', 'EXCHECK', 'PERTES', 'DEMANDES'] as $tab) {
            self::assertArrayHasKey('R:' . $tab, $layouts, "onglet {$tab} sans mise en page");
        }
        foreach ($layouts as $name => $rows) {
            $rects = $this->place($rows, $reg[$zone($name)], $g);
            $keys = array_keys($rects);
            foreach ($rects as $k => $r) {
                self::assertTrue(self::inside($r, $reg[$zone($name)]), "{$name} : « {$k} » sort de sa zone");
                self::assertGreaterThan(0.01, $r[2], "{$name} : « {$k} » sans largeur");
                if (isset($buttons[$k])) {
                    [, $to, $long, $short] = $buttons[$k];
                    // Compteur des onglets (jusqu'à 99) compris ; libellé long ou court, à la police plancher, dans 92 % de la case.
                    $sfx = $to === 'T' && in_array($long, ['GROUPES', 'ORBAT', 'FLUX', 'ORDRES', 'CHRONOS', 'DEMANDES'], true) ? ' (99)' : '';
                    $need = min(self::textWidth($long . $sfx), self::textWidth(($short !== '' ? $short : $long) . $sfx)) * $g['fontMin'];
                    self::assertLessThanOrEqual(0.92 * $r[2], $need, "{$name} : « {$long} » ne tient pas dans sa case ({$r[2]} h)");
                }
                if (isset($labels[$k])) {
                    // COMSPEC_RscLabel : 0.017 × safeZoneH, sur une ligne.
                    self::assertLessThanOrEqual($r[2], self::textWidth($labels[$k]) * 0.017 * 1.05, "{$name} : libellé « {$labels[$k]} » coupé");
                    self::assertGreaterThanOrEqual(0.02, $r[3]);
                }
            }
            for ($i = 0; $i < count($keys); $i++) {
                for ($j = $i + 1; $j < count($keys); $j++) {
                    self::assertFalse(self::overlap($rects[$keys[$i]], $rects[$keys[$j]]), "{$name} : « {$keys[$i]} » chevauche « {$keys[$j]} »");
                }
            }
        }
        // Barre de dessin : libellés lisibles (police de la grille, plancher 0.0145 × safeZoneH, au moins 15 px en 1080p).
        self::assertGreaterThanOrEqual(15, $g['fontMin'] * 1080);
        self::assertGreaterThanOrEqual($g['fontMin'] * 1.6, $g['row'], 'rangée trop basse pour son texte');
    }

    /**
     * Console SAT (console ISR, retour de TTRD « faut vraiment ce rendu TOC SAT ») : toute la console tient dans le panneau
     * de droite à chaque format (4:3, 16:10, 16:9, 21:9) ; l'image 16:9 couvre toute sa zone (ni bande noire ni
     * déformation, rognée au bord le plus long) ; le HUD se pose dans la zone visible.
     *
     * @dataProvider screens
     */
    public function testSatConsoleFitsAndImageCoversItsArea(float $scale, float $aspect): void
    {
        self::assertGreaterThan(0, $scale);
        $g = $this->grid();
        $reg = $this->regions($aspect);
        $layouts = $this->layouts();
        $right = $this->place($layouts['R:SAT'], $reg['rRight'], $g);
        $want = ['sSlot0', 'sSlot1', 'sInfo', 'sTake', 'sRelease', 'sL1', 'sGrid', 'sTaskGrid', 'sMap', 'sFollow', 'sFollowGo', 'sFollowOff', 'sNote', 'sLog', 'sTag',
            'sOut', 'sZoom', 'sIn', 'sL2', 'sAUTO', 'sDAY', 'sNV', 'sWHOT', 'sBHOT', 'sFUSW', 'sFUSB', 'sL3', 'sW', 'sN', 'sHome', 'sS', 'sE',
            'sL4', 'sOvMRKR', 'sOvBFT', 'sOvGRID', 'sOvDATA', 'sOvBOX', 'sJit', 'sTerr', 'sRefresh', 'sL5', 'sScr', 'sSend', 'sClear'];
        foreach ($want as $k) {
            self::assertArrayHasKey($k, $right, "console SAT : « {$k} » absent");
            self::assertGreaterThanOrEqual($g['fontMin'] * 1.4, $right[$k][3], "console SAT : « {$k} » trop bas pour son texte");
        }
        $keys = array_keys($right);
        for ($i = 0; $i < count($keys); $i++) {
            for ($j = $i + 1; $j < count($keys); $j++) {
                self::assertFalse(self::overlap($right[$keys[$i]], $right[$keys[$j]]), "console SAT : « {$keys[$i]} » chevauche « {$keys[$j]} »");
            }
        }
        $bottom = max(array_map(static fn (array $r): float => $r[1] + $r[3], $right));
        self::assertLessThanOrEqual($reg['rRight'][1] + $reg['rRight'][3] + 0.0005, $bottom, 'console SAT plus haute que le panneau');
        // Image : zone sBack de L:SAT ; même calcul que « layout » (largeurs physiques : x en hauteurs d'écran).
        foreach (['L:SAT' => 'sBack', 'L:SATMAP' => 'map'] as $lk => $ck) {
            self::assertArrayHasKey($ck, $this->place($layouts[$lk], $reg['rLeft'], $g), "{$lk} sans « {$ck} »");
        }
        [$bx, $by, $bw, $bh] = $this->place($layouts['L:SAT'], $reg['rLeft'], $g)['sBack'];
        $ar = $bw / $bh;
        $sw = $bw;
        $sh = $bh;
        if ($ar > 16 / 9) {
            $sh = $bh * $ar / (16 / 9);
        } else {
            $sw = $bw * (16 / 9) / $ar;
        }
        self::assertEqualsWithDelta(16 / 9, $sw / $sh, 1e-9, 'image déformée');
        self::assertGreaterThanOrEqual($bw - 1e-9, $sw, 'bande noire à gauche ou à droite');
        self::assertGreaterThanOrEqual($bh - 1e-9, $sh, 'bande noire en haut ou en bas');
        self::assertTrue(abs($sw - $bw) < 1e-9 || abs($sh - $bh) < 1e-9, 'image plus grande que nécessaire');
        self::assertGreaterThan(0.3, $bh, 'image trop basse');
        $lap = $this->src('fn_tocLaptop.sqf');
        self::assertStringContainsString('if (_ar > 16 / 9) then { _sh = _bh * _ar / (16 / 9); } else { _sw = _bw * (16 / 9) / _ar; };', $lap);
        self::assertStringContainsString('_ui set ["sPic", _d ctrlCreate ["RscPicture", -1, _clip]];', $lap);
        self::assertStringContainsString('_st set ["satHud", ["hudMake", [_d, controlNull, "PC"]] call comspec_atak_native_fnc_tocSat];', $lap);
    }

    /** @param array<int, float> $a @param array<int, float> $b */
    private static function overlap(array $a, array $b): bool
    {
        return $a[0] < $b[0] + $b[2] - 0.0005 && $b[0] < $a[0] + $a[2] - 0.0005 && $a[1] < $b[1] + $b[3] - 0.0005 && $b[1] < $a[1] + $a[3] - 0.0005;
    }

    /** @param array<int, float> $r @param array<int, float> $z */
    private static function inside(array $r, array $z): bool
    {
        return $r[0] >= $z[0] - 0.0005 && $r[1] >= $z[1] - 0.0005 && $r[0] + $r[2] <= $z[0] + $z[2] + 0.0005 && $r[1] + $r[3] <= $z[1] + $z[3] + 0.0005;
    }

    /** Largeur estimée en hauteurs de police (Barlow Condensed gras, majuscules larges), comme AtakZeusCentreLayoutTest. */
    private static function textWidth(string $s): float
    {
        $w = 0.0;
        foreach (mb_str_split($s) as $ch) {
            $w += match (true) {
                $ch === ' ' => 0.22,
                (bool) preg_match('/\p{Lu}/u', $ch) => 0.52,
                (bool) preg_match('/\p{Ll}/u', $ch) => 0.44,
                (bool) preg_match('/\d/', $ch) => 0.5,
                default => 0.3,
            };
        }

        return $w;
    }

    public function testSatelliteSeesAtNightKeepsRenderingAndZoomsWithTheWheel(): void
    {
        $sat = $this->src('fn_tocSat.sqf');
        // Nuit : AUTO en vision nocturne, JOUR éclairci (correction de couleur) ; bail jamais raccourci.
        self::assertStringContainsString('case "effect": {', $sat);
        self::assertStringContainsString('private _night = sunOrMoon < 0.5;', $sat);
        self::assertStringContainsString('["DAYN", "jour éclairci (nuit)", [3, 1, 3.5, 1, 0.04, [0, 0, 0, 0], [1, 1, 1, 1], [0.299, 0.587, 0.114, 0]]],', $sat);
        self::assertStringContainsString('_a params ["_side", "_n", ["_secs", 3], ["_pc", false]];', $sat);
        self::assertStringContainsString('_e set [2, (_e select 2) max (diag_tickTime + _secs)];', $sat);
        $room = $this->src('fn_tocRoom.sqf');
        self::assertStringContainsString('if (_v in ["AUTO", "DAY", "NV", "WHOT", "BHOT", "FUSW", "FUSB"]) then { _s set [3, _v]; };', $room);
        self::assertStringContainsString('[-1, -1, 500, "AUTO", "", "", "", "", [false, true]]', $room);
        $lap = $this->src('fn_tocLaptop.sqf');
        // Onglet SAT quitté : 30 s de rendu gardées ; molette et glisser sur l'image ; commandes grisées avec leur raison.
        self::assertStringContainsString('["need", [_side, _st getOrDefault ["satN", 0], 30]] call comspec_atak_native_fnc_tocSat;', $lap);
        self::assertStringContainsString('_d displayAddEventHandler ["MouseZChanged"', $lap);
        self::assertStringContainsString('["send", ["sat", [_n, "pan", [-_du, _dv]]]] call comspec_atak_native_fnc_tocRoom;', $lap);
        self::assertStringContainsString('(_ui get _x) ctrlEnable _can; (_ui get _x) setVariable ["why", _why];', $lap);
        self::assertStringContainsString('case "audit": {', $lap);
        $auto = (string) file_get_contents(__DIR__ . '/../../mod/COMSPEC_ATAK_Native/missions/COMSPEC_Autotest.Stratis/scripts/toc.sqf');
        self::assertStringContainsString('["audit"] call comspec_atak_native_fnc_tocLaptop', $auto);
    }
}
