#include "..\script_component.hpp"

private _state = call Iceman_fnc_route_getState;
_state set ["active", false];
call Iceman_fnc_route_updatePanel;
