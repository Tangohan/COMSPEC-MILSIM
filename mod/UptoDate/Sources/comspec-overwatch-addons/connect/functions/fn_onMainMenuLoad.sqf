/*
    Hook menu principal Arma (RscDisplayMain) — bandeau bêta + note de service (licence) au premier lancement.
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

// Licence pas encore acceptée : la note revient à chaque retour au menu principal.
if (!(([] call comspec_overwatch_connect_fnc_licenceStatus) select 1)) then {
    uiNamespace setVariable ["COMSPEC_MainMenuBetaBooted", false];
    missionNamespace setVariable ["COMSPEC_BetaAccessNoteShown", false, false];
};
if (uiNamespace getVariable ["COMSPEC_MainMenuBetaBooted", false]) exitWith {};
uiNamespace setVariable ["COMSPEC_MainMenuBetaBooted", true];

0 spawn {
    uiSleep 1.2;
    // Note de service (licence + bêta) : une fois le service Athéna choisi (sinon après le « Oui » / « Non »).
    private _t0 = diag_tickTime;
    waitUntil { uiSleep 0.5; (([] call comspec_overwatch_connect_fnc_athenaMode) isNotEqualTo "") || {diag_tickTime > (_t0 + 180)} };
    if (
        (([] call comspec_overwatch_connect_fnc_athenaMode) isEqualTo "on")
        && {missionNamespace getVariable ["comspec_overwatch_enabled", true]}
    ) then {
        "COMSPECExtension" callExtension "Warmup";
        uiSleep 0.25;
    };
    [] call comspec_overwatch_connect_fnc_showBetaAccessNote;
};
