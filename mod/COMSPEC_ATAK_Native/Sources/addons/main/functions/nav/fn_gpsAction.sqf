/*
    App GPS. Params : [action, argument]
      "grid" : itinéraire vers la grille saisie     "pick" : outil ROUTE sur la carte (clic sur la destination)
      "wp" : vers l'étape active des points de passage     "marker" nom : vers un marqueur
      "map" : ouvre la carte en suivi     "stop" : arrête le guidage
*/
params [["_act", ""], ["_arg", ""]];
private _f = uiNamespace getVariable ["COMSPEC_ATAK_Gps", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_Gps", _f];
private _go = {
    params ["_p", "_label"];
    [[_p select 0, _p select 1, 0], _label] spawn comspec_atak_native_fnc_routeCompute;
    [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "GPS") then { ["GPS"] call comspec_atak_native_fnc_pageRender; }; }, [], 1.5] call CBA_fnc_waitAndExecute;
};
switch (_act) do {
    case "grid": {
        private _txt = ["gpsGrid", _f getOrDefault ["grid", ""]] call comspec_atak_native_fnc_formValue;
        _f set ["grid", _txt];
        private _g = (_txt splitString " ,.-") joinString "";
        if ((count _g) < 4 || {((count _g) mod 2) isEqualTo 1}) exitWith { ["WARNING", "Grille invalide : 4, 6, 8 ou 10 chiffres", 3, 20] call comspec_atak_native_fnc_notify; };
        private _p = ([_g] call BIS_fnc_gridToPos) param [0, []];
        if ((count _p) < 2) exitWith { ["WARNING", "Grille hors de la carte", 3, 20] call comspec_atak_native_fnc_notify; };
        // Centre de la case pour une grille courte.
        private _cell = (([_g] call BIS_fnc_gridToPos) param [1, [0, 0]]) apply { _x / 2 };
        [[(_p select 0) + (_cell select 0), (_p select 1) + (_cell select 1)], format ["Grille %1", _txt]] call _go;
    };
    case "pick": {
        ["MAP"] call comspec_atak_native_fnc_navigate;
        [{ ["ROUTE"] call comspec_atak_native_fnc_mapToolSet; ["MAP"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
    };
    case "wp": { ["gps"] call comspec_atak_native_fnc_wpAction; };
    case "marker": { [getMarkerPos _arg, markerText _arg] call _go; };
    case "map": {
        (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["mapFollow", true];
        ["MAP"] call comspec_atak_native_fnc_navigate;
    };
    case "stop": {
        missionNamespace setVariable ["COMSPEC_ATAK_Route", createHashMap];
        ["INFO", "Guidage arrêté", 2, 10] call comspec_atak_native_fnc_notify;
        [{ ["GPS"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
    };
};
true
