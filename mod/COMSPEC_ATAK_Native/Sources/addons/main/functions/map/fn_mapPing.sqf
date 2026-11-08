/*
    Ping de carte partagé avec mon camp (menu clic droit de la carte).
    ["send", position]                 envoie le ping à tous les joueurs de mon camp (moi compris)
    ["recv", [position, indicatif, émetteur]]  reçu (événement CBA comspec_atak_native_mapPing) : gardé 10 s,
                                       bandeau + vibration chez les autres (seulement avec un téléphone ATAK)
    ["draw", contrôle carte]           cercles qui s'élargissent + indicatif (appelé depuis fn_mapOnDraw)
    Pings en cours : missionNamespace COMSPEC_ATAK_Pings = [[position, heure diag_tickTime, indicatif, émetteur]...]
    Pointeur de l'appui maintenu (fn_mapOnDraw), comme celui de la carte du jeu : suit le curseur, s'arrête au relâchement.
    ["pointStart", carte] / ["pointTick", carte] / ["pointStop"]   chez celui qui pointe : à l'écran à chaque image ; au réseau
                                       4 fois par seconde au plus s'il bouge, une fois par seconde s'il est immobile, puis la fin
    ["pointRecv", [position ou [], indicatif, émetteur]]   événement CBA comspec_atak_native_mapPoint : sans bandeau ni vibration
    Pointeurs affichés : missionNamespace COMSPEC_ATAK_Pointers, émetteur -> [position, heure, indicatif] ; un pointeur sans
    nouvelle depuis 2,5 s disparaît (relâchement jamais reçu).
*/
params [["_mode", ""], ["_arg", []]];
private _life = 10;
switch (_mode) do {
    case "send": {
        private _pos = [_arg select 0, _arg select 1, 0];
        if ((([] call comspec_atak_native_fnc_linkQuality) getOrDefault ["bars", 1]) <= 0) exitWith {
            ["WARNING", "Ping impossible : pas de réseau", 3, 20] call comspec_atak_native_fnc_notify;
        };
        private _cs = [player, true] call comspec_atak_native_fnc_unitCallsign;
        if (_cs isEqualTo "") then { _cs = name player; };
        private _to = allPlayers select { side group _x isEqualTo side group player };
        _to pushBackUnique player;
        ["comspec_atak_native_mapPing", [_pos, _cs, player], _to] call CBA_fnc_targetEvent;
        ["TACTICAL", format ["Ping envoyé · %1", [_pos] call comspec_atak_native_fnc_gridRef], 3, 30] call comspec_atak_native_fnc_notify;
    };
    case "recv": {
        _arg params [["_pos", []], ["_cs", ""], ["_from", objNull]];
        if ((count _pos) < 2 || {!hasInterface}) exitWith {};
        if (_from isNotEqualTo player && {!([player] call comspec_atak_native_fnc_hasDevice)}) exitWith {};
        private _pings = (missionNamespace getVariable ["COMSPEC_ATAK_Pings", []]) select { (diag_tickTime - (_x select 1)) < _life };
        _pings pushBack [_pos, diag_tickTime, _cs, _from];
        missionNamespace setVariable ["COMSPEC_ATAK_Pings", _pings];
        if (_from isNotEqualTo player) then {
            ["TACTICAL", format ["PING %1 · %2", _cs, [_pos] call comspec_atak_native_fnc_gridRef], 4, 40] call comspec_atak_native_fnc_notify;
            [] call comspec_atak_native_fnc_vibrate;
        };
    };
    case "pointStart": {
        private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        private _d = ctrlParent _arg;
        // Relâchement en dehors de la carte : vu par l'écran entier.
        private _eh = _d displayAddEventHandler ["MouseButtonUp", { if ((_this select 1) isEqualTo 0) then { ["pointStop"] call comspec_atak_native_fnc_mapPing; }; false }];
        private _net = (([] call comspec_atak_native_fnc_linkQuality) getOrDefault ["bars", 1]) > 0;
        if (!_net) then { ["WARNING", "Ping impossible : pas de réseau", 3, 20] call comspec_atak_native_fnc_notify; };
        private _cs = [player, true] call comspec_atak_native_fnc_unitCallsign;
        if (_cs isEqualTo "") then { _cs = name player; };
        private _me = player;
        private _to = if (_net) then { allPlayers select { _x isNotEqualTo _me && {side group _x isEqualTo side group _me} } } else { [] };
        _s set ["mapPoint", createHashMapFromArray [["d", _d], ["eh", _eh], ["to", _to], ["cs", _cs], ["at", diag_tickTime], ["sent", -1], ["pos", [-1e6, -1e6, 0]]]];
        ["pointTick", _arg] call comspec_atak_native_fnc_mapPing;
    };
    case "pointTick": {
        private _map = _arg;
        private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        private _p = _s getOrDefault ["mapPoint", createHashMap];
        private _now = diag_tickTime;
        // Écran refermé bouton appuyé, ou appui oublié : arrêt.
        if ((count _p) isEqualTo 0 || {(_p get "d") isNotEqualTo (ctrlParent _map)} || {(_now - (_p get "at")) > 60}) exitWith { ["pointStop"] call comspec_atak_native_fnc_mapPing; };
        private _w = _map ctrlMapScreenToWorld getMousePosition;
        private _pos = [_w select 0, _w select 1, 0];
        private _tab = missionNamespace getVariable "COMSPEC_ATAK_Pointers";
        if (isNil "_tab") then { _tab = createHashMap; missionNamespace setVariable ["COMSPEC_ATAK_Pointers", _tab]; };
        _tab set [netId player, [_pos, _now, _p get "cs"]];
        private _dt = _now - (_p get "sent");
        if (_dt >= 1 || {_dt >= 0.25 && {(_pos distance2D (_p get "pos")) > ((_map ctrlMapScreenToWorld [0, 0]) distance2D (_map ctrlMapScreenToWorld [pixelW * 6, 0]))}}) then {
            _p set ["sent", _now];
            _p set ["pos", _pos];
            if ((count (_p get "to")) > 0) then { ["comspec_atak_native_mapPoint", [_pos, _p get "cs", player], _p get "to"] call CBA_fnc_targetEvent; };
        };
    };
    case "pointStop": {
        private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        _s set ["mapHold", false];
        _s set ["mapLc", []];
        private _p = _s getOrDefault ["mapPoint", createHashMap];
        if ((count _p) isEqualTo 0) exitWith {};
        _s set ["mapPoint", createHashMap];
        if (!isNull (_p get "d")) then { (_p get "d") displayRemoveEventHandler ["MouseButtonUp", _p get "eh"]; };
        (missionNamespace getVariable ["COMSPEC_ATAK_Pointers", createHashMap]) deleteAt (netId player);
        if ((count (_p get "to")) > 0) then { ["comspec_atak_native_mapPoint", [[], _p get "cs", player], _p get "to"] call CBA_fnc_targetEvent; };
    };
    case "pointRecv": {
        _arg params [["_pos", []], ["_cs", ""], ["_from", objNull]];
        if (!hasInterface || {_from isEqualTo player} || {!([player] call comspec_atak_native_fnc_hasDevice)}) exitWith {};
        private _tab = missionNamespace getVariable "COMSPEC_ATAK_Pointers";
        if (isNil "_tab") then { _tab = createHashMap; missionNamespace setVariable ["COMSPEC_ATAK_Pointers", _tab]; };
        if ((count _pos) < 2) then { _tab deleteAt (netId _from); } else { _tab set [netId _from, [_pos, diag_tickTime, _cs]]; };
    };
    case "draw": {
        private _map = _arg;
        private _ptr = missionNamespace getVariable ["COMSPEC_ATAK_Pointers", createHashMap];
        if ((count _ptr) > 0) then {
            private _isP = uiNamespace getVariable ["COMSPEC_ATAK_MapIs", 1];
            private _old = [];
            {
                _y params ["_pos", "_t0", "_cs"];
                private _age = diag_tickTime - _t0;
                if (_age > 2.5) then { _old pushBack _x; continue };
                private _al = ((2.5 - _age) / 1) min 1;
                _map drawIcon ["\A3\ui_f\data\map\markers\military\circle_CA.paa", [1, 0.85, 0.15, _al], _pos, 26 * _isP, 26 * _isP, 0, "", 0];
                _map drawIcon ["\A3\ui_f\data\map\markers\military\dot_CA.paa", [1, 0.85, 0.15, _al], _pos, 12 * _isP, 12 * _isP, 0, _cs, 2, 0.026 * (uiNamespace getVariable ["COMSPEC_ATAK_MapTs", 1]), COMSPEC_ATAK_FontSet select 1, "right"];
            } forEach _ptr;
            { _ptr deleteAt _x; } forEach _old;
        };
        private _pings = missionNamespace getVariable ["COMSPEC_ATAK_Pings", []];
        if ((count _pings) isEqualTo 0) exitWith {};
        private _is = uiNamespace getVariable ["COMSPEC_ATAK_MapIs", 1];
        private _ts = uiNamespace getVariable ["COMSPEC_ATAK_MapTs", 1];
        private _ring = "\A3\ui_f\data\map\markers\military\circle_CA.paa";
        private _dot = "\A3\ui_f\data\map\markers\military\dot_CA.paa";
        private _alive = [];
        {
            _x params ["_pos", "_t0", "_cs"];
            private _age = diag_tickTime - _t0;
            if (_age >= _life) then { continue };
            _alive pushBack _x;
            // Dernières secondes : le ping s'efface.
            private _fade = ((_life - _age) / 2) min 1;
            {
                private _ph = ((_age + _x) % 1.5) / 1.5;
                private _sz = (14 + 46 * _ph) * _is;
                _map drawIcon [_ring, [1, 0.85, 0.15, (1 - _ph) * 0.95 * _fade], _pos, _sz, _sz, 0, "", 0];
            } forEach [0, 0.75];
            _map drawIcon [_dot, [1, 0.85, 0.15, _fade], _pos, 14 * _is, 14 * _is, 0, format ["PING %1", _cs], 2, 0.026 * _ts, (COMSPEC_ATAK_FontSet select 1), "right"];
        } forEach _pings;
        if ((count _alive) isNotEqualTo (count _pings)) then { missionNamespace setVariable ["COMSPEC_ATAK_Pings", _alive]; };
    };
};
true
