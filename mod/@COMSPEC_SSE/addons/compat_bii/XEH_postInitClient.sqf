/*
    Post-init client : enregistre matériel + hooks BII si présents.
*/
if (!hasInterface) exitWith {};

[] spawn {
    // Laisser BII / cTab finir leur postInit
    uiSleep 1.5;
    if !(missionNamespace getVariable ["comspec_sse_biiBridgeEnabled", true]) exitWith {};
    if !([] call comspec_sse_fnc_biiIsPresent) exitWith {
        ["BII Identifi absent — passerelle inactive."] call comspec_sse_fnc_log;
    };

    [] call comspec_sse_fnc_biiRegisterEquipment;
    [] call comspec_sse_fnc_biiInstallHooks;
    comspec_sse_biiBridgeReady = true;
    ["Passerelle BII Identifi active."] call comspec_sse_fnc_log;
};
