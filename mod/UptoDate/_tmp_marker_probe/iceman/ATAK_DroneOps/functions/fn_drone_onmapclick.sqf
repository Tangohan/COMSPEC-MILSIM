params ["_map", "_button", "_x", "_y"];
if (_button != 0) exitWith {};

private _state = call Iceman_fnc_drone_getState;
private _mode = _state getOrDefault ["selectMode", ""];
if (_mode != "target") exitWith {};

private _pos = _map ctrlMapScreenToWorld [_x, _y];
_state set ["target", _pos];
_state set ["selectMode", ""];

["DRONE", format ["Drone point set: %1.", [_pos] call Iceman_fnc_drone_posToGrid], 3] call cTab_fnc_addNotification;
call Iceman_fnc_drone_updatePanel;
