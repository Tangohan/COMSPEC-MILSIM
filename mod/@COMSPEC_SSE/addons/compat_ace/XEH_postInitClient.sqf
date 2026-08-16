/*
    Post-init client : hooks ACE dogtags si présents.
*/
if (!hasInterface) exitWith {};

[] spawn {
    uiSleep 1;
    if !(missionNamespace getVariable ["comspec_sse_aceDogtagBridgeEnabled", true]) exitWith {};
    if !([] call comspec_sse_fnc_aceDogtagIsPresent) exitWith {
        ["ACE dogtags absent — passerelle plaque inactive."] call comspec_sse_fnc_log;
    };

    [] call comspec_sse_fnc_aceDogtagInstallHooks;
    comspec_sse_aceDogtagBridgeReady = true;
    ["Passerelle ACE plaque d’identification → SSE active."] call comspec_sse_fnc_log;
};
