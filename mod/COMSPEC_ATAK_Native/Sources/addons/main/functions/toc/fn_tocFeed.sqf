/*
    Données des écrans TOC (fn_tocScreen), calculées par le serveur de la mission : tous les joueurs voient le même
    réseau, celui que le serveur connaît, avec les mêmes règles que le BFT des téléphones (fn_localDataRefresh) :
    seuls les porteurs d'un ATAK (joueurs et IA équipées, fn_aiAtak) sont des nœuds, à leur dernier point PLI (fn_pli),
    en direct / ancien / perdu selon l'âge du point, hors ligne si le téléphone ne balise plus (COMSPEC_ATAK_Beacon).
    Publié toutes les 3 s, pour les seuls camps qui ont un écran TOC ou un PC du poste ouvert (fn_tocLaptop), dans la variable publique
      COMSPEC_ATAK_TocFeed_<WEST | EAST | GUER | CIV> = [serverTime, nœuds, flux, état]
        nœud  : [clé, indicatif, [x, y], cap, fraîcheur (0 direct, 1 ancien, 2 perdu, 3 hors ligne), "inf" | "veh" |
                 "armor" | "air" | "boat", IA, groupe, occupants (équipage d'un véhicule regroupé en un nœud)]
        flux  : 30 derniers [heure, sorte, priorité, auteur, grille, texte, [x, y] ou [], serverTime, partie précédente]
                (comptes rendus, alertes, SALUTE, MEDEVAC, BDA, ordres, messages du poste TOC)
        état  : [brouilleurs actifs, relais, joueurs, IA]
    Modes : ["init"] (serveur) | ["tick"] | ["push", [camp, entrée]] | ["events"] -> HashMap camp -> flux (fn_tocPersist)
            ["setEvents", HashMap] (fn_tocPersist : flux de la partie précédente)
*/
params [["_mode", "tick"], ["_a", []]];
if (!isServer) exitWith {};
private _fn = comspec_atak_native_fnc_tocFeed;
switch (_mode) do {
    case "init": {
        if (!isNil "COMSPEC_ATAK_TocEvents") exitWith {};
        COMSPEC_ATAK_TocEvents = createHashMap;
        COMSPEC_ATAK_TocDev = createHashMap;
        COMSPEC_ATAK_TocLast = createHashMap;
        private _now = { [dayTime, "HH:MM"] call BIS_fnc_timeToString };
        // Événements de camp relayés par le serveur (comspec_atak_native_sideEvent les rejoue ici en local).
        ["comspec_atak_native_report", {
            params [["_r", createHashMap], ["_side", ""]];
            if !(_r isEqualType createHashMap) exitWith {};
            if ((_r getOrDefault ["dest", "CAMP"]) isEqualTo "MON GROUPE") exitWith {};
            private _p = _r getOrDefault ["pos", []];
            ["push", [_side, [_r getOrDefault ["time", ""], _r getOrDefault ["label", "C.R."], _r getOrDefault ["prio", ""], _r getOrDefault ["by", ""], _r getOrDefault ["grid", ""],
                (_r getOrDefault ["summary", ""]) select [0, 200], [[], [_p select 0, _p select 1]] select ((count _p) >= 2)]]] call comspec_atak_native_fnc_tocFeed;
        }] call CBA_fnc_addEventHandler;
        ["comspec_atak_native_alert", {
            params [["_kind", ""], ["_who", ""], ["_pos", []], ["_grid", ""], ["_txt", ""], ["_side", ""]];
            if !(_who isEqualType "") then { _who = str _who; };
            ["push", [_side, [[dayTime, "HH:MM"] call BIS_fnc_timeToString, _kind, ["URGENT", "FLASH"] select (_kind in ["TIC", "SOS", "MAN DOWN", "CONTACT"]), _who, _grid,
                _txt select [0, 200], [[], [_pos select 0, _pos select 1]] select (_pos isEqualType [] && {(count _pos) >= 2})]]] call comspec_atak_native_fnc_tocFeed;
        }] call CBA_fnc_addEventHandler;
        ["comspec_atak_native_medevac", {
            params [["_r", createHashMap], ["_side", ""]];
            if !(_r isEqualType createHashMap) exitWith {};
            private _p = _r getOrDefault ["pos", []];
            ["push", [_side, [_r getOrDefault ["time", ""], "MEDEVAC", _r getOrDefault ["prio", "URGENT"], _r getOrDefault ["from", ""], _r getOrDefault ["grid", ""],
                format ["Blessés : urgents %1, prioritaires %2, différés %3", _r getOrDefault ["t1", "0"], _r getOrDefault ["t2", "0"], _r getOrDefault ["t3", "0"]],
                [[], [_p select 0, _p select 1]] select (_p isEqualType [] && {(count _p) >= 2})]]] call comspec_atak_native_fnc_tocFeed;
        }] call CBA_fnc_addEventHandler;
        ["comspec_atak_native_bda", {
            params [["_r", createHashMap], ["_side", ""]];
            if !(_r isEqualType createHashMap) exitWith {};
            private _p = _r getOrDefault ["pos", []];
            ["push", [_side, [_r getOrDefault ["time", [dayTime, "HH:MM"] call BIS_fnc_timeToString], "BDA", "", _r getOrDefault ["by", _r getOrDefault ["from", ""]], _r getOrDefault ["grid", ""],
                (_r getOrDefault ["summary", _r getOrDefault ["text", ""]]) select [0, 200], [[], [_p select 0, _p select 1]] select (_p isEqualType [] && {(count _p) >= 2})]]] call comspec_atak_native_fnc_tocFeed;
        }] call CBA_fnc_addEventHandler;
        ["comspec_atak_native_tocMsg", {
            params [["_cs", "TOC"], ["_body", ""], ["_time", ""], ["_prio", "ROUTINE"], ["_side", ""], "", "", ["_to", ""]];
            // Message à un seul chef (PC du poste) : pas sur les écrans.
            if (_to isNotEqualTo "") exitWith {};
            ["push", [_side, [_time, "TOC", _prio, _cs, "", _body select [0, 200], []]]] call comspec_atak_native_fnc_tocFeed;
        }] call CBA_fnc_addEventHandler;
        // Ordres du camp (PC du poste, chefs) : sur les écrans TOC et dans le flux du PC.
        ["comspec_atak_native_localOrder", {
            params [["_pairs", []], ["_side", ""]];
            private _o = createHashMapFromArray _pairs;
            private _p = _o getOrDefault ["pos", []];
            private _lbl = _o getOrDefault ["typeLabel", ""];
            if (_lbl isEqualTo "") then { _lbl = _o getOrDefault ["type", "ORDRE"]; };
            private _tgt = _o getOrDefault ["target", ""];
            ["push", [_side, [_o getOrDefault ["time", ""], "ORDRE", _o getOrDefault ["priority", ""], _o getOrDefault ["issuer", ""], [[[_p select 0, _p select 1, 0], 6] call comspec_atak_native_fnc_gridRef, ""] select ((count _p) < 2),
                format ["%1 → %2 : %3", _lbl, ["tout le camp", _tgt] select (_tgt isNotEqualTo ""), (_o getOrDefault ["payload", ""]) select [0, 160]], [[], [_p select 0, _p select 1]] select ((count _p) >= 2)]]] call comspec_atak_native_fnc_tocFeed;
        }] call CBA_fnc_addEventHandler;
        [{ ["tick"] call comspec_atak_native_fnc_tocFeed; }, 3] call comspec_atak_native_fnc_pfhSpread;
    };
    case "push": {
        _a params [["_side", ""], ["_e", []]];
        if !(_side in ["WEST", "EAST", "GUER", "CIV"]) exitWith {};
        _e resize 7;
        _e set [7, serverTime];
        _e set [8, false];
        { if (isNil "_x") then { _e set [_forEachIndex, ["", []] select (_forEachIndex isEqualTo 6)]; }; } forEach _e;
        private _l = COMSPEC_ATAK_TocEvents getOrDefault [_side, []];
        _l pushBack _e;
        if ((count _l) > 30) then { _l deleteRange [0, (count _l) - 30]; };
        COMSPEC_ATAK_TocEvents set [_side, _l];
        ["save"] call comspec_atak_native_fnc_tocPersist;
    };
    case "events": { COMSPEC_ATAK_TocEvents };
    case "setEvents": {
        // Flux de la partie précédente : marqué comme tel (grisé sur l'écran), sans position (heure de la partie passée).
        {
            private _side = _x;
            private _old = (_y select { _x isEqualType [] && {(count _x) >= 6} }) apply { private _e = +_x; _e resize 9; _e set [6, []]; _e set [7, -1e9]; _e set [8, true]; _e };
            private _all = _old + (COMSPEC_ATAK_TocEvents getOrDefault [_side, []]);
            COMSPEC_ATAK_TocEvents set [_side, _all select [0 max ((count _all) - 30)]];
        } forEach _a;
    };
    case "tick": {
        private _screens = (missionNamespace getVariable ["COMSPEC_ATAK_TocScreens", []]) select { !isNull _x && {alive _x} };
        // PC du poste ouverts (fn_tocLaptop) : leur camp est publié aussi, même sans écran.
        private _ops = allPlayers select { ((_x getVariable ["COMSPEC_ATAK_TocView", []]) param [0, ""]) isNotEqualTo "" };
        if ((count _screens) isEqualTo 0 && {(count _ops) isEqualTo 0}) exitWith {};
        // Camps à publier : ceux des écrans ; un écran ouvert à tous publie pour chaque camp qui a des joueurs.
        private _sides = _ops apply { (_x getVariable "COMSPEC_ATAK_TocView") select 0 };
        {
            private _s = (_x getVariable ["COMSPEC_ATAK_TocScr", []]) param [1, ""];
            if (_s isEqualTo "") then { { _sides pushBackUnique str side group _x; } forEach allPlayers; } else { _sides pushBackUnique _s; };
        } forEach _screens;
        _sides = (_sides arrayIntersect _sides) select { _x in ["WEST", "EAST", "GUER", "CIV"] };
        private _pli = ["pli"] call comspec_atak_native_fnc_netCond;
        private _rate = (["pliRate"] call comspec_atak_native_fnc_netCond) max 1;
        private _liveAge = [5, _rate * 1.6 + 2] select _pli;
        private _staleAge = [20, _rate * 4 + 10] select _pli;
        private _now = serverTime;
        private _jam = { _x isEqualType [] && {(_x param [4, 1e9]) >= _now} } count (missionNamespace getVariable ["COMSPEC_ATAK_Jammers", []]);
        private _relays = { _x isEqualType objNull && {!isNull _x} && {alive _x} && {_x getVariable ["COMSPEC_AtakRelayOn", true]} } count (missionNamespace getVariable ["COMSPEC_AtakRelays", []]);
        // Porte un ATAK : vérifié au plus toutes les 10 s par unité (inventaire).
        private _has = {
            params ["_u"];
            private _k = netId _u;
            private _c = COMSPEC_ATAK_TocDev getOrDefault [_k, [false, -1e9]];
            if (diag_tickTime > (_c select 1)) then { _c = [[_u] call comspec_atak_native_fnc_hasDevice, diag_tickTime + 8 + random 4]; COMSPEC_ATAK_TocDev set [_k, _c]; };
            _c select 0
        };
        {
            private _side = _x;
            private _nodes = [];
            private _byVeh = createHashMap;
            private _nP = 0; private _nAi = 0;
            {
                private _u = _x;
                if (!alive _u || {(str side group _u) isNotEqualTo _side} || {!([_u] call _has)}) then { continue };
                private _isAi = !isPlayer _u;
                if (_isAi) then { _nAi = _nAi + 1; } else { _nP = _nP + 1; };
                private _key = netId _u;
                private _pos = getPosASL _u; private _dir = getDir (vehicle _u); private _fr = 0;
                private _on = _u getVariable ["COMSPEC_ATAK_Beacon", true];
                private _p = _u getVariable ["COMSPEC_ATAK_Pli", []];
                if (_pli && {_p isEqualType []} && {(count _p) >= 3}) then {
                    _pos = _p select 0; _dir = _p select 1;
                    private _age = (_now - (_p select 2)) max 0;
                    _fr = switch (true) do { case (_age < _liveAge): { 0 }; case (_age < _staleAge): { 1 }; default { 2 }; };
                };
                if (!_on) then {
                    _fr = 3;
                    // Téléphone éteint ou cassé : dernière position connue du serveur.
                    _pos = COMSPEC_ATAK_TocLast getOrDefault [_key, _pos];
                } else { COMSPEC_ATAK_TocLast set [_key, _pos]; };
                private _v = vehicle _u;
                private _kind = switch (true) do {
                    case (_v isEqualTo _u): { "inf" };
                    case (_v isKindOf "Air"): { "air" };
                    case (_v isKindOf "Ship"): { "boat" };
                    case (_v isKindOf "Tank" || {_v isKindOf "Wheeled_APC_F"}): { "armor" };
                    default { "veh" };
                };
                private _cs = [_u, true] call comspec_atak_native_fnc_unitCallsign;
                if (_cs isEqualTo "") then { _cs = [name _u, groupId group _u] select _isAi; };
                // Équipage d'un véhicule : un seul nœud (le meilleur état), occupants comptés.
                if (_v isNotEqualTo _u) then {
                    private _vk = netId _v;
                    private _i = _byVeh getOrDefault [_vk, -1];
                    if (_i >= 0) then {
                        private _n = _nodes select _i;
                        _n set [8, (_n select 8) + 1];
                        if (_fr < (_n select 4)) then { _n set [4, _fr]; _n set [2, [_pos select 0, _pos select 1]]; };
                        if (_u isEqualTo effectiveCommander _v) then { _n set [1, _cs]; _n set [6, _isAi]; };
                        continue
                    };
                    _byVeh set [_vk, count _nodes];
                };
                if ((count _nodes) < 150) then {
                    _nodes pushBack [_key, _cs select [0, 28], [round (_pos select 0), round (_pos select 1)], round _dir, _fr, _kind, _isAi, groupId group _u, 1];
                };
            } forEach allUnits;
            missionNamespace setVariable [format ["COMSPEC_ATAK_TocFeed_%1", _side], [_now, _nodes, +(COMSPEC_ATAK_TocEvents getOrDefault [_side, []]), [_jam, _relays, _nP, _nAi]], true];
        } forEach _sides;
        // Ménage : unités disparues.
        if ((count COMSPEC_ATAK_TocDev) > 600) then { COMSPEC_ATAK_TocDev = createHashMap; COMSPEC_ATAK_TocLast = createHashMap; };
    };
};
true
