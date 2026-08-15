#include "..\script_component.hpp"

params ["_kind", "_pos"];
private _state = call Iceman_fnc_route_getState;
_state set [_kind, _pos];
_state set ["selectMode", ""];
_state set ["route", []];
_state set ["turns", []];
_state set ["distance", 0];
_state set ["remaining", 0];
_state set ["active", false];
_state set ["planning", false];
_state set ["planningId", -1];
_state set ["nextTurn", 0];
_state set ["lastPromptTurn", -1];
call Iceman_fnc_route_updatePanel;
