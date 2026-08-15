#include "..\script_component.hpp"

params ["_map", "_button", "_x", "_y"];
if (_button != 0) exitWith {};

private _state = call Iceman_fnc_elev_getState;
private _mode = _state getOrDefault ["selectMode", ""];
if (_mode == "") exitWith {};

private _pos = _map ctrlMapScreenToWorld [_x, _y];
[_mode, _pos] call Iceman_fnc_elev_setPoint;
["ELEVATION", format ["%1 point set.", toUpper _mode], 3] call cTab_fnc_addNotification;
