#include "..\script_component.hpp"

params ["_map", "_button", "_x", "_y"];
if (_button != 0) exitWith {};

private _state = call Iceman_fnc_jump_getState;
private _mode = _state getOrDefault ["selectMode", ""];
if (_mode == "") exitWith {};

private _pos = _map ctrlMapScreenToWorld [_x, _y];
if (_mode == "waypoint") then {
    [_pos] call Iceman_fnc_jump_addWaypoint;
} else {
    [_mode, _pos] call Iceman_fnc_jump_setPoint;
    private _target = switch (_mode) do {
        case "jumpPoint": {"Jump point"};
        case "dropZone": {"Drop zone"};
        default {toUpper _mode};
    };
    ["JUMP", format ["%1 set.", _target], 3] call cTab_fnc_addNotification;
};
