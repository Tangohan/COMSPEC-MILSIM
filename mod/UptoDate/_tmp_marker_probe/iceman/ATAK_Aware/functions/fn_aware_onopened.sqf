params ["_group", ["_interfaceInit", false], "_isDialog", "_settings"];

if (isNull _group) exitWith {};
uiNamespace setVariable ["Iceman_ATAK_Aware_group", _group];

call Iceman_fnc_aware_install;
call Iceman_fnc_aware_updatePanel;
