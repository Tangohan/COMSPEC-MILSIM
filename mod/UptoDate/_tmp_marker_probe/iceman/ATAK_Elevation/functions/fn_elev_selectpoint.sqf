#include "..\script_component.hpp"

params ["_mode"];

private _state = call Iceman_fnc_elev_getState;
if (_mode == "active") then {
    _mode = _state getOrDefault ["mode", "viewshed"];
};
_state set ["mode", _mode];
_state set ["selectMode", _mode];
_state set ["planning", false];
_state set ["planningId", -1];
_state set ["status", ["Tap map for View Shed point", "Tap map for Heatmap center"] select (_mode == "heatmap")];
call Iceman_fnc_elev_updatePanel;
["ELEVATION", "Tap the ATAK map to place the point.", 4] call cTab_fnc_addNotification;
