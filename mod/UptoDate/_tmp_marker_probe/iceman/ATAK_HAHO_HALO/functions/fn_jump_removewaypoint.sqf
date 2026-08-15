#include "..\script_component.hpp"

private _state = call Iceman_fnc_jump_getState;
private _waypoints = +(_state getOrDefault ["waypoints", []]);

if (_waypoints isEqualTo []) exitWith {
    ["JUMP", "No waypoint to remove.", 3] call cTab_fnc_addNotification;
};

_waypoints deleteAt ((count _waypoints) - 1);
_state set ["waypoints", _waypoints];
_state set ["path", []];
_state set ["segments", []];
_state set ["ticks", []];
_state set ["distance", 0];
_state set ["canopyTime", 0];
_state set ["planned", false];
call Iceman_fnc_jump_updatePanel;

["JUMP", "Last waypoint removed.", 3] call cTab_fnc_addNotification;
