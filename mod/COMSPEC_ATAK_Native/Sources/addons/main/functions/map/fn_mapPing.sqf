/*
    Ping de carte partagé avec mon camp (menu clic droit de la carte).
    ["send", position]                 envoie le ping à tous les joueurs de mon camp (moi compris)
    ["recv", [position, indicatif, émetteur]]  reçu (événement CBA comspec_atak_native_mapPing) : gardé 10 s,
                                       bandeau + vibration chez les autres (seulement avec un téléphone ATAK)
    ["draw", contrôle carte]           cercles qui s'élargissent + indicatif (appelé depuis fn_mapOnDraw)
    Pings en cours : missionNamespace COMSPEC_ATAK_Pings = [[position, heure diag_tickTime, indicatif, émetteur]...]
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
    case "draw": {
        private _map = _arg;
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
