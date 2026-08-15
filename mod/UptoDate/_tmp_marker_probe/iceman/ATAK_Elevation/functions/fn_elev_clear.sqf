#include "..\script_component.hpp"

private _state = call Iceman_fnc_elev_getState;
_state set ["selectMode", ""];
_state set ["overlayType", ""];
_state set ["overlay", []];
_state set ["active", false];
_state set ["planning", false];
_state set ["planningId", -1];
_state set ["status", "Cleared"];
call Iceman_fnc_elev_updatePanel;
