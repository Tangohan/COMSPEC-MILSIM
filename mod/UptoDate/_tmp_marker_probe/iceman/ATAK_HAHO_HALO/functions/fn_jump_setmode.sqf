#include "..\script_component.hpp"

params [["_mode", "HAHO"]];

private _state = call Iceman_fnc_jump_getState;
_state set ["mode", toUpper _mode];
_state set ["planned", false];
_state set ["ticks", []];
_state set ["warnings", []];

call Iceman_fnc_jump_updatePanel;
["JUMP", format ["%1 mode selected.", toUpper _mode], 3] call cTab_fnc_addNotification;
