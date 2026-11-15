<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Perf (lot 5) : Overwatch connect et Lobby. Menus ACE posés sur plusieurs images, fiche opérateur arrêtée net sans
 * Steam UID, JSON sans boucle par caractère, caches de session, arsenal paresseux, marqueurs en file, Lobby.
 * Sources lues sans dépendre des fins de ligne ni des versions.
 */
final class OverwatchPerfLot5AssetTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../';
    private const CONN = 'mod/UptoDate/Sources/comspec-overwatch-addons/connect/';
    private const LOBBY = 'mod/COMSPEC_Lobby/Sources/addons/main/functions/fn_lobbyOpen.sqf';

    private const TOUCHED = [
        self::CONN . 'XEH_postInit.sqf',
        self::CONN . 'functions/fn_initACE.sqf',
        self::CONN . 'functions/fn_initATAKMenu.sqf',
        self::CONN . 'functions/fn_initChargeAceActions.sqf',
        self::CONN . 'functions/fn_operatorProfileUidGate.sqf',
        self::CONN . 'functions/fn_operatorProfileTick.sqf',
        self::CONN . 'functions/fn_syncOperatorProfile.sqf',
        self::CONN . 'functions/fn_buildOperatorProfile.sqf',
        self::CONN . 'functions/fn_collectItemCatalog.sqf',
        self::CONN . 'functions/fn_collectLoadedMods.sqf',
        self::CONN . 'functions/fn_jsonValue.sqf',
        self::CONN . 'functions/fn_aceLoadoutsRepair.sqf',
        self::CONN . 'functions/fn_arsenalAceStore.sqf',
        self::CONN . 'functions/fn_arsenalInitOverlay.sqf',
        self::CONN . 'functions/fn_arsenalOverlayShow.sqf',
        self::CONN . 'functions/fn_markerEhQueue.sqf',
        self::CONN . 'functions/fn_log.sqf',
        self::CONN . 'functions/fn_logDump.sqf',
        self::LOBBY,
    ];

    /** Fichiers écrits pour ce lot : rien ne doit s'y afficher à l'écran. */
    private const NEW_FILES = [
        self::CONN . 'functions/fn_operatorProfileUidGate.sqf',
        self::CONN . 'functions/fn_markerEhQueue.sqf',
    ];

    private const BANNED = ['systemChat', 'hint', 'hintSilent', 'hintC', 'cutText', 'cutRsc', 'titleText', 'titleRsc',
        'BIS_fnc_showNotification', 'BIS_fnc_dynamicText', 'comspec_overwatch_connect_fnc_showNotification',
        'comspec_overwatch_connect_fnc_announce', 'comspec_atak_native_fnc_notify', 'sideChat', 'globalChat', 'groupChat'];

    private static function read(string $rel): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(self::ROOT . $rel));
    }

    private static function conn(string $file): string
    {
        return self::read(self::CONN . $file);
    }

    /** Code SQF sans commentaires ni chaînes. */
    private static function strip(string $src): string
    {
        $out = '';
        $n = strlen($src);
        for ($i = 0; $i < $n; $i++) {
            $c = $src[$i];
            if ($c === '/' && ($src[$i + 1] ?? '') === '/') {
                while ($i < $n && $src[$i] !== "\n") {
                    $i++;
                }
                $out .= "\n";
                continue;
            }
            if ($c === '/' && ($src[$i + 1] ?? '') === '*') {
                $end = strpos($src, '*/', $i + 2);
                $i = $end === false ? $n : $end + 1;
                continue;
            }
            if ($c === '"' || $c === "'") {
                $i++;
                while ($i < $n) {
                    if ($src[$i] === $c) {
                        if (($src[$i + 1] ?? '') === $c) {
                            $i += 2;
                            continue;
                        }
                        break;
                    }
                    $i++;
                }
                $out .= '""';
                continue;
            }
            $out .= $c;
        }

        return $out;
    }

    private static function pos(string $needle, string $haystack): int
    {
        $p = strpos($haystack, $needle);
        self::assertNotFalse($p, 'absent : ' . $needle);

        return (int) $p;
    }

    public function testDelimitersAreBalanced(): void
    {
        foreach (self::TOUCHED as $rel) {
            $code = self::strip(self::read($rel));
            $depth = ['{' => 0, '[' => 0, '(' => 0];
            $close = ['}' => '{', ']' => '[', ')' => '('];
            foreach (str_split($code) as $ch) {
                if (isset($depth[$ch])) {
                    $depth[$ch]++;
                } elseif (isset($close[$ch])) {
                    $depth[$close[$ch]]--;
                    self::assertGreaterThanOrEqual(0, $depth[$close[$ch]], $rel . ' : ' . $ch . ' sans ouverture');
                }
            }
            self::assertSame(['{' => 0, '[' => 0, '(' => 0], $depth, $rel);
        }
    }

    public function testNothingIsShownOnScreen(): void
    {
        foreach (self::NEW_FILES as $rel) {
            $code = self::strip(self::read($rel));
            foreach (self::BANNED as $b) {
                self::assertSame(0, preg_match('/\b' . preg_quote($b, '/') . '\b/', $code), $rel . ' : ' . $b);
            }
        }
        // Fichiers modifiés : pas plus d'affichage à l'écran qu'avant (aucun dans ceux-ci).
        foreach (['functions/fn_operatorProfileTick.sqf', 'functions/fn_syncOperatorProfile.sqf', 'functions/fn_buildOperatorProfile.sqf',
            'functions/fn_jsonValue.sqf', 'functions/fn_collectItemCatalog.sqf', 'functions/fn_collectLoadedMods.sqf',
            'functions/fn_aceLoadoutsRepair.sqf', 'functions/fn_arsenalAceStore.sqf', 'functions/fn_arsenalInitOverlay.sqf',
            'functions/fn_log.sqf', 'functions/fn_logDump.sqf', 'functions/fn_initATAKMenu.sqf'] as $file) {
            $code = self::strip(self::conn($file));
            foreach (self::BANNED as $b) {
                self::assertSame(0, preg_match('/\b' . preg_quote($b, '/') . '\b/', $code), $file . ' : ' . $b);
            }
        }
        $lobby = self::strip(self::read(self::LOBBY));
        foreach (self::BANNED as $b) {
            self::assertSame(0, preg_match('/\b' . preg_quote($b, '/') . '\b/', $lobby), 'lobby : ' . $b);
        }
    }

    public function testNewFunctionsAreRegistered(): void
    {
        $cfg = self::conn('config.cpp');
        self::assertStringContainsString('class operatorProfileUidGate {};', $cfg);
        self::assertStringContainsString('class markerEhQueue {};', $cfg);
    }

    public function testOperatorProfileStopsCleanlyWithoutSteamUid(): void
    {
        $gate = self::conn('functions/fn_operatorProfileUidGate.sqf');
        // Une ligne par changement d'état : la ligne « absent » n'est écrite que si l'état n'était pas déjà « absent ».
        self::assertSame(1, substr_count($gate, '"Steam UID absent'));
        $warn = self::pos('"Steam UID absent', $gate);
        $guard = self::pos('if (!_known && {!_missing}) then {', $gate);
        $set = self::pos('setVariable ["COMSPEC_OperatorUidMissing", true', $gate);
        self::assertLessThan($set, $guard);
        self::assertLessThan($warn, $set);
        // Reprise automatique, journalisée une fois.
        self::assertStringContainsString('if (_known && {_missing}) then {', $gate);
        self::assertStringContainsString('setVariable ["COMSPEC_OperatorUidMissing", false', $gate);
        self::assertStringContainsString('getPlayerUID player', $gate);
        self::assertStringContainsString('comspec_overwatch_saved_steam_uid', $gate);
        // Aucune construction de fiche ni script dans la porte.
        $gateCode = self::strip($gate);
        self::assertStringNotContainsString('buildOperatorProfile', $gateCode);
        self::assertStringNotContainsString('spawn', $gateCode);
        self::assertStringNotContainsString('callExtension', $gateCode);

        // Contrôle périodique : la porte passe avant tout script (spawn) et toute construction.
        $tick = self::conn('functions/fn_operatorProfileTick.sqf');
        $g = self::pos('if !([] call comspec_overwatch_connect_fnc_operatorProfileUidGate) exitWith { false };', $tick);
        self::assertLessThan(self::pos('spawn {', $tick), $g);
        self::assertLessThan(self::pos('comspec_overwatch_connect_fnc_buildOperatorProfile', $tick), $g);

        // Envoi : la porte passe avant la construction, et l'envoi ne journalise plus lui-même l'absence d'UID.
        $sync = self::conn('functions/fn_syncOperatorProfile.sqf');
        $g = self::pos('if !([] call comspec_overwatch_connect_fnc_operatorProfileUidGate) exitWith { false };', $sync);
        self::assertLessThan(self::pos('call comspec_overwatch_connect_fnc_buildOperatorProfile', $sync), $g);
        self::assertStringNotContainsString('"Steam UID absent', $sync);
        self::assertStringContainsString('if ((count _steam) < 15) exitWith { false };', $sync);
        // Le frein partagé des comptes non liés (fn_linkGate) reste en place, avant la porte.
        self::assertLessThan($g, self::pos('["blocks", "OperatorSync"] call comspec_overwatch_connect_fnc_linkGate', $sync));
    }

    public function testOperatorProfileIsBuiltOnceAndEncodedNatively(): void
    {
        $json = self::conn('functions/fn_jsonValue.sqf');
        // Chemin rapide natif avant la boucle par caractère (même test que fn_hashMapToJson).
        $fast = self::pos('if (_s regexMatch "[^\x00-\x1f""\\\\]*") exitWith { _dq + _s + _dq };', $json);
        self::assertLessThan(self::pos('forEach (toArray _s)', $json), $fast);
        self::assertStringContainsString('if (_s regexMatch "[^\x00-\x1f""\\\\]*") exitWith { _dq + _s + _dq };', self::conn('functions/fn_hashMapToJson.sqf'));

        // Fiche légère du contrôle réutilisée par l'envoi.
        $tick = self::conn('functions/fn_operatorProfileTick.sqf');
        self::assertStringContainsString('["sync", _reason, false, _payload] call comspec_overwatch_connect_fnc_syncOperatorProfile', $tick);
        $sync = self::conn('functions/fn_syncOperatorProfile.sqf');
        self::assertStringContainsString('[player, _event, _reason, false, _base] call comspec_overwatch_connect_fnc_buildOperatorProfile', $sync);
        $build = self::conn('functions/fn_buildOperatorProfile.sqf');
        self::assertStringContainsString('if (!_light && {"fingerprint" in _base} && {"equipment" in _base}) then {', $build);
        self::assertSame(1, substr_count($build, 'call comspec_overwatch_connect_fnc_collectOperatorLoadout'));

        // Liste des mods et catalogue d'objets : gardés pour la session de jeu.
        $mods = self::conn('functions/fn_collectLoadedMods.sqf');
        self::assertLessThan(self::pos('forEach getLoadedModsInfo', $mods), self::pos('uiNamespace getVariable "COMSPEC_OpLoadedMods"', $mods));
        self::assertStringContainsString('uiNamespace setVariable ["COMSPEC_OpLoadedMods", _out];', $mods);
        $cat = self::conn('functions/fn_collectItemCatalog.sqf');
        self::assertLessThan(self::pos('configSourceMod _cfg', $cat), self::pos('if (!isNil "_hit") exitWith { _out pushBack (+_hit); };', $cat));
        self::assertStringContainsString('_memo set [_memoKey, _entry];', $cat);
        // Mesure du travail de script seul, au journal.
        self::assertStringContainsString('[PERF] Fiche opérateur : JSON', $sync);
    }

    public function testAceMenusAreInstalledIncrementally(): void
    {
        $ace = self::conn('functions/fn_initACE.sqf');
        $code = self::strip($ace);
        // Les actions sont mises en file, puis posées par une boucle à budget : un seul appel à l'installateur.
        self::assertGreaterThanOrEqual(5, substr_count($code, '_queue pushBack ['));
        self::assertSame(1, substr_count($code, 'call comspec_overwatch_connect_fnc_aceAddSelfAction'));
        self::assertSame(1, substr_count($code, 'call ace_interact_menu_fnc_addActionToClass'));
        self::assertStringContainsString('if ((diag_tickTime - _t0) > 0.002) exitWith {};', $ace);
        self::assertStringContainsString('[_fnc_drain, 0, _job] call CBA_fnc_addPerFrameHandler;', $ace);
        self::assertStringContainsString('[_handle] call CBA_fnc_removePerFrameHandler;', $ace);
        // L'installateur n'est appelé que dans la boucle, après la mise en file.
        $drain = self::pos('private _fnc_drain = {', $ace);
        self::assertLessThan(self::pos('call comspec_overwatch_connect_fnc_aceAddSelfAction', $ace), $drain);
        // Rien n'est refait si l'arbre de cette mission est déjà posé (réapparition, second appel).
        $early = self::pos('if ((missionNamespace getVariable ["COMSPEC_ACEClassTreeVer", 0]) isEqualTo _menuVer) exitWith {', $ace);
        self::assertLessThan(self::pos('private _queue = [];', $ace), $early);
        // Les anciens identifiants ne sont retirés que si un ancien arbre existe dans la mission.
        $cond = self::pos('if ((missionNamespace getVariable ["COMSPEC_ACEMenuStructureVer", 0]) isNotEqualTo 0) then {', $ace);
        $legacy = self::pos('private _legacyFlat = [', $ace);
        self::assertLessThan($legacy, $cond);
        self::assertLessThan(self::pos('missionNamespace setVariable ["COMSPEC_ACESelfActions", [], false];', $ace), $legacy);
        // Fin de pose : drapeau levé, dossier des charges, version de l'arbre, ligne PERF.
        $done = self::pos('missionNamespace setVariable ["COMSPEC_ACEMenuBuilding", false, false];', $ace);
        self::assertLessThan($done, self::pos('missionNamespace setVariable ["COMSPEC_ACEMenuBuilding", true, false];', $ace));
        self::assertLessThan(self::pos('[] call comspec_overwatch_connect_fnc_initChargeAceActions;', $ace), $done);
        self::assertLessThan(self::pos('missionNamespace setVariable ["COMSPEC_ACEClassTreeVer", _menuVer, false];', $ace), $done);
        self::assertStringContainsString('[PERF] Menus ACE (installation)', $ace);
        self::assertStringNotContainsString('(arbre v5)', $ace);
        // Les menus qui se posent sous COMSPEC_Main attendent la fin (même ordre d'entrées qu'avant).
        self::assertStringContainsString('COMSPEC_ACEMenuBuilding', self::conn('functions/fn_initATAKMenu.sqf'));
        self::assertStringContainsString('COMSPEC_ACEMenuBuilding', self::conn('functions/fn_initChargeAceActions.sqf'));
    }

    public function testArsenalWorkIsLazyAndTimed(): void
    {
        $repair = self::conn('functions/fn_aceLoadoutsRepair.sqf');
        $skip = self::pos('if (!isNil "_last" && {_last isEqualTo (call _fnShape)}) exitWith { false };', $repair);
        self::assertLessThan(self::pos('(["repair"] call comspec_overwatch_connect_fnc_arsenalAceStore)', $repair), $skip);
        self::assertStringContainsString('uiNamespace setVariable ["COMSPEC_AceLoadoutsShape", call _fnShape];', $repair);

        $store = self::conn('functions/fn_arsenalAceStore.sqf');
        // Empreinte de contenu calculée seulement pour les doublons de nom.
        self::assertStringContainsString('if !(_k in _seen) then { _seen set [_k, [_x]]; continue };', $store);
        self::assertStringContainsString('if ((count _first) < 2) then { _first pushBack ([_first select 0] call _fnSig); };', $store);

        $init = self::conn('functions/fn_arsenalInitOverlay.sqf');
        self::assertStringContainsString('[PERF] Arsenal : ouverture COMSPEC', $init);

        $show = self::conn('functions/fn_arsenalOverlayShow.sqf');
        // Seul le bouton à l'ouverture ; le panneau au premier clic, sans recréer le bouton.
        $lazy = self::pos('if (!_panel) exitWith {', $show);
        self::assertLessThan(self::pos('ctrlCreate ["RscControlsGroupNoScrollbars", 884401]', $show), $lazy);
        self::assertLessThan($lazy, self::pos('ctrlCreate ["COMSPEC_RscButtonAtakPrimary", 884400]', $show));
        self::assertStringContainsString('[_d, true] call comspec_overwatch_connect_fnc_arsenalOverlayShow;', $show);
        self::assertStringContainsString('if (isNull _tog) then {', $show);
    }

    public function testMarkerHandlersOnlyQueue(): void
    {
        $post = self::conn('XEH_postInit.sqf');
        $from = self::pos('if (isNil "COMSPEC_MapMarkerEHsEarly") then {', $post);
        $to = self::pos('if (isNil "COMSPEC_MapClosedEh") then {', $post);
        $block = self::strip(substr($post, $from, $to - $from));
        self::assertSame(3, substr_count($block, 'call comspec_overwatch_connect_fnc_markerEhQueue'));
        self::assertStringNotContainsString('comspec_overwatch_connect_fnc_syncMapMarker', $block);
        self::assertStringNotContainsString('CBA_fnc_waitAndExecute', $block);

        $queue = self::conn('functions/fn_markerEhQueue.sqf');
        self::assertStringContainsString('if ((diag_tickTime - _t0) > 0.0015) exitWith {};', $queue);
        self::assertStringContainsString('[_handle] call CBA_fnc_removePerFrameHandler;', $queue);
        self::assertStringContainsString('diag_tickTime + 0.4', $queue);
        self::assertSame(2, substr_count(self::strip($queue), 'call comspec_overwatch_connect_fnc_syncMapMarker'));
    }

    public function testLogBufferAndBootDump(): void
    {
        $log = self::conn('functions/fn_log.sqf');
        self::assertStringContainsString('_buf deleteRange [0, (count _buf) - 200];', $log);
        $dump = self::conn('functions/fn_logDump.sqf');
        $spawn = self::pos('if (_reason isEqualTo "boot" && {!canSuspend}) exitWith {', $dump);
        self::assertLessThan(self::pos('COMSPEC_LogDumpBootAt', $dump), $spawn);
    }

    public function testLobbySyncAndPortrait(): void
    {
        $lobby = self::read(self::LOBBY);
        $code = self::strip($lobby);
        // Un seul passage de synchronisation à la fois.
        self::assertStringContainsString('if (uiNamespace getVariable ["COMSPEC_LOBBY_SyncBusy", false]) exitWith {};', $lobby);
        self::assertStringContainsString('uiNamespace setVariable ["COMSPEC_LOBBY_SyncBusy", false];', $lobby);
        // Portrait : morceaux assemblés une fois, image gardée entre deux ouvertures de l'écran.
        self::assertStringNotContainsString('_b64 = _b64 +', $code);
        self::assertStringContainsString('private _b64 = _parts joinString "";', $lobby);
        self::assertStringContainsString('uiNamespace getVariable "COMSPEC_LOBBY_ImgKeep"', $lobby);
        self::assertStringContainsString('exitWith { _same = true; };', $lobby);
        // Appels directs à COMSPEC Link chronométrés au journal.
        self::assertStringContainsString('[COMSPEC LOBBY][PERF] DLL %1 : %2 ms (direct)', $lobby);
    }
}
