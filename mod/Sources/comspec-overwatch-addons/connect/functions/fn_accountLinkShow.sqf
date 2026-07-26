/*
    Ouvre l’écran Connexion Athena (code / Steam + barre de transmission).
*/
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

if (!isNil "comspec_overwatch_atak_athena_fnc_athena_showLinkDialog") then {
    [] call comspec_overwatch_atak_athena_fnc_athena_showLinkDialog;
} else {
    if (!isNull (uiNamespace getVariable ["COMSPEC_AccountLink_Display", displayNull])) exitWith {};
    createDialog "COMSPEC_AccountLink_Dialog";
};
