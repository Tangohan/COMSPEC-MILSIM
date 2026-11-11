<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Traçage d'un numéro depuis l'ATAK (app GE, onglet TRAÇAGE) : le serveur seul calcule la position, rien ne s'affiche
 * par-dessus l'écran de jeu, les calculs purs donnent les valeurs annoncées.
 * Lecture du code seulement : rien de tout cela n'a tourné en jeu.
 */
final class AtakTraceNumeroTest extends TestCase
{
    private const MOD = 'mod/COMSPEC_ATAK_Native/';
    private const MAIN = self::MOD . 'Sources/addons/main/';
    private const TRACE = self::MAIN . 'functions/trace/';

    private static function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path));
    }

    /** @return array<string, string> dictionnaire fr => en (en.txt) ou fr => "" (xx.txt) */
    private static function dico(string $path): array
    {
        $out = [];
        foreach (explode("\n", self::read($path)) as $l) {
            if (preg_match('/^\["((?:[^"]|"")*)", (?:"(?:[^"]|"")*", )?"((?:[^"]|"")*)"\],?$/', $l, $m)) {
                $out[str_replace('""', '"', $m[1])] = str_replace('""', '"', $m[2]);
            }
        }

        return $out;
    }

    public function testAucunCanalDAlerteDuJeu(): void
    {
        foreach (['fn_traceCalc.sqf', 'fn_traceServer.sqf', 'fn_traceAction.sqf'] as $f) {
            $src = self::read(self::TRACE . $f);
            foreach (['hint ', 'hintSilent', 'hintC', 'systemChat', 'titleText', 'cutText', 'BIS_fnc_showNotification', 'sideChat', 'globalChat', 'groupChat', 'fnc_toast', 'BIS_fnc_dynamicText'] as $bad) {
                self::assertStringNotContainsString($bad, $src, "{$f} : {$bad}");
            }
        }
        // Résultats : centre de notifications du téléphone, onglet, carte, RPT.
        self::assertStringContainsString('comspec_atak_native_fnc_notify', self::read(self::TRACE . 'fn_traceAction.sqf'));
        self::assertStringContainsString('comspec_atak_native_fnc_log', self::read(self::TRACE . 'fn_traceServer.sqf'));
    }

    public function testLeServeurFaitFoiEtNeRepondQuAuDemandeur(): void
    {
        $srv = self::read(self::TRACE . 'fn_traceServer.sqf');
        self::assertStringContainsString('if (!isServer) exitWith { [] };', $srv);
        self::assertStringContainsString('(owner _unit) isNotEqualTo remoteExecutedOwner', $srv);
        self::assertStringContainsString('["comspec_atak_native_traceRes", [_token, _q, _qn, _code, _pos, _rad, _at, _kind, _wait], _unit] call CBA_fnc_targetEvent;', $srv);
        self::assertStringNotContainsString('CBA_fnc_globalEvent', $srv);
        self::assertStringNotContainsString('publicVariable', $srv);
        // Dernière position connue : mémoire du serveur, jamais diffusée.
        self::assertStringContainsString('missionNamespace setVariable ["COMSPEC_ATAK_TraceLast", _lastAll];', $srv);
        self::assertDoesNotMatchRegularExpression('/COMSPEC_ATAK_TraceLast", [^\]]*, true\]/', $srv);
        // Requête opérateur jamais instantanée, limites par joueur.
        self::assertStringContainsString('comspec_atak_native_trace_delay', $srv);
        self::assertStringContainsString('comspec_atak_native_fnc_waitAndExec', $srv);
        foreach (['"WAIT"', '"CAP"', '"NOLINK"', '"DENIED"', '"DISABLED"', 'comspec_atak_native_trace_cooldown', 'comspec_atak_native_trace_max'] as $k) {
            self::assertStringContainsString($k, $srv);
        }
        // Brouilleurs, relais (relief compris), réseau civil.
        self::assertStringContainsString('COMSPEC_ATAK_Jammers', $srv);
        self::assertStringContainsString('["link", [_x select 1, _x select 4, _x select 5, _tp]] call comspec_atak_native_fnc_relayNet', $srv);
        self::assertStringContainsString('comspec_atak_native_trace_civnet', $srv);
        // IA : profils SSE, attribut Eden, numéro calculé.
        foreach (['comspec_sse_fnc_getSection', 'digitalDevices', 'comspec_sse_personPhone', 'comspec_sse_logicalEntities', 'COMSPEC_ATAK_PhoneNumber', 'COMSPEC_ATAK_PhoneTrace', 'comspec_atak_native_fnc_phoneIdent'] as $k) {
            self::assertStringContainsString($k, $srv);
        }

        // Le téléphone ne lit aucune position d'unité : il demande et affiche la réponse.
        $cli = self::read(self::TRACE . 'fn_traceAction.sqf');
        self::assertStringContainsString('["req", [player, _q, _mode, _token]] remoteExecCall ["comspec_atak_native_fnc_traceServer", 2];', $cli);
        foreach (['getPosATL', 'getPosASL', 'allUnits', 'allDeadMen', 'visiblePosition'] as $bad) {
            self::assertStringNotContainsString($bad, $cli, $bad);
        }
    }

    public function testBranchementsEtConfig(): void
    {
        $cfg = self::read(self::MAIN . 'config.cpp');
        self::assertStringContainsString('class traceCalc {}; class traceServer {}; class traceAction {};', $cfg);
        self::assertStringContainsString('class PhoneTrace {', $cfg);
        self::assertStringContainsString('class COMSPEC_ATAK_PhoneTraceCat {', $cfg);
        self::assertStringContainsString("_this setVariable ['COMSPEC_ATAK_PhoneTrace', _value, true];", $cfg);
        self::assertStringContainsString("_this setVariable ['COMSPEC_ATAK_PhoneNumber', _value, true];", $cfg);
        $st = self::read(self::MAIN . 'stringtable.xml');
        preg_match_all('/\$(STR_COMSPEC_ATAK_CFG_[0-9A-F]{10})/', substr($cfg, (int) strpos($cfg, 'class PhoneTrace {'), 600), $m1);
        preg_match_all('/\$(STR_COMSPEC_ATAK_CFG_[0-9A-F]{10})/', substr($cfg, (int) strpos($cfg, 'class COMSPEC_ATAK_PhoneTraceCat {')), $m2);
        $keys = array_unique(array_merge($m1[1], $m2[1]));
        self::assertGreaterThanOrEqual(11, count($keys));
        foreach ($keys as $k) {
            self::assertStringContainsString('<Key ID="' . $k . '">', $st, $k);
        }

        self::assertStringContainsString('["init"] call comspec_atak_native_fnc_traceAction;', self::read(self::MAIN . 'XEH_postInitClient.sqf'));
        $ew = self::read(self::MAIN . 'functions/pages/fn_ewAction.sqf');
        self::assertStringContainsString('["tick"] call comspec_atak_native_fnc_traceAction;', $ew);
        self::assertStringNotContainsString('_geoFix', $ew);
        $page = self::read(self::MAIN . 'functions/pages/fn_pageEw.sqf');
        self::assertStringContainsString('["TRAÇAGE", "GEO"] call _tabBtn', $page);
        self::assertStringContainsString('_rows append (["rows"] call comspec_atak_native_fnc_traceAction);', $page);
        // Entrées « TRACER » : fichier ouvert de l'app Extraction, app SSE terrain (seulement si l'ATAK natif est chargé).
        self::assertStringContainsString('["numRows", [[_content], format ["Fichier %1", _fn]]] call comspec_atak_native_fnc_traceAction', self::read(self::MAIN . 'functions/datalink/fn_pageDataLink.sqf'));
        $field = self::read('mod/@COMSPEC_SSE/addons/compat_atak/functions/fn_fieldPage.sqf');
        self::assertStringContainsString('if (!isNil "comspec_atak_native_fnc_traceAction") then {', $field);
        // Carte : cercle qui grandit avec l'âge d'une dernière position.
        self::assertStringContainsString('["aged", [_r, _age, _grow, 5000]] call comspec_atak_native_fnc_traceCalc', self::read(self::MAIN . 'functions/map/fn_ewDraw.sqf'));
    }

    public function testReglagesAvecExempleEtTraduits(): void
    {
        $pre = self::read(self::MAIN . 'XEH_preInit.sqf');
        $en = self::dico(self::MAIN . 'i18n/en.txt');
        $xx = self::dico(self::MOD . 'localisation/template/xx.txt');
        preg_match_all('/\["comspec_atak_native_trace_[a-z_]+", "[A-Z]+",\n    \["([^"]+)", "([^"]+)"\]/', $pre, $m, PREG_SET_ORDER);
        self::assertCount(9, $m);
        foreach ($m as [, $title, $tip]) {
            self::assertStringContainsString('Ex. :', $tip, $title);
            foreach ([$title, $tip] as $t) {
                self::assertArrayHasKey($t, $en, "EN manquant : {$t}");
                self::assertNotSame('', $en[$t]);
                self::assertArrayHasKey($t, $xx, "modèle xx manquant : {$t}");
            }
        }
        // Textes du téléphone et du Zeus : en anglais, sans collision avec un mot déjà traduit autrement.
        foreach (['Traçage de numéro', 'TRACER LE NUMÉRO', 'TRACER %1', 'Numéro inconnu du réseau', "Pas de liaison avec l'opérateur", 'Journal de traçage',
            'Tracé %1 : position à %2 m près, %3', 'ATAK · Téléphone de l\'IA (traçage)', 'ATAK · Traçage des numéros', 'TRAÇAGE'] as $t) {
            self::assertArrayHasKey($t, $en, $t);
            self::assertArrayHasKey($t, $xx, $t);
        }
        self::assertSame('DRAW', $en['TRACER'], 'TRACER veut déjà dire « dessiner » : le bouton dit TRACER LE NUMÉRO');
        self::assertStringNotContainsString('"TRACER"]', self::read(self::TRACE . 'fn_traceAction.sqf'));
    }

    public function testCalculsPursDuSelfTest(): void
    {
        $src = self::read(self::TRACE . 'fn_traceCalc.sqf');
        $body = substr($src, (int) strpos($src, 'case "selfTest"'));
        preg_match_all('/^\s*\["([^"]+)", (\[.*\]) call _([a-z]+), (.+)\] call _eq;$/m', $body, $m, PREG_SET_ORDER);
        self::assertGreaterThanOrEqual(25, count($m));
        foreach ($m as [, $name, $args, $fn, $want]) {
            if (str_contains($args, ' call ')) {
                continue;
            }
            $a = json_decode($args, true, 512, JSON_THROW_ON_ERROR);
            $w = json_decode($want, true, 512, JSON_THROW_ON_ERROR);
            $got = match ($fn) {
                'norm' => self::norm($a[0]),
                'same' => self::same((string) $a[0], (string) $a[1]),
                'radius' => self::radius(...$a),
                'aged' => self::aged(...$a),
                'st' => self::etat(array_column($a, 1, 0)),
                'cooldown' => max(($a[0] + $a[2]) - $a[1], 0),
                'extract' => self::extract($a[0]),
                default => self::fail("fonction inconnue {$fn}"),
            };
            self::assertEquals($w, $got, $name);
        }
        // Les appels imbriqués (norm dans same) : vérifiés à part.
        self::assertStringContainsString('["same 9 chiffres", [["964750123456"] call _norm, ["750123456"] call _norm] call _same, true] call _eq;', $body);
        self::assertTrue(self::same(self::norm('964750123456'), self::norm('750123456')));
    }

    // Copies PHP des calculs de fn_traceCalc : mêmes règles, pour vérifier les valeurs attendues du selfTest.
    private static function norm(string $q): string
    {
        $n = (string) preg_replace('/[^0-9A-Z]/', '', strtoupper($q));
        if (str_starts_with($n, '00') && strlen($n) > 10) {
            $n = substr($n, 2);
        }
        if (str_starts_with($n, '33') && strlen($n) === 11) {
            $n = '0' . substr($n, 2);
        }

        return $n;
    }

    private static function same(string $a, string $b): bool
    {
        if ($a === '' || $b === '') {
            return false;
        }
        if ($a === $b) {
            return true;
        }

        return ctype_digit($a) && ctype_digit($b) && strlen($a) >= 9 && strlen($b) >= 9 && substr($a, -9) === substr($b, -9);
    }

    private static function radius(float $n, float $d, float $multi, float $single): float
    {
        if ($n <= 0) {
            return -1;
        }
        if ($n >= 3) {
            return $multi;
        }
        if ((int) $n === 2) {
            return $multi * 2;
        }

        return $single * min(max(0.4, $d / 2000), 1.9);
    }

    private static function aged(float $r, float $age, float $grow, float $cap): float
    {
        return min($r + max($age, 0) * max($grow, 0), max($cap, $r));
    }

    /** @param array<string, mixed> $f */
    private static function etat(array $f): string
    {
        return match (true) {
            (bool) ($f['disabled'] ?? false) => 'DISABLED',
            !($f['found'] ?? false) => 'UNKNOWN',
            (bool) ($f['burner'] ?? false) => 'UNKNOWN',
            !($f['present'] ?? true) => 'OFF',
            (bool) ($f['jammed'] ?? false) => 'JAM',
            (bool) ($f['sos'] ?? false) => 'SOS',
            !($f['on'] ?? true) || (bool) ($f['airplane'] ?? false) => 'OFF',
            ($f['antennas'] ?? 0) <= 0 => 'NOCOV',
            default => 'OK',
        };
    }

    /** @return list<string> */
    private static function extract(string $t): array
    {
        preg_match_all('/\+?\(?\d[\d .()\-]{6,22}\d/', $t, $m);
        $out = [];
        $seen = [];
        foreach ($m[0] as $raw) {
            $s = trim($raw);
            $nd = strlen((string) preg_replace('/\D/', '', $s));
            $pieces = array_values(array_filter(preg_split('/[ .\-()]/', $s) ?: [], static fn ($p): bool => $p !== ''));
            $ok = $nd >= 8 && $nd <= 15 && !preg_match('/^\d{4}-\d{2}-\d{2}/', $s)
                && (in_array($s[0], ['+', '(', '0'], true) || count($pieces) >= 3);
            if ($ok && !in_array(self::norm($s), $seen, true)) {
                $seen[] = self::norm($s);
                $out[] = $s;
            }
            if (count($out) >= 6) {
                break;
            }
        }

        return $out;
    }
}
