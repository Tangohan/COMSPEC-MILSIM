/*
    Hook menu principal Arma (RscDisplayMain) — NDA accès anticipé + enregistrement.
*/
if (!hasInterface) exitWith {};
if (uiNamespace getVariable ["COMSPEC_MainMenuBetaBooted", false]) exitWith {};
uiNamespace setVariable ["COMSPEC_MainMenuBetaBooted", true];

0 spawn {
    // Laisser le menu et l’extension se stabiliser avant le dialogue natif
    uiSleep 1.5;
    if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
    "COMSPECExtension" callExtension "Warmup";
    uiSleep 0.35;
    [] call comspec_overwatch_connect_fnc_showBetaAccessNote;
};
