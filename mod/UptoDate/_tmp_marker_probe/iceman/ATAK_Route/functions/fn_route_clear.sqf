#include "..\script_component.hpp"

private _state = call Iceman_fnc_route_getState;
_state set ["start", []];
_state set ["end", []];
_state set ["waypoints", []];
_state set ["route", []];
_state set ["turns", []];
_state set ["distance", 0];
_state set ["remaining", 0];
_state set ["selectMode", ""];
_state set ["tab", "route"];
_state set ["active", false];
_state set ["planning", false];
_state set ["planningId", -1];
_state set ["nextTurn", 0];
_state set ["lastPromptTurn", -1];
call Iceman_fnc_route_updatePanel;
