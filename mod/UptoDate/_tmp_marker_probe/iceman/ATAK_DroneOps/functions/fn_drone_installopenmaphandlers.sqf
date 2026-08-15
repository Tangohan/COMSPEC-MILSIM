{
    _x params ["_displayVar", "_drawContacts"];
    private _display = uiNamespace getVariable [_displayVar, displayNull];
    if (!isNull _display) then {
        [_display, _drawContacts] call Iceman_fnc_drone_installMapHandlers;
    };
} forEach [
    ["cTab_Android_dlg", true],
    ["cTab_Android_dsp", true],
    ["cTab_Tablet_dlg", false],
    ["cTab_FBCB2_dlg", false],
    ["cTab_TAD_dlg", false],
    ["cTab_TAD_dsp", false],
    ["cTab_microDAGR_dlg", false],
    ["cTab_microDAGR_dsp", false]
];
