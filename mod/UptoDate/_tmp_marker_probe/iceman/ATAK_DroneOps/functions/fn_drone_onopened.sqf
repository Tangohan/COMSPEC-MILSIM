params ["_group", ["_interfaceInit", false], "_isDialog", "_settings"];

if (isNull _group) exitWith {};

private _display = ctrlParent _group;
uiNamespace setVariable ["Iceman_ATAK_DroneOps_group", _group];

private _drawContacts = (
    { _display isEqualTo (uiNamespace getVariable [_x, displayNull]) }
    count ["cTab_Android_dlg", "cTab_Android_dsp"]
) > 0;
[_display, _drawContacts] call Iceman_fnc_drone_installMapHandlers;
call Iceman_fnc_drone_updatePanel;
