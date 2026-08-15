#include "..\script_component.hpp"

{
    private _display = uiNamespace getVariable [_x, displayNull];
    if (!isNull _display) then {
        [_display] call Iceman_fnc_elev_installMapHandlers;
    };
} forEach [
    "cTab_Android_dlg",
    "cTab_Android_dsp",
    "cTab_Tablet_dlg",
    "cTab_FBCB2_dlg",
    "cTab_TAD_dlg",
    "cTab_TAD_dsp",
    "cTab_microDAGR_dlg",
    "cTab_microDAGR_dsp"
];
