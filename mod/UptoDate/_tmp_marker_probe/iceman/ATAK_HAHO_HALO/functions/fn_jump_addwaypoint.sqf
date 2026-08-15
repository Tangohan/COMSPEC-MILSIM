#include "..\script_component.hpp"

params ["_pos"];

private _state = call Iceman_fnc_jump_getState;
private _waypoints = +(_state getOrDefault ["waypoints", []]);
_waypoints pushBack _pos;

_state set ["waypoints", _waypoints];
_state set ["selectMode", ""];
_state set ["path", []];
_state set ["segments", []];
_state set ["ticks", []];
_state set ["distance", 0];
_state set ["canopyTime", 0];
_state set ["planned", false];
_state set ["warnings", []];
call Iceman_fnc_jump_updatePanel;

["JUMP", format ["Waypoint %1 added.", count _waypoints], 3] call cTab_fnc_addNotification;
