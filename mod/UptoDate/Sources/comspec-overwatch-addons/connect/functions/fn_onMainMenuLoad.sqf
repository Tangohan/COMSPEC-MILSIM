/*
    Hook menu principal Arma (RscDisplayMain) — bandeau bêta + note au premier lancement.
    Recréé à chaque ouverture du menu (le display natif est détruit/recréé).
*/
params [["_display", displayNull]];

if (!hasInterface) exitWith {};
if (isNull _display) then { _display = findDisplay 0; };
if (isNull _display) exitWith {};

[_display] call comspec_overwatch_connect_fnc_decorateMainMenu;

// Premier lancement : « Votre unité utilise-t-elle le service Athéna ? » avant toute connexion.
// Oui : fn_athenaApply lance la restauration de session. Non : rien ne part vers Athena.
0 spawn {
    uiSleep 0.6;
    if (([] call comspec_overwatch_connect_fnc_athenaMode) isEqualTo "") exitWith {
        [] call comspec_overwatch_connect_fnc_athenaChoiceShow;
    };
    if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
    [] call comspec_overwatch_connect_fnc_initAuth;
};

if (uiNamespace getVariable ["COMSPEC_MainMenuBetaBooted", false]) exitWith {};
uiNamespace setVariable ["COMSPEC_MainMenuBetaBooted", true];

0 spawn {
    uiSleep 1.2;
    if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
    // Note bêta : seulement une fois le service Athéna choisi (sinon après le « Oui »).
    waitUntil { uiSleep 0.5; ([] call comspec_overwatch_connect_fnc_athenaMode) isNotEqualTo "" };
    if (([] call comspec_overwatch_connect_fnc_athenaMode) isNotEqualTo "on") exitWith {};
    "COMSPECExtension" callExtension "Warmup";
    uiSleep 0.25;
    [] call comspec_overwatch_connect_fnc_showBetaAccessNote;
};
