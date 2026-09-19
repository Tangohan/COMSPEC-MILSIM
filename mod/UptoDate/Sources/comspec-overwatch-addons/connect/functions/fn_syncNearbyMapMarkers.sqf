/*
    Après un clic / double-clic carte ATAK : pousser vers Athena les
    marqueurs Widget / OTAN autour du point (force = ignore liaison dégradée).
    Params: [_pos, _radius]
*/
params [
    ["_pos", [0, 0, 0], [[]]],
    ["_radius", 90, [0]]
];

if (!hasInterface) exitWith { 0 };
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { 0 };
if (!(missionNamespace getVariable ["comspec_overwatch_sync_map_markers", true])) exitWith { 0 };
if ((count _pos) < 2) exitWith { 0 };

private _n = 0;
private _markers = +allMapMarkers;
if (!(_markers isEqualType [])) exitWith { 0 };

{
    private _name = _x;
    if (_name isEqualTo "") then { continue };
    private _ok = [_name] call comspec_overwatch_connect_fnc_isSyncableMapMarker;
    if (!_ok) then { continue };
    private _mp = markerPos _name;
    if ((_mp distance2D _pos) > _radius) then { continue };
    [_name, false, true] call comspec_overwatch_connect_fnc_syncMapMarker;
    _n = _n + 1;
} forEach _markers;

if (!isNil "comspec_overwatch_atak_athena_fnc_athena_bridgeCtabMarkers") then {
    [] call comspec_overwatch_atak_athena_fnc_athena_bridgeCtabMarkers;
};
[] call comspec_overwatch_connect_fnc_queueMapMarker;

_n
