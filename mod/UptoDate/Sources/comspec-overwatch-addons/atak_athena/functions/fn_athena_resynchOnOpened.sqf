/*
    Ouverture de l’app Resynch dans cTab (pattern ATAK_APPs Opened).
    Affiche l’écran puis relance toutes les données vers Athena.
*/
params ["_group", ["_interfaceInit", false], "_isDialog", "_settings"];

if (isNull _group) exitWith {};
uiNamespace setVariable ["COMSPEC_ATAK_Resynch_group", _group];
["resynch"] call comspec_overwatch_atak_athena_fnc_athena_hideForeignPages;

[] call comspec_overwatch_atak_athena_fnc_athena_resynchAll;
