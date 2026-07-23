/*
    Hook menu principal Arma (RscDisplayMain) — note d’accès anticipé + enregistrement.
*/
if (!hasInterface) exitWith {};
if (uiNamespace getVariable ["COMSPEC_MainMenuBetaBooted", false]) exitWith {};
uiNamespace setVariable ["COMSPEC_MainMenuBetaBooted", true];

0 spawn {
    // Laisser le menu et la DLL se stabiliser avant MessageBox
    uiSleep 1.8;
    if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
    "COMSPECExtension" callExtension "Warmup";
    uiSleep 0.4;
    [] call comspec_overwatch_connect_fnc_showBetaAccessNote;
};
