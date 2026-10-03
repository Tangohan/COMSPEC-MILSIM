/*
    Actions de l'app JTAC.
      "code", n : code laser ACE      "lock" / "unlock" : verrouille la cible (laser ou visée)
      "pick", netId : appareil contrôlé      "type", n : type de contrôle
      "call", "target" | "hot" | "continue" | "abort" : appel à l'équipage (messagerie de son ATAK)
      "nine" : ouvre le 9-LINE de l'app Feux pré-rempli      "map" : centre la carte sur la cible
*/
params [["_act", ""], ["_arg", ""]];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_Jtac", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_Jtac", _s];
private _render = { [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "JTAC") then { ["JTAC"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame; };
private _me = { private _c = if (!isNil "comspec_overwatch_connect_fnc_getCallsign") then { [] call comspec_overwatch_connect_fnc_getCallsign } else { "" }; if (_c isEqualTo "") then { groupId group player } else { _c } };
switch (_act) do {
    case "code": { player setVariable ["ace_laser_code", _arg, true]; ["INFO", format ["Code laser %1", _arg], 2, 10] call comspec_atak_native_fnc_notify; call _render; };
    case "lock": {
        private _t = [] call comspec_atak_native_fnc_jtacTarget;
        if ((count _t) isEqualTo 0) exitWith { ["WARNING", "Aucune cible : visez un point à moins de 3 km", 3, 20] call comspec_atak_native_fnc_notify; };
        _s set ["target", _t select 0];
        _s set ["source", format ["%1 · %2", _t select 1, [dayTime, "HH:MM"] call BIS_fnc_timeToString]];
        ["SUCCESS", format ["Cible verrouillée : %1", [_t select 0, 10] call comspec_atak_native_fnc_gridRef], 3, 20] call comspec_atak_native_fnc_notify;
        call _render;
    };
    case "unlock": { _s set ["target", []]; call _render; };
    case "pick": { _s set ["air", _arg call BIS_fnc_objectFromNetId]; call _render; };
    case "type": { _s set ["type", _arg]; call _render; };
    case "map": {
        private _p = _s getOrDefault ["target", []];
        if ((count _p) < 2) exitWith {};
        ["MAP"] call comspec_atak_native_fnc_navigate;
        [{ [_this, 0.03] call comspec_atak_native_fnc_mapCenter; }, [_p select 0, _p select 1]] call CBA_fnc_execNextFrame;
    };
    case "nine": {
        private _p = _s getOrDefault ["target", []];
        if ((count _p) < 2) exitWith {};
        private _f = [] call comspec_atak_native_fnc_firesState;
        _f set ["target", _p];
        _f set ["tab", "nine"];
        ["prefill9"] call comspec_atak_native_fnc_firesAction;
        ["FIRES"] call comspec_atak_native_fnc_navigate;
    };
    case "call": {
        private _air = _s getOrDefault ["air", objNull];
        if (isNull _air || {!alive _air}) exitWith { ["WARNING", "Choisissez d'abord un appareil", 3, 20] call comspec_atak_native_fnc_notify; };
        private _p = _s getOrDefault ["target", []];
        private _grid = if ((count _p) >= 2) then { [_p, 10] call comspec_atak_native_fnc_gridRef } else { "-" };
        private _code = player getVariable ["ace_laser_code", 1111];
        private _body = switch (_arg) do {
            case "target": { format ["[JTAC] Cible %1, altitude %2 m, laser %3, type %4. Signalez quand prêt.", _grid, round (getTerrainHeightASL _p), _code, _s getOrDefault ["type", 2]] };
            case "hot": { format ["[JTAC] CLEARED HOT sur %1, laser %2.", _grid, _code] };
            case "continue": { "[JTAC] CONTINUE (pas d'autorisation de tir)." };
            default { "[JTAC] ABORT ABORT ABORT." };
        };
        private _crew = (crew _air) select { isPlayer _x };
        private _time = [dayTime, "HH:MM"] call BIS_fnc_timeToString;
        if ((count _crew) > 0) then { ["comspec_atak_native_p2p", [[] call _me, _body, _time], _crew] call CBA_fnc_targetEvent; };
        private _log = _s getOrDefault ["log", []];
        _log = [[_time, format ["%1 → %2%3", _body, groupId group _air, ["", " (IA, rien reçu)"] select ((count _crew) isEqualTo 0)]]] + _log;
        _s set ["log", _log select [0, 20]];
        [["SUCCESS", "WARNING"] select (_arg isEqualTo "abort"), _body, 3, 40] call comspec_atak_native_fnc_notify;
        call _render;
    };
};
true
