/*
    Ouvre l’écran Connexion Athena (code / Steam + barre de transmission).
    Avec l’ATAK natif : jamais l’ancienne fenêtre « Lier le jeu à Athena » ; le téléphone s’ouvre sur l’app Athena
    (code à autoriser sur athena.ttrd.fr/lier). Au lancement de la mission, rien ne s’ouvre sans geste du joueur.
    Params : [demandé par le joueur (menu, bouton) : true]
*/
params [["_user", false]];
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
if (!isNil "comspec_atak_native_fnc_athenaLink") exitWith {
    ["show", _user] call comspec_atak_native_fnc_athenaLink;
};

if (!isNil "comspec_overwatch_atak_athena_fnc_athena_showLinkDialog") then {
    [] call comspec_overwatch_atak_athena_fnc_athena_showLinkDialog;
} else {
    if (!isNull (uiNamespace getVariable ["COMSPEC_AccountLink_Display", displayNull])) exitWith {};
    createDialog "COMSPEC_AccountLink_Dialog";
};

