/*
    Mesure distance + azimut entre deux points.
*/
params [["_world", []]];
if (!(_world isEqualType []) || {(count _world) < 2}) exitWith {};
private _a = missionNamespace getVariable ["COMSPEC_MapMeasureA", []];
if (!(_a isEqualType []) || {(count _a) < 2}) exitWith {
    missionNamespace setVariable ["COMSPEC_MapMeasureA", _world, false];
    ["INFO", "Premier point de mesure posé"] call comspec_overwatch_atak_athena_fnc_showNotification;
};
private _dst = _a distance2D _world;
private _az = round (_a getDir _world);
if (_az < 0) then { _az = _az + 360; };
private _txt = if (_dst >= 1000) then {
    format ["%1 km  %2°", ((round (_dst / 100)) / 10), _az]
} else {
    format ["%1 m  %2°", round _dst, _az]
};
["INFO", format ["Mesure %1", _txt]] call comspec_overwatch_atak_athena_fnc_showNotification;
missionNamespace setVariable ["COMSPEC_MapMeasureA", [], false];
["measure"] call comspec_overwatch_atak_athena_fnc_setActiveTool;
