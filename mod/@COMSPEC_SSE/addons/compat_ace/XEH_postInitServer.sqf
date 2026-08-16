/*
    Post-init serveur : mêmes hooks (getDogtagData peut être appelé côté serveur pour body bag).
*/
if (!isServer) exitWith {};

[] spawn {
    uiSleep 1;
    if !(missionNamespace getVariable ["comspec_sse_aceDogtagBridgeEnabled", true]) exitWith {};
    if !([] call comspec_sse_fnc_aceDogtagIsPresent) exitWith {};

    [] call comspec_sse_fnc_aceDogtagInstallHooks;
    comspec_sse_aceDogtagBridgeReady = true;
};
