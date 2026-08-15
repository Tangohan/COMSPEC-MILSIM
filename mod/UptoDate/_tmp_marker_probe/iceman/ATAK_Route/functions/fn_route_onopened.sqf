#include "..\script_component.hpp"

params ["_group", ["_interfaceInit", false], "_isDialog", "_settings"];
private _display = ctrlParent _group;
uiNamespace setVariable ["Iceman_ATAK_Route_pageGroup", _group];
[_display] call Iceman_fnc_route_installMapHandlers;

private _motCtrl = controlNull;
{
    if (ctrlIDC _x == 125) exitWith {_motCtrl = _x};
} forEach allControls _group;

if (!isNull _motCtrl && {lbSize _motCtrl == 0}) then {
    _motCtrl lbAdd "Foot";
    _motCtrl lbAdd "Vehicle";
    _motCtrl lbSetCurSel 0;
};

call Iceman_fnc_route_updatePanel;

[{
    call Iceman_fnc_route_updatePanel;
}, 0.05] call CBA_fnc_waitAndExecute;
