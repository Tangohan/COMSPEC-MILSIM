/*
    Ouverture de l’app Tutoriel / WIKI dans cTab.
*/
params ["_group", ["_interfaceInit", false], "_isDialog", "_settings"];

if (isNull _group) exitWith {};
uiNamespace setVariable ["COMSPEC_ATAK_Wiki_group", _group];
["wiki"] call comspec_overwatch_atak_athena_fnc_athena_hideForeignPages;
[] call comspec_overwatch_atak_athena_fnc_athena_updateWiki;
