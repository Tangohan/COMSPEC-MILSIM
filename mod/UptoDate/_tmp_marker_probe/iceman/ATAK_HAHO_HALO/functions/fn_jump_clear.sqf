#include "..\script_component.hpp"

private _state = call Iceman_fnc_jump_getState;
_state set ["jumpPoint", []];
_state set ["dropZone", []];
_state set ["waypoints", []];
_state set ["path", []];
_state set ["segments", []];
_state set ["ticks", []];
_state set ["distance", 0];
_state set ["canopyTime", 0];
_state set ["selectMode", ""];
_state set ["planned", false];
_state set ["requiredExitAGL", 0];
_state set ["requiredPullAGL", 0];
_state set ["warnings", []];
call Iceman_fnc_jump_updatePanel;
