/*
    Post-init client : enregistre matériel + hooks BII si présents.
*/
if (!hasInterface) exitWith {};

diag_log "[SSE][POSTINIT][compat_bii] BEGIN";

private _biiEnabled = true;
if (!isNil "comspec_debug_fnc_isModuleEnabled") then {
    _biiEnabled = ["compat_bii"] call comspec_debug_fnc_isModuleEnabled;
} else {
    _biiEnabled = !(missionNamespace getVariable ["COMSPEC_DEBUG_DISABLE_COMPAT_BII", false]);
};
if (!_biiEnabled) exitWith {
    if (!isNil "comspec_debug_fnc_log") then {
        ["WARN", "Boot", "ISOLATION", "Compat BII disabled"] call comspec_debug_fnc_log;
    };
    diag_log "[COMSPEC SSE][DEBUG] Compat BII disabled";
    diag_log "[SSE][POSTINIT][compat_bii] END (disabled)";
};

[] spawn {
    // Laisser BII / cTab finir leur postInit
    uiSleep 1.5;
    if !(missionNamespace getVariable ["comspec_sse_biiBridgeEnabled", true]) exitWith {
        diag_log "[SSE][POSTINIT][compat_bii] END (bridge off)";
    };
    if !([] call comspec_sse_fnc_biiIsPresent) exitWith {
        ["BII Identifi absent - passerelle inactive."] call comspec_sse_fnc_log;
        diag_log "[SSE][POSTINIT][compat_bii] END (no BII)";
    };

    try {
        if (!isNil "comspec_sse_fnc_biiRegisterEquipment") then {
            [] call comspec_sse_fnc_biiRegisterEquipment;
        };
        if (!isNil "comspec_sse_fnc_biiInstallHooks") then {
            [] call comspec_sse_fnc_biiInstallHooks;
        };
        comspec_sse_biiBridgeReady = true;
        ["Passerelle BII Identifi active."] call comspec_sse_fnc_log;
        diag_log "[SSE][POSTINIT][compat_bii] END";
    } catch {
        private _err = if (!isNil "_exception") then { str _exception } else { "unknown" };
        if (!isNil "comspec_debug_fnc_exception") then {
            ["comspec_sse_fnc_biiInstallHooks", _err, "compat_bii postInit"] call comspec_debug_fnc_exception;
        };
        [format ["Passerelle BII Identifi: echec postInit client (%1)", _err], "ERROR"] call comspec_sse_fnc_log;
        comspec_sse_biiBridgeReady = false;
        diag_log format ["[SSE][POSTINIT][compat_bii] END (error %1)", _err];
    };
};
