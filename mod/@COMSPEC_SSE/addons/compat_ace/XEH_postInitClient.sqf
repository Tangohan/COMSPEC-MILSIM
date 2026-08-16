/*
    Post-init client : hooks ACE dogtags si présents.
*/
if (!hasInterface) exitWith {};

diag_log "[SSE][POSTINIT][compat_ace] BEGIN";

private _aceCompatEnabled = true;
if (!isNil "comspec_debug_fnc_isModuleEnabled") then {
    _aceCompatEnabled = ["compat_ace"] call comspec_debug_fnc_isModuleEnabled;
} else {
    _aceCompatEnabled = !(missionNamespace getVariable ["COMSPEC_DEBUG_DISABLE_COMPAT_ACE", false]);
};
if (!_aceCompatEnabled) exitWith {
    if (!isNil "comspec_debug_fnc_log") then {
        ["WARN", "Boot", "ISOLATION", "Compat ACE disabled"] call comspec_debug_fnc_log;
    };
    diag_log "[COMSPEC SSE][DEBUG] Compat ACE disabled";
    diag_log "[SSE][POSTINIT][compat_ace] END (disabled)";
};

[] spawn {
    uiSleep 1;
    if !(missionNamespace getVariable ["comspec_sse_aceDogtagBridgeEnabled", true]) exitWith {
        diag_log "[SSE][POSTINIT][compat_ace] END (bridge off)";
    };
    if !([] call comspec_sse_fnc_aceDogtagIsPresent) exitWith {
        ["ACE dogtags absent — passerelle plaque inactive."] call comspec_sse_fnc_log;
        diag_log "[SSE][POSTINIT][compat_ace] END (no dogtags)";
    };

    [] call comspec_sse_fnc_aceDogtagInstallHooks;
    comspec_sse_aceDogtagBridgeReady = true;
    ["Passerelle ACE plaque d’identification → SSE active."] call comspec_sse_fnc_log;
    diag_log "[SSE][POSTINIT][compat_ace] END";
};
