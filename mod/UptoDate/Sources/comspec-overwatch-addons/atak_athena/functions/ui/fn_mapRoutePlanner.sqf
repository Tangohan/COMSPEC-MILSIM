/*
    Itinéraire : points successifs, distances intermédiaires et totale.
    Traits dessinés en overlay (pas IceMan Route).
*/
params [["_world", []]];
if (!(_world isEqualType []) || {(count _world) < 2}) exitWith {};
private _pts = missionNamespace getVariable ["COMSPEC_MapRoutePts", []];
if (!(_pts isEqualType [])) then { _pts = []; };
_pts pushBack _world;
missionNamespace setVariable ["COMSPEC_MapRoutePts", _pts, false];
private _n = count _pts;
if (_n < 2) exitWith {
    ["INFO", "Point de départ d’itinéraire"] call comspec_overwatch_atak_athena_fnc_showNotification;
};
private _total = 0;
private _i = 1;
while { _i < _n } do {
    _total = _total + ((_pts select (_i - 1)) distance2D (_pts select _i));
    _i = _i + 1;
};
private _last = (_pts select (_n - 2)) distance2D (_pts select (_n - 1));
["INFO", format ["Segment %1 m — total %2 m", round _last, round _total]] call comspec_overwatch_atak_athena_fnc_showNotification;
private _mk = format ["COMSPEC_ROUTE_%1", _n];
createMarkerLocal [_mk, _world];
_mk setMarkerTypeLocal "mil_dot";
_mk setMarkerColorLocal "ColorBlue";
_mk setMarkerTextLocal str _n;
_mk setMarkerSizeLocal [0.6, 0.6];
