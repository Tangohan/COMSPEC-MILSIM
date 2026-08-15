#include "..\script_component.hpp"

params ["_map", "_button", "_x", "_y"];
if (_button != 0) exitWith {};

private _state = call Iceman_fnc_route_getState;
private _mode = _state getOrDefault ["selectMode", ""];
if (_mode == "") exitWith {};

private _pos = _map ctrlMapScreenToWorld [_x, _y];
if (_mode == "waypoint") exitWith {
    [_pos] call Iceman_fnc_route_addWaypoint;
};

[_mode, _pos] call Iceman_fnc_route_setPoint;
["ROUTE", format ["%1 point set.", toUpper _mode], 3] call cTab_fnc_addNotification;
