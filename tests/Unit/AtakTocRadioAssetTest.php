<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Activité radio du poste TOC (demande de TTRD : « une UI PTT pour savoir qui parle, sur quelle fréquence »).
 * Lecture du code SQF : les calculs sans effet de fn_tocRadio (clé d'un réseau, liste de veille, tri de ce que voit
 * le poste, collision, silence, historique, occupation, messages d'état) sont réécrits ici et rejoués sur la table
 * _vectors du code (la même table est rejouée en jeu par « selfCheck »). Le reste vérifie le branchement : affichage
 * RADIO des écrans, console, encadré, réglages, traductions, et ce qui ne doit jamais arriver (appel direct d'ACRE2
 * ou de TFAR, dépendance dure, boucle, message par-dessus le jeu).
 */
final class AtakTocRadioAssetTest extends TestCase
{
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';
    private const TOC = self::MAIN . 'functions/toc/';

    private static function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path));
    }

    /** Table JSON du code SQF : « // ---- TABLE _nom » … « private _nom = [ … ]; » … « // ---- FIN ». */
    private static function table(string $src, string $name): array
    {
        self::assertSame(1, preg_match('/\/\/ ---- TABLE ' . preg_quote($name, '/') . ' .*?private ' . preg_quote($name, '/') . ' = (\[.*?\]);\n\s*\/\/ ---- FIN/s', $src, $m), $name);
        $data = json_decode($m[1], true);
        self::assertIsArray($data, $name . ' : JSON strict attendu (' . json_last_error_msg() . ')');

        return $data;
    }

    // ---- Mêmes calculs que fn_tocRadio.sqf ----

    private static function fmt(float $n): string
    {
        return $n > 0 ? number_format(round($n * 1000) / 1000, 3, '.', '') : '';
    }

    private static function key(mixed $v): string
    {
        if (is_int($v) || is_float($v)) {
            return self::fmt((float) $v);
        }
        if (!is_string($v)) {
            return '';
        }
        $s = mb_strtoupper(trim($v));
        if (preg_match('/^[0-9]+(\.[0-9]+)?( ?MHZ)?$/', $s) === 1) {
            return self::fmt((float) preg_replace('/ ?MHZ$/', '', $s));
        }

        return mb_substr($s, 0, 24);
    }

    private static function isFreq(string $k): bool
    {
        return preg_match('/^[0-9]+\.[0-9]{3}$/', $k) === 1;
    }

    private static function parse(string $txt): array
    {
        $out = [];
        foreach (preg_split('/[,;\r\n]/', $txt) as $it) {
            $it = trim($it);
            $chk = str_ends_with($it, '*');
            if ($chk) {
                $it = trim(substr($it, 0, -1));
            }
            if ($it === '') {
                continue;
            }
            $name = '';
            $raw = $it;
            $eq = strpos($it, '=');
            if ($eq !== false) {
                $name = trim(substr($it, 0, $eq));
                $raw = trim(substr($it, $eq + 1));
            } else {
                $w = array_values(array_filter(explode(' ', $it), static fn (string $p): bool => $p !== ''));
                $tail = $w[count($w) - 1] ?? '';
                if (count($w) > 1 && self::isFreq(self::key($tail))) {
                    $raw = $tail;
                    $name = implode(' ', array_slice($w, 0, -1));
                }
            }
            $k = self::key($raw);
            if ($k === '' || in_array($k, array_column($out, 1), true)) {
                continue;
            }
            if ($name === '' && !self::isFreq($k)) {
                $name = $raw;
            }
            $out[] = [mb_substr($name, 0, 24), $k, $chk];
        }

        return array_slice($out, 0, 24);
    }

    private static function text(array $tbl): string
    {
        return implode(', ', array_map(static fn (array $r): string => (($r[0] !== '' && $r[0] !== $r[1]) ? $r[0] . '=' . $r[1] : $r[1]) . ($r[2] ? '*' : ''), $tbl));
    }

    private static function netName(string $k, array $tbl, string $lbl): string
    {
        foreach ($tbl as $r) {
            if ($r[1] === $k && $r[0] !== '') {
                return $r[0];
            }
        }
        if ($lbl !== '') {
            return $lbl;
        }

        return self::isFreq($k) ? $k . ' MHz' : $k;
    }

    private static function visible(array $rows, int $idx, array $tbl, bool $all): array
    {
        if ($all) {
            return $rows;
        }
        $keys = array_column($tbl, 1);

        return array_values(array_filter($rows, static fn (array $r): bool => in_array($r[$idx] ?? '', $keys, true)));
    }

    private static function clash(array $act): array
    {
        $out = [];
        foreach ($act as $e) {
            $n = count(array_filter($act, static fn (array $o): bool => $o[4] === $e[4]));
            if ($n > 1 && !in_array($e[4], $out, true)) {
                $out[] = $e[4];
            }
        }

        return $out;
    }

    private static function silence(float $last, float $now, float $min, float $ref): int
    {
        if ($min <= 0) {
            return -1;
        }
        $m = (int) floor(($now - max($last, $ref)) / 60);

        return $m >= $min ? $m : -1;
    }

    private static function push(array $list, mixed $row, int $n): array
    {
        $list[] = $row;

        return array_slice($list, max(0, count($list) - $n));
    }

    private static function occupancy(array $hist, array $act, string $k, float $now, float $win): int
    {
        $from = $now - $win;
        $sum = 0.0;
        foreach ($hist as $h) {
            if ($h[2] === $k) {
                $d = min($h[7], $now) - max($h[7] - $h[6], $from);
                if ($d > 0) {
                    $sum += $d;
                }
            }
        }
        foreach ($act as $e) {
            if ($e[4] === $k) {
                $sum += max(0, $now - max($e[9], $from));
            }
        }

        return (int) max(0, min(100, round(100 * $sum / max(1, $win))));
    }

    private static function apply(array $s, array $d, int $n): array
    {
        if (count($s) < 4) {
            $s = [0, [], [], []];
        }
        if ($d[0] === 'S') {
            return [$d[1], $d[2], $d[3], $d[4]];
        }
        $rev = $d[1] ?? -1;
        if ($rev <= $s[0]) {
            return $s;
        }
        if ($rev > $s[0] + 1) {
            return [];
        }
        [, $act, $hist, $nets] = $s;
        if ($d[0] === 'B') {
            $act = array_values(array_filter($act, static fn (array $e): bool => $e[0] !== $d[2][0]));
            $act[] = $d[2];
        } elseif ($d[0] === 'E') {
            $act = array_values(array_filter($act, static fn (array $e): bool => $e[0] !== $d[2]));
            if (($d[3] ?? []) !== []) {
                $hist = self::push($hist, $d[3], $n);
            }
            if (($d[4] ?? []) !== []) {
                $nets = array_values(array_filter($nets, static fn (array $e): bool => $e[0] !== $d[4][0]));
                $nets[] = $d[4];
                $nets = array_slice($nets, max(0, count($nets) - 40));
            }
        }

        return [$rev, $act, $hist, $nets];
    }

    private static function dur(float $t): string
    {
        $t = (int) max(0, round($t));

        return sprintf('%d:%02d', intdiv($t, 60), $t % 60);
    }

    private static function ago(float $t): string
    {
        $t = (int) max(0, floor($t));
        if ($t < 60) {
            return $t . ' s';
        }
        if ($t < 3600) {
            return intdiv($t, 60) . ' min';
        }

        return sprintf('%d h %02d', intdiv($t, 3600), intdiv($t % 3600, 60));
    }

    private static function calc(string $fn, mixed $a): mixed
    {
        return match ($fn) {
            'key' => self::key($a),
            'isFreq' => self::isFreq($a),
            'parse' => self::parse($a),
            'text' => self::text($a),
            'netName' => self::netName($a[0], $a[1], $a[2]),
            'visible' => self::visible($a[0], $a[1], $a[2], $a[3]),
            'clash' => self::clash($a),
            'silence' => self::silence($a[0], $a[1], $a[2], $a[3]),
            'push' => self::push($a[0], $a[1], $a[2]),
            'occupancy' => self::occupancy($a[0], $a[1], $a[2], $a[3], $a[4]),
            'apply' => self::apply($a[0], $a[1], $a[2]),
            'dur' => self::dur($a),
            'ago' => self::ago($a),
        };
    }

    public function testPureFunctionsMatchTheVectorsOfTheCode(): void
    {
        $src = self::read(self::TOC . 'fn_tocRadio.sqf');
        $vectors = self::table($src, '_vectors');
        self::assertGreaterThanOrEqual(30, count($vectors));
        $seen = [];
        foreach ($vectors as $i => [$fn, $arg, $want]) {
            $seen[$fn] = true;
            self::assertSame(json_encode($want), json_encode(self::calc($fn, $arg)), "vecteur {$i} ({$fn})");
        }
        // Chaque calcul demandé a ses vecteurs, et le code les rejoue en jeu.
        foreach (['key', 'isFreq', 'parse', 'text', 'netName', 'visible', 'clash', 'silence', 'push', 'occupancy', 'apply', 'dur', 'ago'] as $fn) {
            self::assertArrayHasKey($fn, $seen, "aucun vecteur pour {$fn}");
            self::assertStringContainsString('["' . $fn . '", ', self::between($src, 'private _pure = createHashMapFromArray [', '];'), "{$fn} absent de la table des calculs");
        }
        self::assertStringContainsString('case "selfCheck": {', $src);
        self::assertStringContainsString('if (_mode in _pure) exitWith { _a call (_pure get _mode) };', $src);
    }

    public function testFrequencyAndNetNaming(): void
    {
        // Fréquence en MHz à 3 décimales, quel que soit ce que donne le mod radio (nombre ACRE2, texte TFAR).
        self::assertSame('31.000', self::key(31));
        self::assertSame('31.000', self::key('31'));
        self::assertSame('40.500', self::key('40.5 MHz'));
        self::assertSame('148.525', self::key(148.525));
        self::assertSame('', self::key(-3));
        // Table des réseaux nommés (exemples de la demande) puis nom du canal du mod radio, puis la fréquence.
        $tbl = self::parse('CMD NET 31.000, AIR 40.500, ALPHA 1-1=45.125*');
        self::assertSame([['CMD NET', '31.000', false], ['AIR', '40.500', false], ['ALPHA 1-1', '45.125', true]], $tbl);
        self::assertSame('CMD NET', self::netName('31.000', $tbl, 'Canal 1'));
        self::assertSame('Canal 4', self::netName('60.000', $tbl, 'Canal 4'));
        self::assertSame('60.000 MHz', self::netName('60.000', $tbl, ''));
        // Aller-retour texte -> liste -> texte (ce que le serveur fait d'une liste reçue d'un joueur).
        self::assertSame($tbl, self::parse(self::text($tbl)));
        // 24 réseaux au plus, pas de doublon.
        self::assertCount(24, self::parse(implode(',', array_map(static fn (int $i): string => (string) (30 + $i), range(0, 40)))));
        self::assertCount(1, self::parse('31, 31.0, 31.000 MHz'));
    }

    public function testWhatTheTocSeesClashSilenceAndHistory(): void
    {
        $tbl = self::parse('CMD=31.000*, AIR=40.500');
        $act = [['a', 'ALPHA', '', '', '31.000'], ['b', 'BRAVO', '', '', '77.000'], ['c', 'CHARLIE', '', '', '31.000']];
        // Le poste ne voit que sa liste de veille ; « tout surveiller » montre tout.
        self::assertSame(['a', 'c'], array_column(self::visible($act, 4, $tbl, false), 0));
        self::assertCount(3, self::visible($act, 4, $tbl, true));
        self::assertSame([], self::visible($act, 4, [], false));
        // Deux émetteurs sur le même réseau : collision ; une émission cachée n'en crée pas.
        self::assertSame(['31.000'], self::clash(self::visible($act, 4, $tbl, false)));
        self::assertSame([], self::clash([$act[0], $act[1]]));
        // Silence : compté depuis la dernière fin d'émission, ou depuis l'allumage si le réseau n'a jamais parlé.
        self::assertSame(12, self::silence(0, 12 * 60 + 5, 10, 0));
        self::assertSame(-1, self::silence(0, 9 * 60, 10, 0));
        self::assertSame(-1, self::silence(-1, 2000, 10, 1900));
        // Historique : 50 dernières émissions, la plus ancienne sort.
        $h = [];
        for ($i = 1; $i <= 60; $i++) {
            $h = self::push($h, $i, 50);
        }
        self::assertCount(50, $h);
        self::assertSame([11, 60], [$h[0], $h[49]]);
        self::assertStringContainsString('private _max = 50;', self::read(self::TOC . 'fn_tocRadio.sqf'));
    }

    public function testStateMessagesAreAppliedInOrderAndAGapAsksForTheFullState(): void
    {
        $s = [0, [], [], []];
        $s = self::apply($s, ['B', 1, ['u1', 'ALPHA', '', '', '31.000']], 50);
        $s = self::apply($s, ['B', 2, ['u2', 'BRAVO', '', '', '31.000']], 50);
        self::assertSame(['u1', 'u2'], array_column($s[1], 0));
        $s = self::apply($s, ['E', 3, 'u1', ['ALPHA', '', '31.000', '', 'SW', '12:00:00', 4.2, 100, true], ['31.000', '', 'ALPHA', 100, 1, 4]], 50);
        self::assertSame([3, ['u2'], 1, 1], [$s[0], array_column($s[1], 0), count($s[2]), count($s[3])]);
        // Fin d'un simple clic d'alternat : l'émission disparaît, rien n'entre dans l'historique.
        $s = self::apply($s, ['E', 4, 'u2', [], []], 50);
        self::assertSame([4, [], 1], [$s[0], $s[1], count($s[2])]);
        // Message déjà contenu dans l'état complet reçu à l'abonnement : ignoré. Message manqué : [] (état redemandé).
        self::assertSame($s, self::apply($s, ['B', 4, ['u9']], 50));
        self::assertSame([], self::apply($s, ['B', 6, ['u9']], 50));
        // État complet (joueur qui rejoint) : remplace tout, même avec une révision plus petite.
        self::assertSame([2, [['u7']], [], []], self::apply($s, ['S', 2, [['u7']], [], []], 50));
        $src = self::read(self::TOC . 'fn_tocRadio.sqf');
        self::assertStringContainsString('if (_gap) then { ["sub", "resync"] call comspec_atak_native_fnc_tocRadio; };', $src);
        self::assertStringContainsString('["comspec_atak_native_tocRadioMsg", [_x, [["S", _s select 0, _s select 1, _s select 2, _s select 3]]], _owner] call CBA_fnc_ownerEvent;', $src);
    }

    public function testCollectionIsEventDrivenAndSilentWhileNobodyWatches(): void
    {
        $src = self::read(self::TOC . 'fn_tocRadio.sqf');
        // Aucune boucle dans le modèle : ni gestionnaire d'image, ni script en attente.
        self::assertDoesNotMatchRegularExpression('/addPerFrameHandler|\bwhile\b|\bspawn\b|\bsleep\b|waitUntil/', $src);
        // Collecte éteinte : rien ne part du joueur, le serveur n'écoute pas.
        self::assertStringContainsString('if ((missionNamespace getVariable ["COMSPEC_ATAK_TocRadioOn", -1]) < 0 || {!(call _enabled)}) exitWith { false };', $src);
        self::assertStringContainsString('missionNamespace setVariable ["COMSPEC_ATAK_TocRadioOn", serverTime max 0, true];', $src);
        // Envois regroupés (0,25 s), aux abonnés du camp seulement ; Zeus et administrateur : tous les camps.
        self::assertStringContainsString('[{ ["srvFlush"] call comspec_atak_native_fnc_tocRadio; }, [], 0.25] call comspec_atak_native_fnc_waitAndExec;', $src);
        self::assertStringContainsString('if (_y in [_side, "*"]) then { ["comspec_atak_native_tocRadioMsg", [_side, _list], _x] call CBA_fnc_ownerEvent; };', $src);
        self::assertStringContainsString('private _wide = _scope isEqualTo "*" && {!isNull (getAssignedCuratorLogic _u) || {(admin _owner) > 0} || {_owner <= 2}};', $src);
        // Seules trois demandes viennent des joueurs ; une émission sans fin est close d'office.
        self::assertStringContainsString('if (_m in ["srvTx", "srvSub", "srvNets"]) then {', $src);
        self::assertStringContainsString('addMissionEventHandler ["PlayerDisconnected", {', $src);
        self::assertStringContainsString('[_side, _e select 0, _e select 9], 180] call comspec_atak_native_fnc_waitAndExec;', $src);
        // Démarrage : un appel côté serveur, deux côté joueur, aucun dans une boucle.
        self::assertStringContainsString('["initServer"] call comspec_atak_native_fnc_tocRadio;', self::read(self::MAIN . 'XEH_postInitServer.sqf'));
        $cli = self::read(self::MAIN . 'XEH_postInitClient.sqf');
        self::assertStringContainsString('["initClient"] call comspec_atak_native_fnc_tocRadio;', $cli);
        self::assertStringContainsString('["initClient"] call comspec_atak_native_fnc_tocRadioUi;', $cli);
    }

    public function testRadioModsAreSoftAndOnlyReachedThroughTheGuard(): void
    {
        // Aucune dépendance dure à ACRE2 ni à TFAR.
        $cfg = self::read(self::MAIN . 'config.cpp');
        self::assertSame(1, preg_match('/requiredAddons\[\]\s*=\s*\{([^}]*)\}/', $cfg, $m));
        self::assertDoesNotMatchRegularExpression('/tfar|acre|task_force/i', $m[1]);
        foreach (['fn_tocRadio.sqf', 'fn_tocRadioUi.sqf', 'fn_moduleTocRadio.sqf'] as $f) {
            $src = self::read(self::TOC . $f);
            // Fonctions d'ACRE2 et de TFAR : toujours un nom entre guillemets remis à fn_radioGuard « api », jamais un appel.
            self::assertDoesNotMatchRegularExpression('/(?<!")\b(acre_api_fnc_|acre_sys_|TFAR_fnc_)\w+/', $src, $f);
            self::assertDoesNotMatchRegularExpression('/\b(call|spawn)\s+(acre_|TFAR_)/i', $src, $f);
        }
        $src = self::read(self::TOC . 'fn_tocRadio.sqf');
        self::assertStringContainsString('private _rg = { ["api", _this] call comspec_atak_native_fnc_radioGuard };', $src);
        self::assertGreaterThanOrEqual(3, substr_count($src, '["read"] call comspec_atak_native_fnc_radioGuard'));
        // Les trois sources : ACRE2 (événements CBA), TFAR (alternat), touches du jeu sans mod radio.
        self::assertStringContainsString('["acre_startedSpeaking", {', $src);
        self::assertStringContainsString('["TFAR_fnc_addEventHandler", ["COMSPEC_ATAK_TocRadio", "OnTangent", {', $src);
        self::assertStringContainsString('if (!_hasAcre && {!_hasTfar}) then {', $src);
        self::assertStringContainsString('isClass (configFile >> "CfgPatches" >> "tfar_core")', $src);
        self::assertStringContainsString('isClass (configFile >> "CfgPatches" >> "acre_main")', $src);
    }

    public function testRadioViewIsRegisteredOnTheWallScreens(): void
    {
        $cfg = self::read(self::MAIN . 'config.cpp');
        foreach (['class tocRadio {};', 'class tocRadioUi {};', 'class moduleTocRadio {};', '"COMSPEC_ATAK_Module_TocRadio"', 'value="RADIO"'] as $n) {
            self::assertStringContainsString($n, $cfg);
        }
        $scr = self::read(self::TOC . 'fn_tocScreen.sqf');
        self::assertMatchesRegularExpression('/private _views = \[[^\]]*"REQ", "RADIO", "STRIP"[^\]]*\];/', $scr);
        self::assertMatchesRegularExpression('/private _boards = \[[^\]]*"RADIO"[^\]]*\];/', $scr);
        self::assertStringContainsString('["RADIO", ["RADIO", "activité radio"]]', $scr);
        // Redessiné chaque seconde (durée de l'émission), comme l'horloge.
        self::assertMatchesRegularExpression('/select \(\(_cfg select 3\) in \[[^\]]*"RADIO"[^\]]*\]\)/', $scr);
        $board = self::read(self::TOC . 'fn_tocBoard.sqf');
        self::assertStringContainsString('case "RADIO": {', $board);
        self::assertStringContainsString('["wall", [_side, createHashMapFromArray [["text", _text], ["box", _box], ["tiles", _tiles], ["section", _sectionW], ["table", _table], ["rg", _rg]]]] call comspec_atak_native_fnc_tocRadioUi;', $board);
        // Mise en page et colonnes dans le thème (seule source des régions et des couleurs).
        $thm = self::read(self::TOC . 'fn_tocTheme.sqf');
        $walls = array_column(self::table($thm, '_walls'), 1, 0);
        self::assertSame(['kpi', 'live', 'head', 'sub', 'rule', 'cols', 'area'], array_column($walls['RADIO'], 0));
        $cols = array_column(self::table($thm, '_cols'), 1, 0);
        self::assertSame(['dot', 'net', 'freq', 'st', 'who', 'last', 'n', 'occ'], array_column($cols['RADIO'], 0));
        // L'affichage est offert partout où les autres le sont : module Écran TOC, Eden, menu de l'écran.
        self::assertStringContainsString('["Activité radio (qui parle, sur quelle fréquence)", "RADIO"]', self::read(self::TOC . 'fn_moduleTocScreen.sqf'));
        self::assertStringContainsString('value = "RADIO";', self::read(self::MAIN . 'modules.hpp'));
        $menu = self::read(self::TOC . 'fn_tocMenu.sqf');
        self::assertStringContainsString('["RADIO", ["view|RADIO", "Activité radio", ', $menu);
        self::assertStringContainsString('case (_v in ["CLOCK", "LOG", "EXCHECK", "KILLS", "REQ", "RADIO"]): { _brdK pushBack _n; };', $menu);
        self::assertStringContainsString('["Radio", "Activité radio (console)…", ', $menu);
        self::assertStringContainsString('case "radio": {', $menu);
    }

    public function testWallTextFitsAndNothingFlashes(): void
    {
        $ui = self::read(self::TOC . 'fn_tocRadioUi.sqf');
        $wall = self::between($ui, 'case "wall": {', 'case "open": {');
        // Rien ne clignote ni ne s'anime sur le mur : pas de boucle propre, l'image suit la boucle des écrans.
        self::assertDoesNotMatchRegularExpression('/addPerFrameHandler|ctrlSetFade|blink|diag_tickTime/', $wall);
        // Jamais de texture transparente (dalle blanche) ni de couleur hors du thème.
        foreach (['fn_tocRadioUi.sqf', 'fn_tocRadio.sqf'] as $f) {
            self::assertStringNotContainsString('#(argb', self::read(self::TOC . $f), $f);
        }
        self::assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{6}\b/', $ui);
        // Indicatif et fréquence en grand : largeur de la case contre la largeur estimée du texte (fn_tocBoard « _text »).
        $thm = self::read(self::TOC . 'fn_tocTheme.sqf');
        $type = array_column(self::table($thm, '_type'), 1, 0);
        $live = array_column(array_column(self::table($thm, '_walls'), 1, 0)['RADIO'], null, 0)['live'];
        $monoBold = 0.8 * 1.08;
        // Une émission : fréquence « 148.500 » (7 caractères) en « hero » dans 460 px ; deux : en « num » dans 300 px.
        self::assertLessThanOrEqual(460 - 8, 7 * $type['hero'] * $monoBold);
        self::assertLessThanOrEqual(300 - 8, 7 * $type['num'] * $monoBold);
        self::assertStringContainsString('private _fw = [[0, 0], [300, 460]] select _isF select _big;', $wall);
        // Indicatif de 12 caractères lisible en entier dans les deux cas.
        self::assertLessThanOrEqual($live[3] - 460 - 52 - 8, 12 * $type['hero'] * $monoBold);
        self::assertLessThanOrEqual(($live[3] - 16) / 2 - 300 - 52 - 8, 12 * $type['squadName'] * 0.56 * 1.08);
        // Les deux lignes de la case tiennent dans sa hauteur ; le tableau montre 10 réseaux.
        self::assertGreaterThanOrEqual(98 + 40, $live[4]);
        $walls = array_column(self::table($thm, '_walls'), 1, 0);
        self::assertSame(10, intdiv(array_column($walls['RADIO'], null, 0)['area'][4], 40));
        // Titre de l'écran : ce qu'il montre, jamais le nom du modèle (en-tête de fn_tocBoard, table « title »).
        self::assertStringNotContainsString('displayName', $wall);
    }

    public function testConsoleAndHudNeverUseAGamePopup(): void
    {
        foreach (['fn_tocRadio.sqf', 'fn_tocRadioUi.sqf', 'fn_moduleTocRadio.sqf'] as $f) {
            self::assertDoesNotMatchRegularExpression('/\b(hint|hintSilent|hintC|systemChat|titleText|cutText|BIS_fnc_showNotification|BIS_fnc_dynamicText|comspec_atak_native_fnc_notify|zen_common_fnc_showMessage)\b/', self::read(self::TOC . $f), $f);
        }
        $ui = self::read(self::TOC . 'fn_tocRadioUi.sqf');
        // Panneau autonome que la console du poste peut monter : « mount » rend ses contrôles, « refresh » le redessine.
        self::assertStringContainsString('case "mount": {', $ui);
        self::assertStringContainsString('_a params [["_d", displayNull], ["_rect", [0, 0, 1, 1]], ["_side", "WEST"], ["_all", false], ["_grp", controlNull]];', $ui);
        self::assertStringContainsString('case "refresh": {', $ui);
        // Les résultats vont dans la ligne d'état du panneau.
        self::assertGreaterThanOrEqual(6, substr_count($ui, 'call _say;'));
        // Sections sans petit libellé au-dessus du titre : un seul texte par en-tête.
        self::assertSame(4, substr_count($ui, '] call _headOf;'));
        foreach (['"EN TRANSMISSION"', '"RÉSEAUX"', '"LISTE DE VEILLE"', '"HISTORIQUE"'] as $h) {
            self::assertStringContainsString($h . '] call _headOf;', $ui);
        }
        // Les boucles s'arrêtent seules : panneau fermé, encadré vide.
        self::assertStringContainsString('if (_h >= 0) then { [_h] call CBA_fnc_removePerFrameHandler; };', $ui);
        self::assertStringContainsString('if (_lines isEqualTo []) exitWith { ["hudHide"] call comspec_atak_native_fnc_tocRadioUi };', $ui);
        // Encadré : calque RscTitles du mod, réglage du joueur décoché par défaut, coché au Zeus.
        $disp = self::read(self::MAIN . 'ui/displays.hpp');
        self::assertStringContainsString('class COMSPEC_RscTitleTocRadioHud {', $disp);
        foreach (['idc=7781', 'idc=7782', 'idc=7783'] as $idc) {
            self::assertSame(1, substr_count($disp, $idc), $idc);
        }
        self::assertStringContainsString('cutRsc ["COMSPEC_RscTitleTocRadioHud", "PLAIN", 0, false];', $ui);
        $pre = self::read(self::MAIN . 'XEH_preInit.sqf');
        self::assertMatchesRegularExpression('/\["comspec_atak_native_toc_radio_hud", "CHECKBOX",\n[^\n]+\n\s+_loc, false, 0, /', $pre);
        self::assertMatchesRegularExpression('/\["comspec_atak_native_toc_radio_hud_zeus", "CHECKBOX",\n[^\n]+\n\s+_loc, true, 0, /', $pre);
        self::assertMatchesRegularExpression('/\["comspec_atak_native_toc_radio_see_all", "CHECKBOX",\n[^\n]+\n\s+_loc, false, 1\]/', $pre);
    }

    public function testEverySettingAndEveryTextHasAnExampleAndEnglish(): void
    {
        $en = self::read(self::MAIN . 'i18n/en.txt');
        $xx = self::read('mod/COMSPEC_ATAK_Native/localisation/template/xx.txt');
        $has = static function (string $fr) use ($en, $xx): void {
            $q = '["' . str_replace('"', '""', $fr) . '", "';
            self::assertStringContainsString($q, $en, "« {$fr} » sans anglais");
            self::assertStringContainsString($q . '", "', $xx, "« {$fr} » absent du modèle de traduction");
        };
        // Réglages : titre et aide traduits, aide avec un exemple.
        $pre = self::read(self::MAIN . 'XEH_preInit.sqf');
        preg_match_all('/\["comspec_atak_native_toc_radio_\w+", "\w+",\n\s+\["((?:[^"]|"")+)", "((?:[^"]|"")+)"\],/', $pre, $set, PREG_SET_ORDER);
        self::assertCount(6, $set);
        foreach ($set as [, $title, $tip]) {
            self::assertStringContainsString('Exemple : ', $tip, $title);
            $has(str_replace('""', '"', $title));
            $has(str_replace('""', '"', $tip));
        }
        self::assertStringContainsString('Exemple : CMD NET=31.000*, AIR=40.500, 152.250.', $pre);
        // Textes du panneau, du mur et de l'encadré.
        $ui = self::read(self::TOC . 'fn_tocRadioUi.sqf');
        preg_match_all('/\("([^"\n]+)"\) call _rTr/', $ui, $a);
        preg_match_all('/\("([^"\n]+)"\) call _say/', $ui, $b);
        preg_match_all('/\["([^"\n]+)", "ctrl(?:Edit|Checkbox|Combo)", "([^"\n]+)"\] call _field/', $ui, $c);
        preg_match_all('/, "([^"\n]+)"\] call _headOf/', $ui, $d);
        $texts = array_merge($a[1], $b[1], $c[1], $c[2], $d[1]);
        self::assertGreaterThan(30, count($texts));
        foreach ($c[2] as $help) {
            self::assertStringContainsString(' Exemple : ', $help);
        }
        foreach (array_merge(self::table($ui, '_kinds'), self::table($ui, '_states')) as [, $v]) {
            $texts[] = is_array($v) ? $v[0] : $v;
        }
        foreach (['Canal %1', 'Canal camp', 'Canal commandement', 'Canal groupe · %1', 'Canal véhicule', 'activité radio', 'Activité radio', 'Activité radio (console)…',
            'en émission', 'réseaux veillés', 'collisions', 'silencieux', '+ %1 autres réseaux', 'Activité radio (qui parle, sur quelle fréquence)',
            'Aucune émission sur les réseaux veillés', 'Aucun réseau veillé : ajoutez une fréquence à la liste de veille (console radio du poste)', "Aucun réseau à montrer pour l'instant", 'RÉSEAUX',
            "Personne n'émet sur les réseaux veillés", 'Aucun réseau veillé : ajoutez une fréquence à droite, par exemple 31.000',
            "Les émissions s'inscrivent ici dès qu'un joueur du camp presse son alternat sur un réseau veillé.",
            'Réseau', 'Fréquence', 'État', 'Dernier émetteur', 'Entendu', 'Occupation', 'Indépendants', 'Civils'] as $t) {
            $texts[] = $t;
        }
        foreach (array_unique($texts) as $t) {
            $has($t);
        }
        // Module Zeus / Eden : nom, description et attributs dans les deux langues.
        $st = self::read(self::MAIN . 'stringtable.xml');
        $mod = self::read(self::MAIN . 'modules.hpp');
        preg_match_all('/\$(STR_COMSPEC_ATAK_CFG_TOCRADIO_\w+)/', $mod, $keys);
        self::assertGreaterThanOrEqual(8, count(array_unique($keys[1])));
        foreach (array_unique(array_merge($keys[1], ['STR_COMSPEC_ATAK_CFG_TOCSCR_V_RADIO'])) as $k) {
            self::assertSame(1, preg_match('/<Key ID="' . $k . '">\s*<Original>[^<]+<\/Original>\s*<English>[^<]+<\/English>\s*<French>[^<]+<\/French>/', $st), $k);
        }
        self::assertStringContainsString('Exemple : CMD NET=31.000*, AIR=40.500, 152.250.', $st);
    }

    private static function between(string $src, string $from, string $to): string
    {
        $a = strpos($src, $from);
        self::assertNotFalse($a, $from);
        $b = strpos($src, $to, $a + strlen($from));
        self::assertNotFalse($b, $to);

        return substr($src, $a, $b - $a);
    }
}
