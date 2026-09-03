/*
    Sélectionne une unité / un marqueur sous le curseur carte.
*/
params [["_mapCtrl", controlNull], ["_world", []]];
if (isNull _mapCtrl || {!(_world isEqualType [])}) exitWith { objNull };
private _best = objNull;
private _bestD = 40;
{
    private _d = _x distance2D _world;
    if (_d < _bestD) then {
        _bestD = _d;
        _best = _x;
    };
} forEach (allPlayers + (units group player));
private _mk = "";
{
    if ((markerPos _x) distance2D _world < 25) exitWith { _mk = _x };
} forEach allMapMarkers;
missionNamespace setVariable ["COMSPEC_MapSelected", _best, false];
missionNamespace setVariable ["COMSPEC_MapSelectedMarker", _mk, false];
[_best, _mk] call comspec_overwatch_atak_athena_fnc_setInspector;
_best
