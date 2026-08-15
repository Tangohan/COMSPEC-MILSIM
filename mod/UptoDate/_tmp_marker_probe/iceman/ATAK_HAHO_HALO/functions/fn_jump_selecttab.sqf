#include "..\script_component.hpp"

params [["_tab", "plan"]];

private _state = call Iceman_fnc_jump_getState;
_state set ["tab", _tab];
_state set ["selectMode", ""];
call Iceman_fnc_jump_updatePanel;
