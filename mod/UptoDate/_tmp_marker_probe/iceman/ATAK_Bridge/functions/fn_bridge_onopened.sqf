params ["_group", ["_interfaceInit", false], "_isDialog", "_settings"];

uiNamespace setVariable ["Iceman_ATAK_Bridge_group", _group];
call Iceman_fnc_bridge_applymonitors;
call Iceman_fnc_bridge_updatepanel;

[{
    call Iceman_fnc_bridge_updatepanel;
}, 0.05] call CBA_fnc_waitAndExecute;
