#include "..\script_component.hpp"

params ["_group", ["_interfaceInit", false], "_isDialog", "_settings"];

private _display = ctrlParent _group;
uiNamespace setVariable ["Iceman_ATAK_Elevation_pageGroup", _group];
[_display] call Iceman_fnc_elev_installMapHandlers;
call Iceman_fnc_elev_updatePanel;

[{
    call Iceman_fnc_elev_updatePanel;
}, 0.05] call CBA_fnc_waitAndExecute;
