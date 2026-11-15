<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Carte du téléphone : bandeaux des apps épinglées sur la grille du bandeau des coordonnées (fn_mapBandGrid), lisibles,
 * ordres validés depuis le bandeau, même information en petit format ; volet SITUATION en lignes compactes à aide dépliable.
 */
final class AtakMapPinsPanelAssetTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../';
    private const MAIN = self::ROOT . 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';
    private const GRID = 'functions/map/fn_mapBandGrid.sqf';
    private const PIN = 'functions/map/fn_mapPinnedApp.sqf';
    private const PAGE = 'functions/pages/fn_pageMap.sqf';
    private const UPDATE = 'functions/map/fn_mapOverlayUpdate.sqf';
    private const FORM = 'functions/ui/fn_formRender.sqf';

    private function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(self::MAIN . $path));
    }

    /** Code sans commentaires ni contenu de chaînes. */
    private function code(string $path): string
    {
        $code = preg_replace('~/\*.*?\*/~s', '', $this->read($path)) ?? '';
        $code = preg_replace('~"(?:[^"]|"")*"~s', '""', $code) ?? '';
        $code = preg_replace("~'(?:[^'\n])*'~", "''", $code) ?? '';

        return preg_replace('~//[^\n]*~', '', $code) ?? '';
    }

    /** Constantes nommées de fn_mapBandGrid : ["nom", nombre]. */
    private function gridConst(): array
    {
        self::assertSame(1, preg_match('~private _K = createHashMapFromArray \[(.*?)\n\];~s', $this->read(self::GRID), $m));
        preg_match_all('~\["(\w+)", ([0-9.]+)\]~', $m[1], $pairs, PREG_SET_ORDER);
        $k = [];
        foreach ($pairs as $p) {
            $k[$p[1]] = (float) $p[2];
        }
        foreach (['minChars', 'minCharsCompact', 'maxChars', 'gap', 'maxFrac', 'line1', 'line2', 'padV', 'lineCompact', 'padVCompact'] as $name) {
            self::assertArrayHasKey($name, $k);
        }

        return $k;
    }

    private function cellH(array $k, float $fs, float $in, bool $compact): float
    {
        return $compact ? $fs * $k['lineCompact'] + $in * $k['padVCompact'] : $fs * ($k['line1'] + $k['line2']) + $in * $k['padV'];
    }

    /** Même calcul que fn_mapBandGrid « layout », refait à partir de ses constantes. */
    private function layout(array $k, int $n, bool $mini, float $x0, float $xLim, float $edgeY, bool $top, float $bandW, float $bandH, float $mapH, float $chW, float $cellIn, float $pad, float $fs, float $in): array
    {
        $gap = $pad * $k['gap'];
        $availW = $xLim - $x0;
        $base = $bandH > 0 ? $bandH + $gap : 0.0;
        $plan = function (bool $compact) use ($k, $n, $availW, $gap, $bandW, $chW, $cellIn, $fs, $in): array {
            $h = $this->cellH($k, $fs, $in, $compact);
            $minW = $k[$compact ? 'minCharsCompact' : 'minChars'] * $chW + $cellIn * 2;
            $colsMax = min((int) floor(($availW + $gap) / ($minW + $gap)), $n);
            if ($colsMax < 1) {
                return [0, 0.0, 0, $h];
            }
            $bw = min($bandW, $availW);
            $colsBand = $bandW > 0 ? min((int) floor(($bw + $gap) / ($minW + $gap)), $n) : 0;
            $useBand = $colsBand >= 1 && (int) ceil($n / $colsBand) === (int) ceil($n / $colsMax);
            $cols = $useBand ? $colsBand : $colsMax;
            $colW = (($useBand ? $bw : $availW) - ($cols - 1) * $gap) / $cols;
            if (!$useBand) {
                $colW = min($colW, $k['maxChars'] * $chW + $cellIn * 2);
            }

            return [$cols, $colW, (int) ceil($n / $cols), $h];
        };
        $fits = static fn (int $rows, float $h): bool => ($base + $rows * $h + max($rows - 1, 0) * $gap) <= $mapH * $k['maxFrac'];
        $compact = $mini;
        [$cols, $colW, $rows, $h] = $plan($compact);
        if (!$compact && ($cols < 1 || !$fits($rows, $h))) {
            $compact = true;
            [$cols, $colW, $rows, $h] = $plan(true);
        }
        if ($cols < 1 || $n < 1) {
            return [[], $compact, $bandH, 0, 0.0, $h];
        }
        while ($rows > 0 && !$fits($rows, $h)) {
            $rows--;
        }
        $shown = min($n, $rows * $cols);
        $cells = [];
        for ($i = 0; $i < $shown; $i++) {
            $off = $base + intdiv($i, $cols) * ($h + $gap);
            $cells[] = [$x0 + ($i % $cols) * ($colW + $gap), $top ? $edgeY + $off : $edgeY - $off - $h, $colW, $h];
        }

        return [$cells, $compact, $rows > 0 ? $base + $rows * $h + max($rows - 1, 0) * $gap : $bandH, $cols, $colW, $h];
    }

    /**
     * Géométrie de la carte du téléphone S7 (fn_layoutGet puis fn_pageMap), en unités d'écran d'Arma, écran 16:9.
     * $h : safeZoneH (taille d'interface) ; $ts : Réglages > Taille du texte ; $mts : taille des textes de carte.
     */
    private function mapMetrics(bool $mini, bool $land, float $ts, bool $inter, bool $top, float $h = 1.0, float $mts = 1.25, bool $panel = false): array
    {
        $ratio = 4 / 3;
        $w = $h * 4 / 3;
        $px = $h / 1080;
        $visL = [0.1025, 0.2798, 0.9263, 0.8535];
        $vis = $land ? $visL : [1 - $visL[3], $visL[0], 1 - $visL[1], $visL[2]];
        $scrL = [0.2222, 0.3496, 0.7549, 0.6509];
        $scr = $land ? $scrL : [1 - $scrL[3], $scrL[0], 1 - $scrL[1], $scrL[2]];
        $scr = [$scr[0] - 0.003, $scr[1] - 0.003, $scr[2] + 0.003, $scr[3] + 0.003];
        if ($land) {
            $wantW = $mini ? $w * 0.40 : $w * 0.94;
            $dh = min($wantW / ($vis[2] - $vis[0]) * $ratio, ($mini ? min($h * 0.50, $h * 0.9) : $h * 0.96) / ($vis[3] - $vis[1]));
        } else {
            $dh = ($mini ? min($h * 0.62, $h * 0.94) : $h * 0.94) / ($vis[3] - $vis[1]);
        }
        $dw = $dh / $ratio;
        $sw = $dw * ($scr[2] - $scr[0]);
        $sh = $dh * ($scr[3] - $scr[1]);
        $font = max(max($sh * ($mini ? ($land ? 0.060 : 0.040) : 0.036), $h * 0.015) * $ts, $px * 16);
        $fs = max($font * 0.86, $px * 15);
        $statusH = $font * 1.45;
        $slim = $mini;
        $appH = ($slim && !$inter) ? 0.0 : $font * (($mini && !$inter) ? 1.5 : 1.9);
        $dock = !$land;
        $dockH = ($dock && !$slim) ? $font * 2.9 : 0.0;
        $railW = ($dock || $slim) ? 0.0 : $font * 2.9 / $ratio;
        $inspW = (!$mini && $panel) ? $sw * 0.34 : 0.0;
        $bx = $railW;
        $by = 0.0;
        $mw = $sw - $railW - $inspW;
        $mh = $sh - $statusH - $appH - $dockH;
        $pad = $sw * 0.012 * 2;
        // fn_pageMap : bandeau des coordonnées.
        $panFs = $fs * ($mini ? 0.7 : 0.78) * $mts;
        $panW = min(min($mw * 0.34, $font * 9 / $ratio) * ($mini ? 0.75 : 1) * max($mts, 1), $mw * ($mini ? 0.62 : 0.44));
        $panIn = $pad * 0.6;
        $toolCol = $inter ? min(($mh - 2 * $pad) / 7, $font * 2.1) / $ratio + $pad : 0.0;
        $chW = $panFs * 0.6 / $ratio;
        $cellIn = $panIn / $ratio * 1.2;
        $avail = max($mw - 2 * $pad - $toolCol, $panW * 1.2);
        $bandH = 0.0;
        $stripW = 0.0;
        if ($mini) {
            if ($avail >= 16 * $chW) {
                $stripW = min($avail, 26 * $chW + $cellIn * 2);
                $bandH = $panFs * 1.3 + $panIn;
            }
        } else {
            $meW = min(19 * $chW + $cellIn * 2, $avail * 0.45);
            $curNeed = 31 * $chW + $cellIn * 2;
            $curW = min($avail - $meW, max($curNeed, $panW * 0.9));
            $wrap = $inter && ($curW < 24 * $chW + $cellIn * 2 || $curW < $curNeed * 0.94);
            $bandH = $panFs * 1.45 + $panFs * 1.12 * ($wrap ? 2 : 1) + $panIn * 1.5;
            $stripW = $curW + $meW;
        }
        $top = $top && $bandH > 0;
        $px0 = $top ? max($bx + ($mw - $toolCol - $stripW) / 2, $bx + $pad) : $bx + $pad;
        $py0 = $top ? $by + $pad : $by + $mh - $bandH - $pad;
        // Rose et cap, en haut à gauche.
        $ch = min($mh * 0.24, $font * 3.6);
        $compass = [$bx + $pad, $by + $pad, $ch / $ratio, $ch];
        $heading = [$bx + $pad, $by + $pad + $ch + $px * 2, max($ch / $ratio, $font * 3.6 * 0.7 / $ratio), $fs * 1.15];
        $sth = $fs * 1.9;
        $xLim = $bx + $mw - $toolCol - $pad - (($top && $inter) ? $pad * 2 + $sth / $ratio : 0.0);
        $x0 = $px0;
        if ($top) {
            $x0 = max($x0, $compass[0] + $compass[2] + $pad, $heading[0] + $heading[2] + $pad);
        }

        return compact('ratio', 'bx', 'by', 'mw', 'mh', 'pad', 'font', 'fs', 'panFs', 'panW', 'panIn', 'toolCol', 'chW', 'cellIn', 'bandH', 'stripW', 'top', 'px0', 'py0', 'compass', 'heading', 'sth', 'xLim', 'x0', 'px');
    }

    private function overlap(array $a, array $b): bool
    {
        $e = 1e-9;

        return $a[0] < $b[0] + $b[2] - $e && $b[0] < $a[0] + $a[2] - $e && $a[1] < $b[1] + $b[3] - $e && $b[1] < $a[1] + $a[3] - $e;
    }

    private function near(float $a, float $b): bool
    {
        return abs($a - $b) < 1e-9;
    }

    /** Toutes les mises en page vérifiées : [nom, métriques, n, mini, interactif]. */
    private function cases(): array
    {
        $out = [];
        foreach ([1.0] as $h) {
            foreach ([0.8, 1.0, 1.35] as $ts) {
                foreach ([1.0, 1.25, 1.55] as $mts) {
                    foreach ([false, true] as $top) {
                        foreach ([1, 2, 3] as $n) {
                            // Grand : toujours paysage et en main ; avec ou sans le volet SITUATION.
                            foreach ([false, true] as $panel) {
                                $out[] = ["grand h=$h ts=$ts mts=$mts top=" . (int) $top . " volet=" . (int) $panel . " n=$n", $this->mapMetrics(false, true, $ts, true, $top, $h, $mts, $panel), $n, false, true, $ts, $mts, $panel];
                            }
                            // Petit : paysage ou portrait, porté (pas de clic) ou en main.
                            foreach ([true, false] as $land) {
                                foreach ([false, true] as $inter) {
                                    $out[] = ["petit " . ($land ? 'paysage' : 'portrait') . " h=$h ts=$ts mts=$mts top=" . (int) $top . " main=" . (int) $inter . " n=$n", $this->mapMetrics(true, $land, $ts, $inter, $top, $h, $mts), $n, true, $inter, $ts, $mts, false];
                                }
                            }
                        }
                    }
                }
            }
        }

        return $out;
    }

    public function testGridFunctionIsPureRegisteredAndBalanced(): void
    {
        self::assertStringContainsString('class mapBandGrid {};', $this->read('config.cpp'));
        $grid = $this->read(self::GRID);
        // Fonction pure : aucun contrôle, aucune variable lue ailleurs.
        foreach (['ctrlCreate', 'ctrlSetPosition', 'uiNamespace', 'missionNamespace', 'profileNamespace', 'COMSPEC_ATAK_', 'pixelW', 'safeZone'] as $impure) {
            self::assertStringNotContainsString($impure, $this->code(self::GRID), $impure);
        }
        // Le calcul refait ici est bien celui du fichier.
        foreach ([
            'private _gap = _pad * (_K get "gap");',
            'private _base = [0, _bandH + _gap] select (_bandH > 0);',
            'private _colsMax = (floor ((_availW + _gap) / (_minW + _gap))) min _n;',
            'private _useBand = _colsBand >= 1 && {(ceil (_n / _colsBand)) isEqualTo (ceil (_n / _colsMax))};',
            'private _colW = (([_availW, _bw] select _useBand) - (_cols - 1) * _gap) / _cols;',
            'if (!_useBand) then { _colW = _colW min ((_K get "maxChars") * _chW + _cellIn * 2); };',
            '(_base + _rows * _h + ((_rows - 1) max 0) * _gap) <= (_mapH * (_K get "maxFrac"))',
            'private _compact = _mini;',
            'if (!_compact && {_cols < 1 || {!([_rows, _h] call _fits)}}) then {',
            '_cells pushBack [_x0 + (_i mod _cols) * (_colW + _gap), [_edgeY - _off - _h, _edgeY + _off] select _top, _colW, _h];',
        ] as $line) {
            self::assertStringContainsString($line, $grid);
        }
        // La case a les lignes du bandeau des coordonnées (fn_pageMap : deux lignes NEW, une ligne COMPACT).
        $k = $this->gridConst();
        $page = $this->read(self::PAGE);
        self::assertStringContainsString('private _l1H = _panFs * ' . $k['line1'] . ';', $page);
        self::assertStringContainsString('private _l2H = _panFs * ' . $k['line2'] . ';', $page);
        self::assertStringContainsString('_bandH = _l1H + _l2H * ([1, 2] select _curWrap) + _panIn * ' . $k['padV'] . ';', $page);
        self::assertStringContainsString('_bandH = _panFs * ' . $k['lineCompact'] . ' + _panIn;', $page);
        self::assertSame(1.0, $k['padVCompact']);
        foreach ([self::GRID, self::PIN, self::PAGE, self::UPDATE, self::FORM] as $path) {
            $code = $this->code($path);
            foreach (['{' => '}', '[' => ']', '(' => ')'] as $open => $close) {
                self::assertSame(substr_count($code, $open), substr_count($code, $close), "$path : équilibre $open$close");
            }
        }
    }

    public function testBannersShareOneGridForOneTwoAndThreeAppsLargeAndSmall(): void
    {
        $k = $this->gridConst();
        $seenFull = 0;
        $seenCompact = 0;
        foreach ($this->cases() as [$name, $m, $n, $mini, $inter, $ts, $mts, $panel]) {
            $edgeY = $m['top'] ? $m['by'] + $m['pad'] : $m['by'] + $m['mh'] - $m['pad'];
            $x0 = $m['x0'];
            $base = $m['bandH'];
            $xLim = $m['xLim'];
            $run = function () use ($k, $n, $mini, &$x0, &$xLim, $edgeY, $m, &$base): array {
                return $this->layout($k, $n, $mini, $x0, $xLim, $edgeY, $m['top'], $m['stripW'], $base, $m['mh'], $m['chW'], $m['cellIn'], $m['pad'], $m['panFs'], $m['panIn']);
            };
            [$cells, $compact, $extent, $cols, $colW, $h] = $run();
            // fn_pageMap : en haut, sans place à droite de la rose, la pile repart du bord du bandeau, sous la rose et le cap.
            if ($m['top'] && $cells === [] && $x0 > $m['px0']) {
                $x0 = $m['px0'];
                if ($m['heading'][1] + $m['heading'][3] >= $m['by'] + $m['pad'] * 2 + $m['sth']) {
                    $xLim = $m['bx'] + $m['mw'] - $m['toolCol'] - $m['pad'];
                }
                $base = max($m['bandH'], $m['heading'][1] + $m['heading'][3] - ($m['by'] + $m['pad']));
                [$cells, $compact, $extent, $cols, $colW, $h] = $run();
            }
            $gap = $m['pad'] * $k['gap'];
            // Petit format : toujours une ligne. Bandeau des coordonnées masqué (carte trop étroite) : aucune case non plus.
            if ($mini) {
                self::assertTrue($compact, $name);
            }
            if ($m['bandH'] <= 0) {
                self::assertSame([], $cells, $name);
                continue;
            }
            // Bandeau en bas (défaut) : au moins une app, et toutes (1, 2 ou 3) aux tailles de texte de carte PETIT et NORMAL,
            // en grand sans le volet à toute taille de texte, en petit jusqu'à la taille de texte normale. Au-delà (texte
            // très grand, volet ouvert, bandeau en haut d'une carte étroite), la règle écarte les rangées qui dépassent.
            if (!$m['top']) {
                self::assertGreaterThanOrEqual(1, count($cells), $name);
                if ($mts <= 1.25 && ($mini ? $ts <= 1.0 : !$panel)) {
                    self::assertCount($n, $cells, $name);
                }
            }
            if ($cells === []) {
                continue;
            }
            $compact ? $seenCompact++ : $seenFull++;
            $band = [$m['px0'], $m['py0'], $m['stripW'], $m['bandH']];
            foreach ($cells as $i => $c) {
                // Même largeur, même hauteur, sur les colonnes et les rangées de la grille.
                self::assertTrue($this->near($colW, $c[2]), $name);
                self::assertTrue($this->near($h, $c[3]), $name);
                self::assertTrue($this->near($this->cellH($k, $m['panFs'], $m['panIn'], $compact), $c[3]), $name);
                self::assertTrue($this->near($x0 + ($i % $cols) * ($colW + $gap), $c[0]), $name);
                $row = intdiv($i, $cols);
                $off = $base + $gap + $row * ($h + $gap);
                self::assertTrue($this->near($m['top'] ? $edgeY + $off : $edgeY - $off - $h, $c[1]), $name);
                // Dans la carte, marges comprises, sans passer sous les commandes de droite.
                self::assertGreaterThanOrEqual($m['bx'] + $m['pad'] - 1e-9, $c[0], $name);
                self::assertLessThanOrEqual($xLim + 1e-9, $c[0] + $c[2], $name);
                self::assertLessThanOrEqual($m['bx'] + $m['mw'] - $m['toolCol'] - $m['pad'] + 1e-9, $c[0] + $c[2], $name);
                self::assertGreaterThanOrEqual($m['by'] + $m['pad'] - 1e-9, $c[1], $name);
                self::assertLessThanOrEqual($m['by'] + $m['mh'] - $m['pad'] + 1e-9, $c[1] + $c[3], $name);
                // Ni sur le bandeau des coordonnées, ni sur une autre case, ni sur la rose et le cap.
                self::assertFalse($this->overlap($c, $band), "$name : bandeau");
                self::assertFalse($this->overlap($c, $m['compass']), "$name : rose");
                self::assertFalse($this->overlap($c, $m['heading']), "$name : cap");
                foreach ($cells as $j => $o) {
                    if ($j > $i) {
                        self::assertFalse($this->overlap($c, $o), "$name : cases $i et $j");
                    }
                }
                // Une case montre au moins son libellé et son état.
                self::assertGreaterThanOrEqual($k[$compact ? 'minCharsCompact' : 'minChars'] * $m['chW'] + $m['cellIn'] * 2 - 1e-9, $c[2], $name);
            }
            // Première colonne calée sur le bord gauche du bandeau des coordonnées quand il est en bas.
            if (!$m['top']) {
                self::assertTrue($this->near($m['px0'], $cells[0][0]), $name);
            }
            // La pile (bandeau compris) ne prend jamais plus que la part permise de la carte.
            self::assertLessThanOrEqual($m['mh'] * $k['maxFrac'] + 1e-9, $extent, $name);
            // En main, en bas : RECHERCHER se pose au-dessus de la pile (_panH) et reste sous la rose et le cap.
            if ($inter && !$m['top']) {
                $search = [$m['bx'] + $m['pad'], $m['by'] + $m['mh'] - $extent - $m['pad'] * 2 - $m['sth'], min($m['sth'] / $m['ratio'] + $m['fs'] * 5.8 / $m['ratio'], $m['panW']), $m['sth']];
                foreach ($cells as $c) {
                    self::assertFalse($this->overlap($c, $search), "$name : RECHERCHER");
                }
                if (!$mini) {
                    self::assertGreaterThanOrEqual($m['heading'][1] + $m['heading'][3], $search[1], "$name : RECHERCHER sous le cap");
                }
            }
        }
        // Les deux formes de case ont été vérifiées.
        self::assertGreaterThan(0, $seenFull);
        self::assertGreaterThan(0, $seenCompact);
    }

    public function testMapPageUsesTheGridAndKeepsTheStackTogether(): void
    {
        $page = $this->read(self::PAGE);
        self::assertStringContainsString('private _pinArgs = [count _pinApps, _mini, _pinX0, _pinLim, [_by + _mh - _pad, _by + _pad] select _pinTop, _pinTop, _stripW, _bandH, _mh, _chW, _cellIn, _pad, _panFs, _panIn];', $page);
        self::assertStringContainsString('private _pinGrid = ["layout", _pinArgs] call comspec_atak_native_fnc_mapBandGrid;', $page);
        self::assertStringContainsString('_pinArgs set [7, _bandH max (_pinKeepY - (_by + _pad))];', $page);
        self::assertStringContainsString('if (_pinKeepY >= (_by + _pad * 2 + _fs * 1.9)) then { _pinArgs set [3, _bx + _mw - _toolCol - _pad]; };', $page);
        self::assertStringContainsString('_pinGrid params ["_pinCells", "_pinCompact", "_pinExtent"];', $page);
        self::assertStringContainsString('["build", [_ov, _mk, [_pinX, _pinY, _pinW, _pinH], [_panFs, _panIn, _cellIn, _chW, _ratio], _interactive, _pinApps select _forEachIndex, _pinAlpha, _pinCompact]] call comspec_atak_native_fnc_mapPinnedApp;', $page);
        // Plus de condition « pas en petit format », plus de case posée à droite du bandeau.
        self::assertStringNotContainsString('{!_mini}) then {', $page);
        self::assertStringNotContainsString('_px0 + _stripW + _pad', $page);
        // Place réservée : étiquettes de la carte, puis fiches, RECHERCHER et menu OUTILS (_panH), réglette d'effectif en haut.
        self::assertStringContainsString('_hud pushBack [_pinX - _bx, _pinY - _by, _pinX + _pinW - _bx, _pinY + _pinH - _by];', $page);
        self::assertStringContainsString('if (_pinTop) then { _pinTopEnd = _by + _pad + _pinExtent; } else { _panH = _panH max _pinExtent; };', $page);
        self::assertStringContainsString('if (_pinTopEnd > 0) then { _sqY = _sqY max (_pinTopEnd + pixelH * 3); };', $page);
        // Le bandeau des coordonnées change de hauteur avec son contenu : la pile suit son bord (pas d'intervalle variable).
        $pin = $this->read(self::PIN);
        self::assertStringContainsString('_ov set ["bandFollow", (_ov getOrDefault ["bandFollow", []]) + _made];', $pin);
        $upd = $this->read(self::UPDATE);
        self::assertStringContainsString('private _dyF = [_shrink, -_shrink] select _top;', $upd);
        self::assertStringContainsString('} forEach (_ov getOrDefault ["bandFollow", []]);', $upd);
        self::assertSame(1, substr_count($upd, '["tick"] call comspec_atak_native_fnc_mapPinnedApp;'));
    }

    /** Luminance relative WCAG d'une couleur sRGB (composantes 0 à 1). */
    private function luminance(array $rgb): float
    {
        $lin = array_map(static fn (float $c): float => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4, array_slice($rgb, 0, 3));

        return 0.2126 * $lin[0] + 0.7152 * $lin[1] + 0.0722 * $lin[2];
    }

    private function contrast(array $a, array $b): float
    {
        $la = $this->luminance($a);
        $lb = $this->luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /** Valeurs [thème sombre et jour, thème nuit JVN] d'un jeton de fn_theme. */
    private function themeToken(string $name): array
    {
        $theme = $this->read('functions/ui/fn_theme.sqf');
        $num = '([0-9.]+)';
        if (preg_match('~_th set \["' . $name . '", _th get "(\w+)"\];~', $theme, $alias) === 1) {
            return $this->themeToken($alias[1]);
        }
        self::assertSame(1, preg_match('~_th set \["' . $name . '", \[\[' . "$num, $num, $num, $num\], \[$num, $num, $num, $num\]\] select~", $theme, $m), $name);

        return [[(float) $m[1], (float) $m[2], (float) $m[3]], [(float) $m[5], (float) $m[6], (float) $m[7]]];
    }

    public function testBannerColoursAreNamedThemeInksWithEnoughContrast(): void
    {
        $pin = $this->read(self::PIN);
        $num = '([0-9.]+)';
        self::assertSame(1, preg_match("~private _PIN_BG = \[$num, $num, $num\];~", $pin, $bgM));
        self::assertSame(1, preg_match("~private _PIN_ALPHA_MIN = $num;~", $pin, $alphaM));
        self::assertSame(1, preg_match("~private _PIN_INK_BTN = \[$num, $num, $num, 1\];~", $pin, $btnInkM));
        self::assertSame(1, preg_match("~private _PIN_BTN_BG = \[$num, $num, $num, 1\];~", $pin, $btnBgM));
        $inks = [];
        foreach (['TEXT', 'LABEL', 'EMPTY'] as $role) {
            self::assertSame(1, preg_match('~private _PIN_INK_' . $role . ' = "(Map\w+)";~', $pin, $m), $role);
            $inks[$role] = $m[1];
        }
        $bg = [(float) $bgM[1], (float) $bgM[2], (float) $bgM[3]];
        $alpha = (float) $alphaM[1];
        self::assertGreaterThanOrEqual(0.85, $alpha);
        // Fonds les plus défavorables : le fond seul (carte noire) et le fond à son opacité plancher sur une carte blanche.
        $onWhite = array_map(static fn (float $c): float => $c * $alpha + (1 - $alpha), $bg);
        $onBlack = array_map(static fn (float $c): float => $c * $alpha, $bg);
        foreach ($inks as $role => $token) {
            foreach ($this->themeToken('map' . substr($token, 3)) as $i => $ink) {
                foreach ([$bg, $onWhite, $onBlack] as $j => $under) {
                    self::assertGreaterThanOrEqual(4.5, $this->contrast($ink, $under), "$role thème $i fond $j");
                }
            }
        }
        // Valeurs d'état en grand et en gras (orange, rouge, vert) : texte large, 3 au moins.
        foreach (['mapWarn', 'mapAlert', 'mapOk'] as $token) {
            foreach ($this->themeToken($token) as $i => $ink) {
                self::assertGreaterThanOrEqual(3.0, $this->contrast($ink, $onWhite), "$token thème $i");
            }
        }
        // Boutons : texte sombre sur l'encre d'accent, texte franc sur le fond des autres boutons.
        foreach ($this->themeToken('map' . substr($inks['LABEL'], 3)) as $i => $accent) {
            self::assertGreaterThanOrEqual(4.5, $this->contrast([(float) $btnInkM[1], (float) $btnInkM[2], (float) $btnInkM[3]], $accent), "bouton principal thème $i");
        }
        foreach ($this->themeToken('map' . substr($inks['TEXT'], 3)) as $i => $ink) {
            self::assertGreaterThanOrEqual(4.5, $this->contrast($ink, [(float) $btnBgM[1], (float) $btnBgM[2], (float) $btnBgM[3]]), "bouton secondaire thème $i");
        }
        // Les constantes servent : fond à l'opacité plancher, liseré, libellé en encre d'accent, contenu en encre franche.
        self::assertStringContainsString('_bg ctrlSetBackgroundColor (_PIN_BG + [_alpha max _PIN_ALPHA_MIN]);', $pin);
        self::assertStringContainsString('_ln ctrlSetBackgroundColor (_th get _PIN_EDGE);', $pin);
        self::assertStringContainsString('private _ink = _th get ("hex" + _PIN_INK_TEXT);', $pin);
        self::assertStringContainsString('private _ok = _th get ("hex" + _PIN_INK_LABEL);', $pin);
        self::assertStringContainsString('private _dim = _th get ("hex" + _PIN_INK_EMPTY);', $pin);
        self::assertStringContainsString('format ["<t font=\'EtelkaMonospacePro\' color=\'%1\'> · %2</t>", _ink, [_what, _maxc - _used] call _clip]', $pin);
        self::assertStringContainsString('_l2 = format ["<t font=\'EtelkaMonospacePro\' color=\'%1\'>%2</t>", _dim, _none call _tr];', $pin);
        // Aucune couleur de texte en dur dans le résumé.
        self::assertSame(0, preg_match("~color='#[0-9a-fA-F]{6}'~", $pin));
    }

    public function testOrdersAreAnsweredFromTheBannerThroughTheOrdersApp(): void
    {
        $pin = $this->read(self::PIN);
        // Même chemin que les boutons de l'app Ordres : ordre choisi puis fn_taskAction (Athena / TOC, fn_orderAck, journal).
        $tasks = $this->read('functions/pages/fn_pageTasks.sqf');
        self::assertStringContainsString("set ['selectedTask', %1]; ['%2'] call comspec_atak_native_fnc_taskAction;", $tasks);
        self::assertStringContainsString('(uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["selectedTask", _id];' . "\n" . '        [_status] call comspec_atak_native_fnc_taskAction;', $pin);
        $action = $this->read('functions/pages/fn_taskAction.sqf');
        self::assertStringContainsString('["send", [_id, _status]] call comspec_atak_native_fnc_orderAck;', $action);
        // Le bandeau n'envoie rien lui-même : ni accusé direct, ni appel à Athena, ni écriture de statut.
        self::assertStringNotContainsString('["send"', $pin);
        self::assertStringNotContainsString('updateOrderStatus', $pin);
        self::assertStringNotContainsString('UpdateOrderStatus', $pin);
        self::assertStringNotContainsString('set ["status"', $pin);
        self::assertSame(1, substr_count($pin, 'call comspec_atak_native_fnc_taskAction'));
        // Pas de doublon : statut inconnu, ordre disparu ou statut déjà donné, rien ne part.
        self::assertStringContainsString('if (!(_status in ["ACK", "EXEC", "DONE", "FAILED"]) || {(count _task) isEqualTo 0} || {(toUpper _now) isEqualTo _status}) exitWith { false };', $pin);
        // « VU » : la règle de l'app (une fois par ordre).
        self::assertStringContainsString('if (_any) then { ["seen", [_id]] call comspec_atak_native_fnc_orderAck; };', $pin);
        self::assertStringContainsString('if (_id in _sent) exitWith { false };', $this->read('functions/pages/fn_orderAck.sqf'));
        // Réponses proposées : celles de l'app, mêmes statuts et mêmes mots.
        foreach (['["ACK", "REÇU"]', '["EXEC", "EN COURS"]', '["DONE", "TERMINÉ"]', '["FAILED", "IMPOSSIBLE"]'] as $pair) {
            self::assertStringContainsString($pair, $pin);
        }
        foreach (['["ACK", "REÇU"] call _btn', '["EXEC", "EN COURS"] call _btn', '["DONE", "TERMINÉ"] call _btn', '["FAILED", "IMPOSSIBLE"] call _btn'] as $btn) {
            self::assertStringContainsString($btn, $tasks);
        }
        // Plusieurs ordres : rang « 1/3 », bouton suivant, ordre gardé par son identifiant ; même tri que l'app.
        self::assertStringContainsString('if ((count _ids) > 1) then { _pre = format ["%1/%2 ", _idx + 1, count _ids]; };', $pin);
        self::assertStringContainsString('if ((count _ids) > 1) then { [2, "›", ["", "NEXT"]] call _try; };', $pin);
        self::assertStringContainsString('uiNamespace setVariable ["COMSPEC_ATAK_PinOrderId", _ids select ((_idx + 1) mod (count _ids))];', $pin);
        self::assertStringContainsString('_sorted pushBack [parseNumber (_st in ["ACK", "EXEC"]), [_pi min 2, 3] select (_pi < 0), -_stamp, str _x, _x];', $pin);
        // Émetteur, type, texte, heure.
        self::assertStringContainsString('_when = _best getOrDefault ["time", ""];', $pin);
        self::assertStringContainsString('_who = [_best getOrDefault ["issuer", "TOC"], 12] call _clip;', $pin);
        // Boutons créés après le bouton d'ouverture de l'app (donc au-dessus) : hors des boutons, le toucher ouvre l'app.
        $open = (int) strpos($pin, '[_c getVariable ["page", "CHAT"]] call comspec_atak_native_fnc_navigate;');
        $btn = (int) strpos($pin, 'private _b = ["COMSPEC_RscButton", [_cx, _y0 + _bm, _chW * 4, _h - _bm * 2]] call _mk;');
        self::assertGreaterThan(0, $open);
        self::assertGreaterThan($open, $btn);
        // Clic différé d'une image, bouton sans action : rien.
        self::assertStringContainsString('if (_do isEqualTo []) exitWith {};', $pin);
        self::assertStringContainsString('[{ ["act", _this] call comspec_atak_native_fnc_mapPinnedApp; }, _do] call comspec_atak_native_fnc_nextFrame;', $pin);
        // Les boutons ne prennent jamais plus que leur part de la case ; le texte s'arrête avant eux.
        self::assertSame(1, preg_match('~private _PIN_ACT_FRAC = ([0-9.]+);~', $pin, $frac));
        self::assertLessThanOrEqual(0.6, (float) $frac[1]);
        self::assertStringContainsString('if (_used + _need + _gapB <= _budget) then {', $pin);
    }

    public function testSmallFormatShowsTheSameBannersAndNamesAnExistingKey(): void
    {
        $pin = $this->read(self::PIN);
        // Une ligne par bandeau : libellé, état, puis ce qui tient, coupé proprement.
        self::assertStringContainsString('if (_compact) exitWith {', $pin);
        self::assertStringContainsString('_state = [_state, _left max 3] call _clip;', $pin);
        self::assertStringContainsString('[_line, ""]', $pin);
        // Téléphone porté : la touche existante « Interagir avec la miniature », lue dans les raccourcis, écrite sur le bandeau.
        self::assertStringContainsString('private _kb = (["get", "PhoneMiniUse"] call comspec_atak_native_fnc_keybinds) param [0, []];', $pin);
        self::assertStringContainsString('["COMSPEC ATAK", "PhoneMiniUse", ["Interagir avec la miniature (sans l\'agrandir)"', $this->read('XEH_postInitClient.sqf'));
        // Aucun nouveau raccourci.
        self::assertStringNotContainsString('CBA_fnc_addKeybind', $pin);
        // Les textes ne disent plus que les bandeaux sont masqués en petit format.
        foreach ([self::PIN, self::PAGE, 'XEH_preInit.sqf', 'functions/pages/fn_pageSettings.sqf'] as $path) {
            $src = $this->read($path);
            self::assertStringNotContainsString('masquées en petit format', $src, $path);
            self::assertStringNotContainsString('Masqué en petit format', $src, $path);
            self::assertStringNotContainsString('Rien en petit format', $src, $path);
        }
        self::assertStringContainsString('En petit format : une ligne par app.', $pin);
        self::assertStringContainsString('En petit format : une ligne par app. Aussi réglable dans Réglages > Carte."', $this->read('XEH_preInit.sqf'));
    }

    public function testSituationPanelUsesCompactRowsWithOneFoldableHelp(): void
    {
        $page = $this->read(self::PAGE);
        // Une seule explication ouverte, gardée pour la session dans uiNamespace.
        self::assertStringContainsString('uiNamespace setVariable ["COMSPEC_ATAK_MapPanelHelp", ["", _k] select ((uiNamespace getVariable ["COMSPEC_ATAK_MapPanelHelp", ""]) isNotEqualTo _k)];', $page);
        self::assertStringNotContainsString('profileNamespace setVariable ["COMSPEC_ATAK_MapPanelHelp"', $page);
        // Mode Essentiel : ligne compacte (libellé + interrupteur), aide retirée de la ligne, dépliée dessous en pleine largeur.
        self::assertStringContainsString('{ [_x, "essential"] call _foldSwitch; } forEach (["rows"] call comspec_atak_native_fnc_essentialMode);', $page);
        self::assertStringContainsString('_r set [4, ""];', $page);
        self::assertStringContainsString('_r set [7, [_helpOpen isEqualTo _k, _k call _helpCode]];', $page);
        self::assertStringContainsString('if (_helpOpen isEqualTo _k) then { _rows pushBack ["text", format ["<t size=\'0.8\' color=\'%1\'>%2</t>", COMSPEC_ATAK_Th get "hexTextDim", _hp call comspec_atak_native_fnc_tr]]; };', $page);
        // Apps épinglées : « Apps épinglées · n/3 », choix et explication seulement dépliés.
        self::assertStringContainsString('_rows append (["panelRows", [_helpOpen isEqualTo "pins", "pins" call _helpCode]] call comspec_atak_native_fnc_mapPinnedApp);', $page);
        $pin = $this->read(self::PIN);
        self::assertStringContainsString('private _rows = [["fold", "Apps épinglées", format ["%1/%2", count _sel, count _vars], _open, _code]];', $pin);
        self::assertSame(1, preg_match('~case "panelRows": \{(.*?)\n    \};~s', $pin, $m));
        self::assertStringContainsString('if (_open) then {', $m[1]);
        self::assertStringContainsString('_seg set [3, ""];', $m[1]);
        // Plus aucun long texte d'aide écrit dans une ligne du volet : même traitement pour les calques (vue CONFIGURATION).
        self::assertStringNotContainsString('_rows append (["rows"] call comspec_atak_native_fnc_essentialMode);', $page);
        self::assertStringNotContainsString('_rows append (["rows"] call comspec_atak_native_fnc_mapPinnedApp);', $page);
        self::assertStringContainsString('["", _help] select _full, _locked], "layer:" + _var] call _foldSwitch;', $page);
        self::assertSame(0, preg_match('~_rows pushBack \["switch", [^\n]*_help~', $page));
        // Composant : le chevron et la zone de toucher ne couvrent que le libellé, la pastille garde son clic.
        $form = $this->read(self::FORM);
        self::assertStringContainsString('_row params ["", "_label", "_on", "_code", ["_help", ""], ["_locked", false], ["_id", ""], ["_swFold", []]];', $form);
        self::assertStringContainsString('private _swB = ["COMSPEC_RscButtonOverlay", [_pad, _y, _w - _pillW - _pad, _h]] call _mk;', $form);
        self::assertStringContainsString('["ui_chevron_down", "ui_chevron_up"] select _swOpen', $form);
        foreach (['ui_chevron_down.paa', 'ui_chevron_up.paa'] as $paa) {
            self::assertTrue(is_file(self::MAIN . 'data/' . $paa), $paa);
        }
        // Réglages > Carte garde la ligne de choix complète, avec la nouvelle explication.
        self::assertStringContainsString('(["rows"] call comspec_atak_native_fnc_mapPinnedApp) select 0,', $this->read('functions/pages/fn_pageSettings.sqf'));
        self::assertStringContainsString('}, _pinHelp]]', $pin);
    }

    public function testNothingIsShownOutsideThePhone(): void
    {
        foreach ([self::GRID, self::PIN] as $path) {
            $code = $this->code($path);
            foreach (['fnc_notify', 'fnc_toast', 'systemChat', 'hint ', 'hintSilent', 'hintC', 'titleText', 'cutText', 'cutRsc', 'BIS_fnc_showNotification'] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $code, "$path : $forbidden");
            }
        }
        // fn_pageMap, fn_mapOverlayUpdate, fn_formRender : seulement les parties ajoutées ici.
        $page = $this->read(self::PAGE);
        $from = (int) strpos($page, 'private _pinApps = ["apps"] call comspec_atak_native_fnc_mapPinnedApp;');
        $to = (int) strpos($page, '// Rectangles de l\'interface posée sur la carte');
        $help0 = (int) strpos($page, '// Explications dépliables du volet');
        $help1 = (int) strpos($page, '_rows pushBack ["title", "Calques"];');
        self::assertGreaterThan($from, $to);
        self::assertGreaterThan($help0, $help1);
        $form = $this->read(self::FORM);
        $sw0 = (int) strpos($form, 'if (_swFoldOn) then {');
        $upd = $this->read(self::UPDATE);
        $up0 = (int) strpos($upd, 'private _dyF = [_shrink, -_shrink] select _top;');
        foreach ([substr($page, $from, $to - $from), substr($page, $help0, $help1 - $help0), substr($form, $sw0, 900), substr($upd, $up0, 600)] as $i => $part) {
            foreach (['fnc_notify', 'fnc_toast', 'systemChat', 'hint ', 'hintSilent', 'titleText', 'cutText'] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $part, "partie $i : $forbidden");
            }
        }
    }
}
