#include "..\script_component.hpp"

private _state = call Iceman_fnc_jump_getState;
_state set ["waypoints", []];
_state set ["path", []];
_state set ["segments", []];
_state set ["ticks", []];
_state set ["distance", 0];
_state set ["canopyTime", 0];
_state set ["planned", false];
call Iceman_fnc_jump_updatePanel;

["JUMP", "Jump waypoints cleared.", 3] call cTab_fnc_addNotification;
