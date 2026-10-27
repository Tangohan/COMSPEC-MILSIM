/*
    Flotte de drones du pilote : jusqu'à 4 drones appairés au même téléphone. Un seul est « piloté » à la fois
    (COMSPEC_ATAK_Drone : caméra, onglets VOL, TÂCHES, CIBLES) ; les autres (missionNamespace COMSPEC_ATAK_DroneFleet,
    drones en arrière-plan) continuent seuls leur ordre ou leur rôle. Appairer un autre drone garde le premier dans la flotte.
      ["list"]                    → [drone piloté, drones en arrière-plan...] (vivants) ;
      ["keep", drone]             range un drone en arrière-plan (point de décollage gardé sur le drone, COMSPEC_DroneHomePos) ;
      ["switch", drone]           pilote ce drone, celui d'avant passe en arrière-plan ;
      ["promote"]                 drone piloté perdu ou désappairé : le premier de la flotte prend sa place ;
      ["drop", drone]             libère un drone en arrière-plan (plus appairé au téléphone) ;
      ["send", [drone, ordre, arguments]]   ordre de vol à un drone de la flotte (zones interdites respectées) ;
      ["role", [drone, rôle]]     rôle tenu seul par le drone (variable publique COMSPEC_DroneRole) :
          ""        aucun (le drone garde son dernier ordre) ;
          "ME"      me suivre (rang dans la flotte : décalé sur le côté pour ne pas se gêner) ;
          "CQB"     me suivre en CQB (vol bas, 3 m, 10 km/h) ;
          "POINT"   surveiller le point de l'onglet TÂCHES (orbite caméra sur le point, rayon décalé par rang) ;
          "PLAYER"  surveiller le joueur choisi dans l'onglet VOL (escorte, 30 m derrière) ;
          "GUARD"   garde rapprochée (IA de proximité, réglage serveur « Repérage automatique par drone », désactivé
                    par défaut ; sinon refusé) : suit le pilote, repère seul les ennemis à moins de 200 m de lui
                    (en vue directe du drone), prévient (notification, vibreur, journal, croix sur la carte) et va observer
                    le plus proche s'il passe sous 120 m, puis revient au pilote quand il n'est plus vu depuis 20 s ;
          "HUNT"    recherche autour du pilote (secteur décalé par rang) ;
      ["swarm", ordre]            essaim, tous les drones à la fois : "ME", "CQB", "GUARD", "HUNT", "POINT" (rôles), "HOVER",
                                  "RTH" (chacun à son point de décollage), "LAND" ;
      ["tick"]                    chaque seconde (PFH du drone piloté) : rôles, et pour les drones en arrière-plan liaison
                                  perdue ou batterie critique → retour au point de décollage, zone interdite → sortie ;
      ["draw", carte]             drones en arrière-plan et contacts de la garde sur la carte (fn_droneDraw) ;
      ["link", drone]             → [barres 0-4, distance, en liaison].
*/
params [["_act", "list"], ["_arg", []]];
private _active = missionNamespace getVariable ["COMSPEC_ATAK_Drone", objNull];
private _bg = (missionNamespace getVariable ["COMSPEC_ATAK_DroneFleet", []]) select { !isNull _x };
private _say = { params ["_lvl", "_txt"]; [_lvl, _txt, 4, 40] call comspec_atak_native_fnc_notify; };
private _log = { params ["_t", ["_c", ""]]; ["log", _t, _c] call comspec_atak_native_fnc_droneAction; };
private _name = { params ["_x"]; [_x, [], "NAME"] call comspec_atak_native_fnc_droneOsd };
private _all = { (([_active] select { !isNull _x && {alive _x} }) + (_bg select { alive _x })) };
private _render = { [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "DRONE") then { ["DRONE"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame; };
private _linkOf = {
    params ["_x"];
    if (isNull _x || {!alive _x}) exitWith { [0, 0, false] };
    if (_x isEqualTo _active) exitWith { (["link"] call comspec_atak_native_fnc_droneAction) select [0, 3] };
    private _range = call comspec_atak_native_fnc_droneRange;
    if (terrainIntersectASL [eyePos player, getPosASL _x]) then { _range = _range * 0.3; };
    if !([player] call comspec_atak_native_fnc_hasDevice) then { _range = 0; };
    if ((missionNamespace getVariable ["COMSPEC_ATAK_Battery", 100]) <= 0) then { _range = 0; };
    private _dist = player distance _x;
    private _q = ((1 - (_dist / (_range max 1)) ^ 2) max 0) min 1;
    [[0, ceil (4 * _q) max 1] select (_dist < _range), round _dist, _dist < _range]
};
// Ordre de vol brut à un drone : le drone piloté passe par fn_droneAction (journal, surveillance, zones interdites).
private _sendTo = {
    params ["_x", "_cmd", ["_args", []]];
    if (isNull _x || {!alive _x}) exitWith { false };
    if (_x isEqualTo _active) exitWith {
        missionNamespace setVariable ["COMSPEC_ATAK_DroneRoleSending", true];
        private _ok = ["sendRaw", [_cmd, _args]] call comspec_atak_native_fnc_droneAction;
        missionNamespace setVariable ["COMSPEC_ATAK_DroneRoleSending", false];
        _ok
    };
    if !(([_x] call _linkOf) select 2) exitWith { ["WARNING", format ["%1 hors liaison : ordre non transmis", [_x] call _name]] call _say; false };
    private _goal = switch (_cmd) do { case "goto"; case "loiter"; case "observe": { _args param [0, []] }; default { [] }; };
    if ((count _goal) >= 2 && {(count (["inside", _goal] call comspec_atak_native_fnc_noFly)) > 0}) exitWith {
        ["WARNING", format ["%1 : le but est dans une zone interdite, ordre refusé", [_x] call _name]] call _say;
        false
    };
    if ((_x getVariable ["COMSPEC_DroneLock", ""]) isNotEqualTo "") then { _x setVariable ["COMSPEC_DroneLock", "", true]; };
    ["comspec_atak_native_droneCmd", [_x, _cmd, _args], _x] call CBA_fnc_targetEvent;
    true
};
// Rang dans la flotte (0 = drone piloté) : décalage du suivi (fn_droneCmd, variable COMSPEC_DroneSlot) et des orbites.
private _slotOf = { params ["_x"]; ((call _all) find _x) max 0 };
private _roleName = { params ["_r"]; (createHashMapFromArray [["", "aucun"], ["ME", "me suivre"], ["CQB", "me suivre en CQB"], ["POINT", "surveiller le point"], ["PLAYER", "surveiller le joueur"], ["GUARD", "garde rapprochée"], ["HUNT", "recherche"]]) getOrDefault [_r, _r] };
private _gridPos = {
    private _g = ((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["droneTaskGridVal", ""]) regexReplace ["[^0-9]", ""];
    if ((count _g) < 6 || {((count _g) mod 2) isEqualTo 1}) exitWith { [] };
    (([_g] call BIS_fnc_gridToPos)) params [["_p", []], ["_sz", [0, 0]]];
    if ((count _p) < 2) exitWith { [] };
    private _c = [(_p select 0) + (_sz select 0) / 2, (_p select 1) + (_sz select 1) / 2];
    [_c select 0, _c select 1, (getTerrainHeightASL _c) + 1]
};

switch (_act) do {
    case "list": { call _all };
    case "link": { [_arg] call _linkOf };
    case "keep": {
        if (isNull _arg || {!alive _arg} || {_arg in _bg}) exitWith {};
        if (_arg isEqualTo _active) then { _arg setVariable ["COMSPEC_DroneHomePos", missionNamespace getVariable ["COMSPEC_ATAK_DroneHome", getPosASL _arg]]; };
        _bg pushBack _arg;
        missionNamespace setVariable ["COMSPEC_ATAK_DroneFleet", _bg];
    };
    case "switch": {
        if (isNull _arg || {!alive _arg} || {_arg isEqualTo _active}) exitWith {};
        if !(_arg in _bg) exitWith {};
        ["fleetAdopt", _arg] call comspec_atak_native_fnc_droneAction;
        [format ["Flotte : %1 piloté, les autres continuent seuls", [_arg] call _name], "#4da3ff"] call _log;
        call _render;
    };
    case "promote": {
        private _next = _bg select { alive _x };
        missionNamespace setVariable ["COMSPEC_ATAK_DroneFleet", _next];
        if ((count _next) isEqualTo 0) exitWith {};
        ["fleetAdopt", _next select 0] call comspec_atak_native_fnc_droneAction;
        [format ["Flotte : %1 devient le drone piloté", [_next select 0] call _name], "#4da3ff"] call _log;
    };
    case "drop": {
        if (isNull _arg) exitWith {};
        if (_arg isEqualTo _active) exitWith { ["unpair"] call comspec_atak_native_fnc_droneAction; };
        _arg setVariable ["COMSPEC_DroneOwner", "", true];
        _arg setVariable ["COMSPEC_DroneLock", nil, true];
        _arg setVariable ["COMSPEC_DroneRole", nil, true];
        // Rendu propre au terminal UAV, comme au désappairage (fn_droneCmd "release").
        if (alive _arg) then { ["comspec_atak_native_droneCmd", [_arg, "release", []], _arg] call CBA_fnc_targetEvent; };
        missionNamespace setVariable ["COMSPEC_ATAK_DroneFleet", _bg - [_arg]];
        [format ["Flotte : %1 libéré", [_arg] call _name]] call _log;
        call _render;
    };
    case "send": { _arg call _sendTo };
    case "role": {
        _arg params [["_x", objNull], ["_r", ""]];
        if (isNull _x || {!alive _x}) exitWith { false };
        private _slot = [_x] call _slotOf;
        _x setVariable ["COMSPEC_DroneSlot", _slot, true];
        _x setVariable ["COMSPEC_DroneGuard", nil];
        private _ok = switch (_r) do {
            case "ME": {
                if (_x getVariable ["COMSPEC_DroneCqb", false]) then { [_x, "cqb", [false, 40, 40]] call _sendTo; };
                [_x, "follow", [player]] call _sendTo
            };
            case "CQB": {
                if !(_x getVariable ["COMSPEC_DroneCqb", false]) then { [_x, "cqb", [true, 3, 10]] call _sendTo; };
                [_x, "follow", [player]] call _sendTo
            };
            case "POINT": {
                private _p = call _gridPos;
                if (_p isEqualTo []) exitWith { ["WARNING", "Choisissez d'abord le point dans l'onglet TÂCHES (grille ou carte)"] call _say; false };
                [_x, "observe", [_p, ((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["droneTaskRad", 150]) + 40 * _slot]] call _sendTo
            };
            case "PLAYER": {
                private _id = (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["droneFollowUid", ""];
                private _i = allPlayers findIf { getPlayerUID _x isEqualTo _id };
                private _t = if (_i >= 0) then { allPlayers select _i } else { [objNull, objectFromNetId _id] select (_id isNotEqualTo "") };
                if (isNull _t || {!alive _t}) exitWith { ["WARNING", "Choisissez d'abord le joueur dans l'onglet VOL (Suivre un joueur)"] call _say; false };
                [_x, "escort", [_t]] call _sendTo
            };
            case "GUARD": {
                if !(missionNamespace getVariable ["comspec_atak_native_drone_autospot", false]) exitWith { ["WARNING", "Garde rapprochée désactivée sur ce serveur (repérage automatique par drone)"] call _say; false };
                if (_x getVariable ["COMSPEC_DroneCqb", false]) then { [_x, "cqb", [false, 40, 40]] call _sendTo; };
                _x setVariable ["COMSPEC_DroneGuard", ["FOLLOW", objNull, 0, 0, createHashMap]];
                [_x, "follow", [player]] call _sendTo
            };
            case "HUNT": {
                private _rad = (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["droneHuntRad", 250];
                private _c = (getPosASL player) vectorAdd ([[0, 0, 0], [_rad * 0.6 * sin (90 * _slot), _rad * 0.6 * cos (90 * _slot), 0]] select (_slot > 0));
                [_x, "hunt", [_rad, player, _c]] call _sendTo
            };
            default { true };
        };
        if (_ok isEqualTo true) then {
            _x setVariable ["COMSPEC_DroneRole", _r, true];
            if (_r isNotEqualTo "") then { [format ["Ordre : %1, rôle %2", [_x] call _name, [_r] call _roleName]] call _log; };
        };
        call _render;
        _ok
    };
    case "swarm": {
        private _fleet = call _all;
        if ((count _fleet) isEqualTo 0) exitWith { ["WARNING", "Aucun drone appairé"] call _say; };
        if (_arg in ["ME", "CQB", "GUARD", "HUNT", "POINT"]) then {
            { ["role", [_x, _arg]] call comspec_atak_native_fnc_droneFleet; } forEach _fleet;
        } else {
            {
                _x setVariable ["COMSPEC_DroneRole", "", true];
                switch (_arg) do {
                    case "HOVER": { [_x, "hover"] call _sendTo; };
                    case "LAND": { [_x, "land"] call _sendTo; };
                    case "RTH": { [_x, "rth", [[_x getVariable ["COMSPEC_DroneHomePos", getPosASL player], missionNamespace getVariable ["COMSPEC_ATAK_DroneHome", getPosASL player]] select (_x isEqualTo _active)]] call _sendTo; };
                };
            } forEach _fleet;
        };
        private _lbl = (createHashMapFromArray [["ME", "me suivre"], ["CQB", "me suivre en CQB"], ["GUARD", "garde rapprochée"], ["HUNT", "recherche"], ["POINT", "surveiller le point"], ["HOVER", "stationnaire"], ["RTH", "retour au point de décollage"], ["LAND", "atterrissage"]]) getOrDefault [_arg, _arg];
        [format ["Ordre à l'essaim (%1 drones) : %2", count _fleet, _lbl], "#4da3ff"] call _log;
        ["INFO", format ["Essaim : %1", toUpper _lbl], 2, 20] call comspec_atak_native_fnc_notify;
        call _render;
    };
    case "tick": {
        private _mySide = side group player;
        // Drones en arrière-plan : perdus, liaison, batterie, zones interdites.
        {
            private _x0 = _x;
            if (!alive _x0) then {
                [format ["Flotte : %1 perdu", [_x0] call _name], "#e5483a"] call _log;
                ["WARNING", format ["Drone perdu : %1", [_x0] call _name]] call _say;
                missionNamespace setVariable ["COMSPEC_ATAK_DroneFleet", (missionNamespace getVariable ["COMSPEC_ATAK_DroneFleet", []]) - [_x0]];
                continue;
            };
            private _ok = ([_x0] call _linkOf) select 2;
            private _was = _x0 getVariable ["COMSPEC_DroneBgLinked", true];
            _x0 setVariable ["COMSPEC_DroneBgLinked", _ok];
            private _m = _x0 getVariable ["COMSPEC_DroneMode", "HOVER"];
            private _air = ((getPosATL _x0) select 2) > 2;
            private _home = _x0 getVariable ["COMSPEC_DroneHomePos", getPosASL player];
            if (_was && {!_ok}) then {
                [format ["Flotte : %1 hors liaison, retour au point de décollage", [_x0] call _name], "#e5483a"] call _log;
                if (_air && {!(_m in ["STRIKE", "RTH", "LAND", "STANDBY"])}) then {
                    _x0 setVariable ["COMSPEC_DroneRole", "", true];
                    ["comspec_atak_native_droneCmd", [_x0, "rth", [_home]], _x0] call CBA_fnc_targetEvent;
                };
            };
            if (!_was && {_ok}) then { [format ["Flotte : %1 de nouveau en liaison", [_x0] call _name], "#5cc76b"] call _log; };
            if ((fuel _x0) <= 0.1 && {_air} && {!(_x0 getVariable ["COMSPEC_DroneBgLow", false])} && {!(_m in ["STRIKE", "RTH", "LAND"])}) then {
                _x0 setVariable ["COMSPEC_DroneBgLow", true];
                _x0 setVariable ["COMSPEC_DroneRole", "", true];
                ["comspec_atak_native_droneCmd", [_x0, "rth", [_home]], _x0] call CBA_fnc_targetEvent;
                ["WARNING", format ["%1 : batterie critique, retour au point de décollage", [_x0] call _name]] call _say;
                [format ["Flotte : %1 batterie critique (%2 %%), retour automatique", [_x0] call _name, round (100 * fuel _x0)], "#e5483a"] call _log;
            };
            private _z = ["inside", getPosASL _x0] call comspec_atak_native_fnc_noFly;
            private _zid = _z param [0, ""];
            if (_zid isNotEqualTo (_x0 getVariable ["COMSPEC_DroneBgZone", ""])) then {
                _x0 setVariable ["COMSPEC_DroneBgZone", _zid];
                if (_zid isNotEqualTo "" && {_ok} && {_air} && {!(_m in ["STRIKE", "LAND", "RTH"])}) then {
                    private _out = ["exit", getPosASL _x0] call comspec_atak_native_fnc_noFly;
                    ["comspec_atak_native_droneCmd", [_x0, "goto", [[_out select 0, _out select 1, 0]]], _x0] call CBA_fnc_targetEvent;
                    [format ["Flotte : %1 en zone interdite, sortie vers %2", [_x0] call _name, [_out, 8] call comspec_atak_native_fnc_gridRef], "#e5483a"] call _log;
                };
            };
        } forEach _bg;
        // Rang de chaque drone (suivi décalé sur le côté).
        { if ((_x getVariable ["COMSPEC_DroneSlot", -1]) isNotEqualTo _forEachIndex) then { _x setVariable ["COMSPEC_DroneSlot", _forEachIndex, true]; }; } forEach (call _all);
        // Garde rapprochée (IA de proximité), toutes les 2 s.
        if ((round time) mod 2 isEqualTo 0 && {missionNamespace getVariable ["comspec_atak_native_drone_autospot", false]}) then {
            {
                private _dr = _x;
                private _g = _dr getVariable ["COMSPEC_DroneGuard", []];
                if ((_dr getVariable ["COMSPEC_DroneRole", ""]) isNotEqualTo "GUARD" || {(count _g) < 5}) then { continue; };
                if !(([_dr] call _linkOf) select 2) then { continue; };
                _g params ["_phase", "_tgt", "_seen", "_sentAt", "_known"];
                private _eye = _dr modelToWorldVisualWorld [0, 0, -0.5];
                private _cands = (player nearEntities [["CAManBase", "LandVehicle"], 200]) select {
                    alive _x && {[_mySide, side group _x] call BIS_fnc_sideIsEnemy} && {(side group _x) isNotEqualTo civilian}
                    && {(_dr distance _x) < 400} && {([_dr, "VIEW", _x] checkVisibility [_eye, aimPos _x]) > 0.3}
                };
                // Contacts nouveaux : alerte (une fois par contact et par minute).
                {
                    private _k = hashValue _x;
                    if ((time - (_known getOrDefault [_k, -1e9])) > 60) then {
                        private _dist = round (player distance2D _x);
                        private _brg = round (player getDir _x);
                        ["WARNING", format ["%1 : ennemi à %2 m, cap %3", [_dr] call _name, _dist, _brg]] call _say;
                        [] call comspec_atak_native_fnc_vibrate;
                        [format ["Garde (%1) : ennemi repéré, %2 m au cap %3, en %4", [_dr] call _name, _dist, _brg, [getPosASL _x, 8] call comspec_atak_native_fnc_gridRef], "#e5483a"] call _log;
                    };
                    _known set [_k, time];
                    private _c = missionNamespace getVariable ["COMSPEC_ATAK_GuardContacts", createHashMap];
                    _c set [_k, [getPosASL _x, time]];
                    missionNamespace setVariable ["COMSPEC_ATAK_GuardContacts", _c];
                } forEach _cands;
                // Le plus proche du pilote, sous 120 m : le drone va l'observer.
                private _near = (_cands select { (player distance2D _x) < 120 }) apply { [player distance2D _x, _x] };
                _near sort true;
                if (_tgt in _cands) then { _seen = time; };
                switch (_phase) do {
                    case "FOLLOW": {
                        if ((count _near) > 0) then {
                            _tgt = (_near select 0) select 1;
                            _seen = time; _sentAt = time; _phase = "WATCH";
                            [_dr, "observe", [getPosASL _tgt, 40]] call _sendTo;
                            [format ["Garde (%1) : observation du contact le plus proche", [_dr] call _name], "#f2ab33"] call _log;
                        };
                    };
                    case "WATCH": {
                        if (isNull _tgt || {!alive _tgt} || {(time - _seen) > 20}) then {
                            _phase = "FOLLOW"; _tgt = objNull;
                            [_dr, "follow", [player]] call _sendTo;
                            [format ["Garde (%1) : contact perdu, retour au pilote", [_dr] call _name]] call _log;
                        } else {
                            private _task = _dr getVariable ["COMSPEC_DroneTask", []];
                            if ((time - _sentAt) > 5 && {((_task param [1, getPosASL _tgt]) distance2D _tgt) > 30}) then {
                                _sentAt = time;
                                [_dr, "observe", [getPosASL _tgt, 40]] call _sendTo;
                            };
                        };
                    };
                };
                // L'ordre a pu effacer le rôle (envoi refusé, hors liaison) : on ne garde l'état que s'il tient toujours.
                if ((_dr getVariable ["COMSPEC_DroneRole", ""]) isEqualTo "GUARD") then { _dr setVariable ["COMSPEC_DroneGuard", [_phase, _tgt, _seen, _sentAt, _known]]; };
            } forEach (call _all);
        };
    };
    case "draw": {
        private _map = _arg;
        if (isNull _map) exitWith {};
        {
            // Liaison relevée par le suivi (une fois par seconde) : pas de calcul de relief à chaque image de la carte.
            private _ok = _x getVariable ["COMSPEC_DroneBgLinked", true];
            private _p = getPosASL _x;
            private _c = [[0.45, 0.75, 1, 1], [0.6, 0.6, 0.6, 0.8]] select !_ok;
            (_x getVariable ["COMSPEC_DroneTask", []]) params [["_k", ""], ["_tp", []], ["_tr", 0]];
            if (_k in ["LOITER", "OBSERVE"] && {(count _tp) >= 2}) then { _map drawEllipse [_tp, _tr, _tr, 0, [_c select 0, _c select 1, _c select 2, 0.5], ""]; };
            private _f = _x getVariable ["COMSPEC_DroneFollow", objNull];
            if ((_x getVariable ["COMSPEC_DroneMode", ""]) in ["FOLLOW", "ESCORT"] && {!isNull _f}) then { _map drawLine [_p, getPosASL _f, [_c select 0, _c select 1, _c select 2, 0.4]]; };
            private _r = _x getVariable ["COMSPEC_DroneRole", ""];
            _map drawIcon [[_x, [], "ICON"] call comspec_atak_native_fnc_droneOsd, _c, _p, 22, 22, getDir _x,
                format [" %1 %2 m%3%4", [_x] call _name, round ((getPosATL _x) select 2), ["", format [" · %1", toUpper ([_r] call _roleName)]] select (_r isNotEqualTo ""), ["", (" (hors liaison)") call comspec_atak_native_fnc_tr] select !_ok],
                1, 0.028, (COMSPEC_ATAK_FontSet select 1), "right"];
        } forEach (_bg select { alive _x });
        // Contacts de la garde rapprochée : croix rouge à la dernière position vue, une minute.
        private _gc = missionNamespace getVariable ["COMSPEC_ATAK_GuardContacts", createHashMap];
        {
            _y params ["_p", "_t"];
            if ((time - _t) > 60) then { _gc deleteAt _x; continue; };
            _map drawIcon ["\A3\ui_f\data\map\markers\military\destroy_CA.paa", [0.95, 0.25, 0.2, 1 - ((time - _t) / 80)], _p, 16, 16, 0, " CONTACT", 1, 0.026, (COMSPEC_ATAK_FontSet select 1), "right"];
        } forEach (+_gc);
    };
};
