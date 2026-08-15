#include "..\script_component.hpp"

params ["_group", ["_interfaceInit", false], "_isDialog", "_settings"];

private _display = ctrlParent _group;
uiNamespace setVariable ["Iceman_ATAK_Jump_group", _group];
[_display] call Iceman_fnc_jump_installMapHandlers;
[_group] call Iceman_fnc_jump_initPage;
call Iceman_fnc_jump_updatePanel;
