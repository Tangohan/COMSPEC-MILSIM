/*
    Ouvre la liaison mobile (adresse + code) en dialog Rsc natif.
    Plus d’injection dans tablet.html / CT_WEBBROWSER depuis ce chemin.
*/
params [["_forceOpen", true, [true]]];

if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

if (!_forceOpen) exitWith {};

if (!isNil "comspec_overwatch_atak_athena_fnc_athena_showPhoneConnect") then {
    [] call comspec_overwatch_atak_athena_fnc_athena_showPhoneConnect;
} else {
    if (!isNull (uiNamespace getVariable ["COMSPEC_PhoneConnect_Display", displayNull])) exitWith {};
    createDialog "COMSPEC_PhoneConnect_Dialog";
};
