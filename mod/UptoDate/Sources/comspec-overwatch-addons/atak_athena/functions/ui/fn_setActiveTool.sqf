/*
    Active ou désactive un outil carte (mesure, grille, itinéraire, zone, couches).
    Zone : reclic cycle cercle / ellipse / rectangle / corridor / secteur.
*/
params [["_tool", "", [""]]];
private _cur = missionNamespace getVariable ["COMSPEC_MapActiveTool", ""];
if (_cur isEqualTo _tool && {_tool isEqualTo "zone"}) exitWith {
    private _shapes = ["circle", "ellipse", "rectangle", "corridor", "sector"];
    private _s = missionNamespace getVariable ["COMSPEC_MapZoneShape", "circle"];
    private _i = _shapes find _s;
    if (_i < 0) then { _i = 0; };
    private _next = _shapes select ((_i + 1) mod 5);
    missionNamespace setVariable ["COMSPEC_MapZoneShape", _next, false];
    private _lab = switch (_next) do {
        case "ellipse": { "ellipse" };
        case "rectangle": { "rectangle" };
        case "corridor": { "corridor" };
        case "sector": { "secteur" };
        default { "cercle" };
    };
    ["INFO", format ["Zone : %1", _lab]] call comspec_overwatch_atak_athena_fnc_showNotification;
    "zone"
};
if (_cur isEqualTo _tool) then { _tool = ""; };
missionNamespace setVariable ["COMSPEC_MapActiveTool", _tool, false];
if (_tool isEqualTo "measure") then {
    missionNamespace setVariable ["COMSPEC_MapMeasureA", []];
};
if (_tool isEqualTo "route") then {
    missionNamespace setVariable ["COMSPEC_MapRoutePts", []];
};
if (_tool isEqualTo "zone") then {
    missionNamespace setVariable ["COMSPEC_MapZoneA", []];
};
_tool
