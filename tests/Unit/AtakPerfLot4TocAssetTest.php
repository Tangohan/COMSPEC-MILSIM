<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Optimisation des gels, lot 4 (poste TOC, écrans, caméras) : fige les garde-fous branchés dans le SQF.
 *  - plus aucune écriture directe du profil dans functions/toc/ (écriture regroupée de fn_saveProfile) ;
 *  - premières images des écrans d'une salle servies par une file, un écran par image, et contrôles de la carte créés
 *    par tranches (avant : tous les écrans, environ 900 contrôles chacun, dans la même image) ;
 *  - mosaïque : quatre caméras rendues au plus, noms des cibles de rendu repris ;
 *  - marqueurs de la mission lus une fois par image pour tous les écrans, plaques comparées par cases ;
 *  - carte du PC du poste : liste de dessin rejouée tant que rien ne change, flux construit hors du dessin ;
 *  - flux du serveur : un seul parcours des unités par passage.
 * Lecture du SQF seulement (rien n'est mesurable sans le jeu) : les durées se lisent dans le RPT, lignes [PERF].
 */
final class AtakPerfLot4TocAssetTest extends TestCase
{
    private const TOC = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/toc/';
    private const TOUCHED = ['fn_tocScreen.sqf', 'fn_tocLaptop.sqf', 'fn_tocAppData.sqf', 'fn_tocMosaic.sqf', 'fn_tocFeed.sqf', 'fn_tocLayout.sqf', 'fn_tocManager.sqf'];

    private static function read(string $file): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/' . self::TOC . $file));
    }

    /** Code sans commentaires ni contenu des chaînes. */
    private static function code(string $src): string
    {
        $src = (string) preg_replace('~/\*.*?\*/~s', '', $src);
        $src = (string) preg_replace('~"(?:[^"\n]|"")*"~', '""', $src);
        $src = (string) preg_replace("~'(?:[^'\n]|'')*'~", "''", $src);

        return (string) preg_replace('~//[^\n]*~', '', $src);
    }

    private static function between(string $src, string $from, string $to): string
    {
        $a = strpos($src, $from);
        self::assertNotFalse($a, $from . ' absent');
        $b = strpos($src, $to, $a);
        self::assertNotFalse($b, $to . ' absent');

        return substr($src, $a, $b - $a);
    }

    public function testTouchedFilesKeepBalancedDelimiters(): void
    {
        foreach (self::TOUCHED as $file) {
            $code = self::code(self::read($file));
            foreach ([['{', '}'], ['[', ']'], ['(', ')']] as [$open, $close]) {
                self::assertSame(substr_count($code, $open), substr_count($code, $close), "{$file} : « {$open} » et « {$close} » ne s'équilibrent pas");
            }
        }
    }

    public function testNothingIsShownOnScreen(): void
    {
        foreach (self::TOUCHED as $file) {
            self::assertDoesNotMatchRegularExpression(
                '/\b(hint|hintSilent|hintC|systemChat|titleText|cutText|BIS_fnc_showNotification|comspec_atak_native_fnc_toast)\b/',
                self::code(self::read($file)),
                "{$file} : message à l'écran"
            );
        }
    }

    public function testNoDirectProfileWriteInTheTocFolder(): void
    {
        $files = glob(dirname(__DIR__, 2) . '/' . self::TOC . '*.sqf') ?: [];
        self::assertGreaterThan(40, count($files));
        foreach ($files as $path) {
            $code = self::code(str_replace("\r\n", "\n", (string) file_get_contents($path)));
            self::assertStringNotContainsString('saveProfileNamespace', $code, basename($path) . ' : écriture directe du profil (image figée)');
        }
        self::assertSame(2, substr_count(self::read('fn_tocLayout.sqf'), '[] call comspec_atak_native_fnc_saveProfile;'));
        self::assertSame(1, substr_count(self::read('fn_tocManager.sqf'), '[] call comspec_atak_native_fnc_saveProfile;'));
    }

    public function testFirstImagesGoThroughAQueueOneScreenPerFrame(): void
    {
        $scr = self::read('fn_tocScreen.sqf');
        // Texture chargée : plus de dessin direct à l'image suivante, la file.
        $load = self::between($scr, 'case "load": {', 'case "center": {');
        self::assertStringContainsString('["first", [_o, _nm, false]] call _fn;', $load);
        self::assertStringNotContainsString('["draw"', $load);
        // Tour de la seconde : la première image passe toujours, mais par la file.
        $tick = self::between($scr, 'case "tick": {', 'case "build": {');
        self::assertStringContainsString('if (_due && {_drawn < 8 || {_last < 0}}) then {', $tick);
        self::assertStringContainsString('if (_last < 0) then {', $tick);
        self::assertStringContainsString('["first", [_o, _tex, true]] call _fn;', $tick);
        self::assertStringContainsString('if (!isNil "_res" && {_res isEqualTo "BUILD"}) then { ["first", [_o, _tex, false]] call _fn; }', $tick);
        // File : un écran par image, relance à l'image suivante tant qu'il en reste, un seul vidage à la fois.
        $first = self::between($scr, 'case "first": {', 'case "firstRun": {');
        self::assertStringContainsString('private _i = _q findIf { (_x select 0) isEqualTo _o };', $first);
        self::assertStringContainsString('if (diag_frameNo - (uiNamespace getVariable ["COMSPEC_ATAK_TocFirstAt", -10]) > 2) then {', $first);
        $run = self::between($scr, 'case "firstRun": {', 'case "tick": {');
        self::assertStringContainsString('while { !_busy && {(count _q) > 0} } do {', $run);
        self::assertSame(1, substr_count($run, '_busy = true;'));
        self::assertSame(1, substr_count($run, '["draw", [_o, _d, true]] call _fn;'));
        self::assertStringContainsString('if (isNull _o || {isNull _d} || {!(_o getVariable ["COMSPEC_ATAK_TocOn", false])}) then { _q deleteAt 0; continue };', $run);
        self::assertStringContainsString('[{ ["firstRun"] call comspec_atak_native_fnc_tocScreen; }, []] call comspec_atak_native_fnc_nextFrame;', $run);
        self::assertStringContainsString('uiNamespace setVariable ["COMSPEC_ATAK_TocFirstAt", -10];', $run);
        self::assertStringContainsString('["TOC : premier dessin d\'un écran (contrôles)", _perfT] call comspec_atak_native_fnc_perfAcc;', $run);
    }

    public function testMapControlsAreCreatedInSlicesInTheSameOrder(): void
    {
        $scr = self::read('fn_tocScreen.sqf');
        $build = self::between($scr, 'case "build": {', 'case "draw": {');
        self::assertStringContainsString('if (_staged) exitWith {};', $build);
        self::assertStringContainsString('if (_stage < (count _steps)) exitWith { _d setVariable ["COMSPEC_ATAK_TocUiB", [_stage, _b]]; createHashMap };', $build);
        // Toutes les clés de la table d'origine, une fois chacune, dans l'ordre de création d'origine (ordre d'empilement).
        preg_match_all('/\["(\w+)", /', implode("\n", array_filter(explode("\n", $build), static fn (string $l): bool => str_contains($l, '{ _b set _x; } forEach ['))), $m);
        self::assertSame(
            ['grp', 'sea', 'tiles', 'dim', 'lines', 'gLbls', 'places', 'segs', 'segLbls', 'mkIcons', 'mkLbls', 'evIcons', 'leads', 'uIcons', 'uLbls', 'uBars', 'scale', 'north', 'legend', 'empty'],
            $m[1]
        );
        // Nombres de contrôles inchangés : 36 tuiles, 28 lignes, 260 segments, 40 marqueurs, 100 nœuds (2 tranches de 50).
        foreach (['from 1 to 36 do', 'from 1 to 28 do', 'from 1 to 16 do', 'from 1 to 260 do', 'from 1 to 24 do', 'from 1 to 40 do', 'from 1 to 12 do', 'from 1 to 100 do', 'from 1 to 50 do'] as $loop) {
            self::assertStringContainsString($loop, $build);
        }
        self::assertSame(2, substr_count($build, "            _units,\n"));
        // Aucune tranche au-delà de 300 contrôles : la plus grosse est celle des segments (260 + 24).
        // Dessin : rien tant que la table n'est pas complète.
        $draw = self::between($scr, 'case "draw": {', 'case "squadText": {');
        self::assertStringContainsString('_a params ["_o", "_d", ["_staged", false]];', $draw);
        self::assertStringContainsString('_ui = ["build", [_d, _staged]] call _fn;', $draw);
        self::assertStringContainsString('if ((count _ui) isEqualTo 0) exitWith { "BUILD" };', $draw);
        self::assertStringContainsString('["TOC carte d\'écran (dessin)", _perfMap] call comspec_atak_native_fnc_perfAcc;', $draw);
    }

    public function testMissionMarkersAreReadOncePerFrameForAllScreens(): void
    {
        $draw = self::between(self::read('fn_tocScreen.sqf'), 'case "draw": {', 'case "squadText": {');
        self::assertSame(1, substr_count(self::code($draw), 'forEach allMapMarkers'));
        self::assertStringContainsString('if ((_mkSnap select 0) isNotEqualTo diag_frameNo) then {', $draw);
        self::assertStringContainsString('_mkSnap = [diag_frameNo, _snapI, _snapS];', $draw);
        self::assertStringContainsString('} forEach (_mkSnap select 1);', $draw);
        self::assertStringContainsString('} forEach (_mkSnap select 2);', $draw);
        // Mêmes filtres qu'avant : visibles seulement, 40 symboles au plus, les trois formes tracées.
        self::assertStringContainsString('if ((markerAlpha _x) > 0) then {', $draw);
        self::assertStringContainsString('if (_mi >= 40) exitWith {};', $draw);
        self::assertStringContainsString('if (_sh in ["POLYLINE", "RECTANGLE", "ELLIPSE"]) then { _snapS pushBack [_x, _sh]; };', $draw);
        // Table des symboles OTAN : une fois par dessin, plus une par nœud.
        self::assertSame(1, substr_count($draw, 'private _natoTex = createHashMapFromArray'));
        self::assertStringNotContainsString('(createHashMapFromArray [["veh", "motor_inf"]', $draw);
    }

    /**
     * Plaques des écrans à carte : l'index par cases rend exactement le résultat du parcours complet d'avant. Les
     * constantes (taille de case, bornes) sont lues dans le SQF.
     */
    public function testPlateIndexGivesTheSameAnswerAsTheFullScan(): void
    {
        $draw = self::between(self::read('fn_tocScreen.sqf'), 'case "draw": {', 'case "squadText": {');
        self::assertStringNotContainsString('_taken', $draw);
        self::assertSame(9, substr_count($draw, '] call _take;'));
        $test = '_x0 < ((_x select 0) + (_x select 2)) && {(_x0 + _w) > (_x select 0)} && {_y0 < ((_x select 1) + (_x select 3))} && {(_y0 + _h) > (_x select 1)}';
        self::assertSame(1, substr_count($draw, $test), 'test de recouvrement modifié');
        self::assertSame(1, preg_match('/for "_i" from \(\(\(floor \(_x0 \/ (\d+)\)\) max (-?\d+)\) min (\d+)\) to \(\(\(floor \(\(_x0 \+ _w\) \/ \1\)\) max \2\) min \3\) do \{/', $draw, $mx));
        self::assertSame(1, preg_match('/for "_j" from \(\(\(floor \(_y0 \/ (\d+)\)\) max (-?\d+)\) min (\d+)\) to \(\(\(floor \(\(_y0 \+ _h\) \/ \1\)\) max \2\) min \3\) do \{/', $draw, $my));
        [$cell, $loX, $hiX] = [(int) $mx[1], (int) $mx[2], (int) $mx[3]];
        [$cellY, $loY, $hiY] = [(int) $my[1], (int) $my[2], (int) $my[3]];
        self::assertSame($cell, $cellY);
        $span = static fn (float $a, float $b, int $lo, int $hi): array => [min($hi, max($lo, (int) floor($a / $cell))), min($hi, max($lo, (int) floor($b / $cell)))];
        $hit = static fn (array $r, array $t): bool => $r[0] < $t[0] + $t[2] && $r[0] + $r[2] > $t[0] && $r[1] < $t[1] + $t[3] && $r[1] + $r[3] > $t[1];
        mt_srand(4);
        for ($round = 0; $round < 40; $round++) {
            $all = [];
            $grid = [];
            for ($n = 0; $n < 220; $n++) {
                // Plaques de toutes tailles, dont certaines hors du cadre 1600 x 900 (bornes de l'index).
                $r = [mt_rand(-400, 1900) + mt_rand(0, 9) / 10, mt_rand(-300, 1100) + mt_rand(0, 9) / 10, mt_rand(8, 620), mt_rand(8, 90)];
                [$i0, $i1] = $span($r[0], $r[0] + $r[2], $loX, $hiX);
                [$j0, $j1] = $span($r[1], $r[1] + $r[3], $loY, $hiY);
                $full = false;
                foreach ($all as $t) {
                    if ($hit($r, $t)) {
                        $full = true;
                        break;
                    }
                }
                $fast = false;
                for ($i = $i0; $i <= $i1 && !$fast; $i++) {
                    for ($j = $j0; $j <= $j1 && !$fast; $j++) {
                        foreach ($grid[$i . ':' . $j] ?? [] as $t) {
                            if ($hit($r, $t)) {
                                $fast = true;
                                break;
                            }
                        }
                    }
                }
                self::assertSame($full, $fast, "tour {$round}, plaque {$n}");
                if (!$full) {
                    $all[] = $r;
                    for ($i = $i0; $i <= $i1; $i++) {
                        for ($j = $j0; $j <= $j1; $j++) {
                            $grid[$i . ':' . $j][] = $r;
                        }
                    }
                }
            }
        }
    }

    public function testMosaicRendersFourCamerasAtMostAndReusesItsTargets(): void
    {
        $mos = self::read('fn_tocMosaic.sqf');
        self::assertStringContainsString('((((round _max) - _busy) max 0) * 4) min 4', self::between($mos, 'case "budget": {', 'case "tiles": {'));
        self::assertStringContainsString('if (({ !isNull (_x select 0) } count (values _cams)) >= (["budget"] call _fn)) exitWith { "" };', $mos);
        // Nom de cible repris par taille d'image ; un nom neuf seulement quand il n'y en a pas de libre.
        $tex = self::between($mos, 'case "tex": {', 'case "tick": {');
        self::assertStringContainsString('getOrDefault [_res, []];', $tex);
        self::assertStringContainsString('private _rt = if ((count _freeRt) > 0) then { _freeRt deleteAt 0 } else {', $tex);
        self::assertSame(1, substr_count($mos, 'format ["comspec_tocmos_%1", _n]'));
        $tick = substr($mos, (int) strpos($mos, 'case "tick": {'));
        self::assertStringContainsString('_freeRt pushBackUnique _rt;', $tick);
        self::assertStringContainsString('["close", [_cam, _rt]] call comspec_atak_native_fnc_tocRtt;', $tick);
    }

    public function testLaptopMapReplaysItsDrawListAndNeverBuildsTheFeed(): void
    {
        $lap = self::read('fn_tocLaptop.sqf');
        $draw = substr($lap, (int) strrpos($lap, '    case "draw": {'));
        // Flux : relu, jamais construit dans le dessin ; c'est le passage de la seconde qui le construit.
        self::assertStringContainsString('private _feed = ["peek", _side] call comspec_atak_native_fnc_tocAppData;', $draw);
        self::assertStringNotContainsString('call _feedOf', $draw);
        self::assertStringContainsString('private _feed = call _feedOf;', self::between($lap, 'case "refresh": {', '    case "draw": {'));
        // Liste de dessin : refaite seulement si sa clé change, rejouée sinon.
        self::assertStringContainsString('if ((_drawList select 0) isNotEqualTo _viewKey) then {', $draw);
        self::assertStringContainsString('{ if ((_x select 0) isEqualTo 0) then { _map drawIcon (_x select 1); } else { _map drawArrow (_x select 1); }; } forEach (_drawList select 1);', $draw);
        foreach (['_dk', '_selU', '_map ctrlMapWorldToScreen [0, 0, 0]', '_map ctrlMapWorldToScreen [1000, 1000, 0]', '_ts', '_sideRgb', '_accent', '_op', 'floor (diag_tickTime / 2)'] as $part) {
            self::assertStringContainsString($part, self::between($draw, 'private _viewKey = [', 'private _drawList = '), "clé de la liste de dessin sans {$part}");
        }
        // Dans la construction de la liste, plus aucun dessin direct des nœuds.
        $buildList = self::between($draw, 'if ((_drawList select 0) isNotEqualTo _viewKey) then {', '_st set ["drawList", _drawList];');
        self::assertStringNotContainsString('_map drawIcon', $buildList);
        self::assertStringNotContainsString('_map drawArrow', $buildList);
        // Chevauchement des étiquettes : trois bandes voisines, plus toutes les étiquettes posées.
        self::assertStringNotContainsString('_taken', $draw);
        self::assertStringContainsString('private _rowH = _ts * 1.1;', $buildList);
        self::assertStringContainsString('} forEach [_row - 1, _row, _row + 1];', $buildList);
        self::assertSame(1, substr_count($buildList, 'private _natoTex = createHashMapFromArray'));
        // Console : flux gardé avec son heure de construction, lecture sans calcul pendant 4 s.
        $data = self::read('fn_tocAppData.sqf');
        self::assertStringContainsString('uiNamespace setVariable ["COMSPEC_ATAK_TocAppFeed", [_key, _feed, diag_tickTime]];', $data);
        self::assertStringContainsString('{(diag_tickTime - (_c param [2, -1e9])) < 4}) exitWith { _c select 1 };', self::between($data, 'case "peek": {', 'case "unit": {'));
        self::assertStringContainsString('["TOC : flux (construction)", _perfT] call comspec_atak_native_fnc_perfAcc;', $data);
        self::assertSame(1, substr_count(self::code($data), 'forEach allUnits'));
    }

    /** Étiquettes de la carte du PC : la recherche dans trois bandes rend le résultat du parcours complet. */
    public function testLabelBandsGiveTheSameAnswerAsTheFullScan(): void
    {
        $ts = 0.027;
        $rowH = $ts * 1.1;
        $hit = static fn (array $r, array $t): bool => abs($t[0] - $r[0]) < ($t[2] + $r[2]) / 2 && abs($t[1] - $r[1]) < ($t[3] + $r[3]) / 2;
        mt_srand(11);
        for ($round = 0; $round < 40; $round++) {
            $all = [];
            $rows = [];
            for ($n = 0; $n < 250; $n++) {
                $k = [1, 1, 1, 0.85][mt_rand(0, 3)];
                $w = mt_rand(2, 45) * $ts * 0.42 * $k + $ts;
                $r = [mt_rand(-200, 1200) / 1000 + $w / 2, mt_rand(-200, 1200) / 1000, $w, $ts * 1.1 * $k];
                $row = (int) floor($r[1] / $rowH);
                $full = false;
                foreach ($all as $t) {
                    if ($hit($r, $t)) {
                        $full = true;
                        break;
                    }
                }
                $fast = false;
                foreach ([$row - 1, $row, $row + 1] as $b) {
                    foreach ($rows[$b] ?? [] as $t) {
                        if ($hit($r, $t)) {
                            $fast = true;
                            break 2;
                        }
                    }
                }
                self::assertSame($full, $fast, "tour {$round}, étiquette {$n}");
                if (!$full) {
                    $all[] = $r;
                    $rows[$row][] = $r;
                }
            }
        }
    }

    public function testServerFeedReadsUnitsOncePerPass(): void
    {
        $feed = self::read('fn_tocFeed.sqf');
        $tick = substr($feed, (int) strrpos($feed, '    case "tick": {'));
        self::assertSame(1, substr_count(self::code($tick), 'forEach allUnits'));
        self::assertStringContainsString('{ private _l = _bySide get (str side group _x); if (!isNil "_l") then { _l pushBack _x; }; } forEach allUnits;', $tick);
        self::assertStringContainsString('} forEach (_bySide getOrDefault [_side, []]);', $tick);
        // Classement fait seulement si un camp a des lecteurs, et jamais quand les téléphones ne sont pas lus.
        self::assertLessThan(strpos($tick, 'if (!_sorted && {_srcMode isNotEqualTo "GAME"}) then {'), strpos($tick, 'if (_viewers isEqualTo []) then { continue };'));
        // Indicatif gardé par unité, vidé avec les autres tables.
        self::assertStringContainsString('private _csC = COMSPEC_ATAK_TocCs getOrDefault [_key, ["", -1e9]];', $tick);
        self::assertStringContainsString('_csC = [_csN, diag_tickTime + 20 + random 10];', $tick);
        self::assertStringContainsString('COMSPEC_ATAK_TocSeen = createHashMap; COMSPEC_ATAK_TocCs = createHashMap; };', $tick);
        // Précision et cadence d'envoi inchangées : positions au mètre, renvoi sur changement ou toutes les 9 s.
        self::assertStringContainsString('[round (_pos select 0), round (_pos select 1)], round _dir', $tick);
        self::assertStringContainsString('{(diag_tickTime - (_st select 1)) > 9}', $tick);
        self::assertStringContainsString('["TOC : flux serveur (passage)", _perfT] call comspec_atak_native_fnc_perfAcc;', $tick);
    }
}
