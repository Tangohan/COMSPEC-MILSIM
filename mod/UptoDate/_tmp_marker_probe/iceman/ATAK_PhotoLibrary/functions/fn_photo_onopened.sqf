params ["_group", ["_interfaceInit", false], "_isDialog", "_settings"];

if (isNull _group) exitWith {};

uiNamespace setVariable ["Iceman_ATAK_PhotoLibrary_group", _group];
missionNamespace setVariable ["Iceman_PhotoLibrary_expanded", false];
missionNamespace setVariable ["Iceman_PhotoLibrary_deleteConfirm", ""];

call Iceman_fnc_photo_refresh;
