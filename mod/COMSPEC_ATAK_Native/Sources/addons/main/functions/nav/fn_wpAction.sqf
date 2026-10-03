/*
    Points de passage : itinéraire en étapes, partageable au groupe, navigation étape par étape.
    État : missionNamespace COMSPEC_ATAK_Waypoints (HashMap) : pts [[pos, nom]...], idx (étape active), nav (bool), from (auteur).
    Params : [action, argument]
      "add" pos | "del" index | "clear" | "nav" (démarre / arrête) | "next" | "prev" | "goto" index
      "share" (envoie au groupe) | "receive" [pts, auteur] (événement) | "gps" (itinéraire routier vers l'étape active) | "tick" (arrivée auto)
*/
params [["_act", ""], ["_arg", []]];
private _w = missionNamespace getVariable ["COMSPEC_ATAK_Waypoints", createHashMap];
if ((count _w) isEqualTo 0) then { _w = createHashMapFromArray [["pts", []], ["idx", 0], ["nav", false], ["from", ""]]; missionNamespace setVariable ["COMSPEC_ATAK_Waypoints", _w]; };
private _pts = _w get "pts";
private _rerender = { [{ private _p = (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]; if (_p in ["WAYPOINTS", "MAP"]) then { [_p] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame; };
switch (_act) do {
    case "add": {
        if ((count _pts) >= 20) exitWith { ["WARNING", "20 étapes au maximum", 3, 20] call comspec_atak_native_fnc_notify; };
        _pts pushBack [[_arg select 0, _arg select 1, 0], format ["WP%1", (count _pts) + 1]];
        ["INFO", format ["Étape WP%1 ajoutée", count _pts], 2, 10] call comspec_atak_native_fnc_notify;
        call _rerender;
    };
    case "del": {
        if (_arg isEqualType 0 && {_arg < count _pts}) then { _pts deleteAt _arg; };
        { _x set [1, format ["WP%1", _forEachIndex + 1]]; } forEach _pts;
        _w set ["idx", (_w get "idx") min (((count _pts) - 1) max 0)];
        call _rerender;
    };
    case "clear": { _w set ["pts", []]; _w set ["idx", 0]; _w set ["nav", false]; _w set ["from", ""]; call _rerender; };
    case "nav": {
        if ((count _pts) isEqualTo 0) exitWith {};
        _w set ["nav", !(_w get "nav")];
        if (_w get "nav") then { ["SUCCESS", format ["Navigation : étape %1 / %2", (_w get "idx") + 1, count _pts], 3, 20] call comspec_atak_native_fnc_notify; };
        call _rerender;
    };
    case "next": { _w set ["idx", ((_w get "idx") + 1) min (((count _pts) - 1) max 0)]; call _rerender; };
    case "prev": { _w set ["idx", ((_w get "idx") - 1) max 0]; call _rerender; };
    case "goto": { _w set ["idx", _arg]; _w set ["nav", true]; call _rerender; };
    case "gps": {
        if ((count _pts) isEqualTo 0) exitWith {};
        (_pts select (_w get "idx")) params ["_p", "_n"];
        [_p, _n] spawn comspec_atak_native_fnc_routeCompute;
        ["MAP"] call comspec_atak_native_fnc_navigate;
    };
    case "share": {
        if ((count _pts) isEqualTo 0) exitWith {};
        private _to = (units group player) select { isPlayer _x && {_x isNotEqualTo player} };
        if ((count _to) isEqualTo 0) exitWith { ["WARNING", "Personne d'autre dans le groupe", 3, 20] call comspec_atak_native_fnc_notify; };
        [{
            params ["_pts", "_to"];
            private _who = [player] call comspec_atak_native_fnc_unitCallsign;
            ["comspec_atak_native_waypoints", [_pts, _who], _to] call CBA_fnc_targetEvent;
            ["comspec_atak_native_wpStore", [netId group player, _pts, _who]] call CBA_fnc_serverEvent;
        }, [+_pts, _to], "Itinéraire", 2] call comspec_atak_native_fnc_netSend;
        ["SUCCESS", format ["Itinéraire envoyé à %1 membre(s)", count _to], 3, 20] call comspec_atak_native_fnc_notify;
    };
    case "receive": {
        _arg params ["_in", "_who", ["_start", true]];
        _w set ["pts", _in]; _w set ["idx", 0]; _w set ["nav", _start]; _w set ["from", _who];
        ["WARNING", format ["Itinéraire reçu de %1 : %2 étape(s), navigation lancée", _who, count _in], 6, 60] call comspec_atak_native_fnc_notify;
        [] call comspec_atak_native_fnc_vibrate;
        call _rerender;
    };
    case "tick": {
        if !(_w get "nav") exitWith {};
        if ((count _pts) isEqualTo 0) exitWith { _w set ["nav", false]; };
        private _i = _w get "idx";
        (_pts select _i) params ["_p", "_n"];
        if ((player distance2D _p) < 25) then {
            if (_i >= ((count _pts) - 1)) then {
                _w set ["nav", false];
                ["SUCCESS", format ["Dernière étape atteinte (%1)", _n], 5, 40] call comspec_atak_native_fnc_notify;
            } else {
                _w set ["idx", _i + 1];
                ["INFO", format ["%1 atteinte, cap sur %2", _n, (_pts select (_i + 1)) select 1], 4, 30] call comspec_atak_native_fnc_notify;
            };
            [] call comspec_atak_native_fnc_vibrate;
            call _rerender;
        };
    };
};
true
