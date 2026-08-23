/*
    Ouverture de l’app Paramètres dans cTab.
*/
params ["_group", ["_interfaceInit", false], "_isDialog", "_settings"];

if (isNull _group) exitWith {};

uiNamespace setVariable ["COMSPEC_ATAK_Settings_group", _group];

0 spawn {
    if (!isNil "comspec_overwatch_connect_fnc_getFireTeams") then {
        [] call comspec_overwatch_connect_fnc_getFireTeams;
    };
    [] call comspec_overwatch_atak_athena_fnc_athena_updateSettings;
};

[] call comspec_overwatch_atak_athena_fnc_athena_updateSettings;
