/*
    Ouverture de l’app « RENS » dans le tiroir ATAK.

    L’accueil du tiroir s’affiche d’abord. Le rédacteur plein cadre s’ouvre
    ensuite, une fois le téléphone calé — trop tôt, le téléphone le referme.
*/
params ["_group", ["_interfaceInit", false], "_isDialog", "_settings"];

if (isNull _group) exitWith {};

uiNamespace setVariable ["COMSPEC_ATAK_Note_group", _group];
["note"] call comspec_overwatch_atak_athena_fnc_athena_hideForeignPages;

[] call comspec_overwatch_atak_athena_fnc_athena_updateNote;

if (!isNull (findDisplay 9982)) exitWith {};

[] spawn {
    uiSleep 0.45;
    ["note"] call comspec_overwatch_atak_athena_fnc_athena_hideForeignPages;
    if (isNull (uiNamespace getVariable ["cTab_Android_dlg", displayNull])) exitWith {};
    if (!isNull (findDisplay 9982)) exitWith {};
    [""] call comspec_overwatch_atak_athena_fnc_athena_openNote;
};
