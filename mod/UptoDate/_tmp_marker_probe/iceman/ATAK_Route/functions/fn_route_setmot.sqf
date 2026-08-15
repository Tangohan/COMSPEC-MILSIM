#include "..\script_component.hpp"

params ["_ctrl", "_index"];

private _state = call Iceman_fnc_route_getState;
private _mot = ["foot", "vehicle"] param [_index, "foot"];
if ((_state getOrDefault ["mot", "foot"]) == _mot) exitWith {};

_state set ["mot", _mot];
_state set ["route", []];
_state set ["turns", []];
_state set ["distance", 0];
_state set ["active", false];
_state set ["planning", false];
_state set ["planningId", -1];

call Iceman_fnc_route_updatePanel;
