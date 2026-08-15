#include "..\script_component.hpp"

params ["_mode", "_pos"];

private _state = call Iceman_fnc_elev_getState;
if (_mode == "heatmap") then {
    _state set ["heatmapCenter", _pos];
    _state set ["mode", "heatmap"];
    _state set ["status", "Heatmap center set"];
} else {
    _state set ["viewshedPoint", _pos];
    _state set ["mode", "viewshed"];
    _state set ["status", "View Shed point set"];
};
_state set ["selectMode", ""];
_state set ["planning", false];
_state set ["planningId", -1];
call Iceman_fnc_elev_updatePanel;
