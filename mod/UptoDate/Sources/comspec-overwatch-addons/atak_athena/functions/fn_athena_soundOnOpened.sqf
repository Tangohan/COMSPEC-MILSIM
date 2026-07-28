/*
    Ouverture de l’app Sons ATAK dans cTab (pattern ATAK_APPs Opened).
*/
params ["_group", ["_interfaceInit", false], "_isDialog", "_settings"];

if (isNull _group) exitWith {};

uiNamespace setVariable ["COMSPEC_ATAK_Sound_group", _group];
[] call comspec_overwatch_atak_athena_fnc_athena_updateSound;
