#include "..\script_component.hpp"

params ["_kind", "_pos"];

private _state = call Iceman_fnc_jump_getState;
_state set [_kind, _pos];
_state set ["selectMode", ""];
_state set ["path", []];
_state set ["segments", []];
_state set ["ticks", []];
_state set ["distance", 0];
_state set ["canopyTime", 0];
_state set ["planned", false];
_state set ["warnings", []];
call Iceman_fnc_jump_updatePanel;
