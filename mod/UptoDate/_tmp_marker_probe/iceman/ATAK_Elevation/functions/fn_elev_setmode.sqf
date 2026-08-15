#include "..\script_component.hpp"

params ["_mode"];

private _state = call Iceman_fnc_elev_getState;
_state set ["mode", _mode];
_state set ["selectMode", ""];
_state set ["planning", false];
_state set ["planningId", -1];
_state set ["status", ["View Shed ready", "Heatmap ready"] select (_mode == "heatmap")];
call Iceman_fnc_elev_updatePanel;
