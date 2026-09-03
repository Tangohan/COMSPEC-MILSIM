/*
    Zones : cercle, ellipse, rectangle, corridor, secteur (forme courante).
*/
params [["_world", []]];
if (!(_world isEqualType []) || {(count _world) < 2}) exitWith {};
private _a = missionNamespace getVariable ["COMSPEC_MapZoneA", []];
if (!(_a isEqualType []) || {(count _a) < 2}) exitWith {
    missionNamespace setVariable ["COMSPEC_MapZoneA", _world, false];
    ["INFO", "Premier point de zone posé"] call comspec_overwatch_atak_athena_fnc_showNotification;
};
private _shape = missionNamespace getVariable ["COMSPEC_MapZoneShape", "circle"];
private _name = format ["COMSPEC_ZONE_%1", round diag_tickTime];
createMarkerLocal [_name, _a];
private _dx = abs ((_world select 0) - (_a select 0));
private _dy = abs ((_world select 1) - (_a select 1));
private _r = _a distance2D _world;
switch (_shape) do {
    case "ellipse": {
        _name setMarkerShapeLocal "ELLIPSE";
        _name setMarkerSizeLocal [_dx max 20, _dy max 20];
    };
    case "rectangle": {
        _name setMarkerShapeLocal "RECTANGLE";
        _name setMarkerSizeLocal [_dx max 20, _dy max 20];
    };
    case "corridor": {
        _name setMarkerShapeLocal "RECTANGLE";
        _name setMarkerSizeLocal [_r / 2, 40];
        _name setMarkerDirLocal (_a getDir _world);
    };
    case "sector": {
        _name setMarkerShapeLocal "ELLIPSE";
        _name setMarkerSizeLocal [_r, _r];
        _name setMarkerDirLocal (_a getDir _world);
        _name setMarkerBrushLocal "Border";
    };
    default {
        _name setMarkerShapeLocal "ELLIPSE";
        _name setMarkerSizeLocal [_r, _r];
    };
};
_name setMarkerColorLocal "ColorOrange";
_name setMarkerAlphaLocal 0.35;
private _lab = switch (_shape) do {
    case "ellipse": { "ellipse" };
    case "rectangle": { "rectangle" };
    case "corridor": { "corridor" };
    case "sector": { "secteur" };
    default { "cercle" };
};
["INFO", format ["Zone %1 %2 m", _lab, round _r]] call comspec_overwatch_atak_athena_fnc_showNotification;
missionNamespace setVariable ["COMSPEC_MapZoneA", [], false];
