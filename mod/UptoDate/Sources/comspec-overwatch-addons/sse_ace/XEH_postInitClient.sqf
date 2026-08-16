if (!hasInterface) exitWith {};

if (!isNil "comspec_debug_fnc_log") then {
    ["DEBUG", "POSTINIT", "BEGIN", "overwatch_sse_ace"] call comspec_debug_fnc_log;
} else {
    diag_log "[SSE][POSTINIT][overwatch_sse_ace] BEGIN";
};

private _enabled = true;
if (!isNil "comspec_debug_fnc_isModuleEnabled") then {
    _enabled = ["overwatch_sse_ace"] call comspec_debug_fnc_isModuleEnabled;
} else {
    _enabled = !(missionNamespace getVariable ["COMSPEC_DEBUG_DISABLE_OVERWATCH_SSE_ACE", false]);
};

if (!_enabled) exitWith {
    if (!isNil "comspec_debug_fnc_log") then {
        ["WARN", "Boot", "ISOLATION", "Overwatch SSE ACE disabled"] call comspec_debug_fnc_log;
        ["DEBUG", "POSTINIT", "END", "overwatch_sse_ace (disabled)"] call comspec_debug_fnc_log;
    } else {
        diag_log "[COMSPEC Overwatch][DEBUG] sse_ace disabled";
        diag_log "[SSE][POSTINIT][overwatch_sse_ace] END (disabled)";
    };
};

// Le joueur n’est pas toujours prêt au postInit : même report que fn_initACE.
if (isNull player) exitWith {
    [{
        try {
            [] call comspec_overwatch_sse_ace_fnc_initSseAce;
        } catch {
            if (!isNil "comspec_debug_fnc_exception") then {
                ["comspec_overwatch_sse_ace_fnc_initSseAce", _exception, "postInit delayed"] call comspec_debug_fnc_exception;
            };
        };
        if (!isNil "comspec_debug_fnc_log") then {
            ["DEBUG", "POSTINIT", "END", "overwatch_sse_ace (delayed)"] call comspec_debug_fnc_log;
        } else {
            diag_log "[SSE][POSTINIT][overwatch_sse_ace] END (delayed)";
        };
    }, [], 2] call CBA_fnc_waitAndExecute;
};

try {
    [] call comspec_overwatch_sse_ace_fnc_initSseAce;
} catch {
    if (!isNil "comspec_debug_fnc_exception") then {
        ["comspec_overwatch_sse_ace_fnc_initSseAce", _exception, "postInit"] call comspec_debug_fnc_exception;
    };
};

if (!isNil "comspec_debug_fnc_log") then {
    ["DEBUG", "POSTINIT", "END", "overwatch_sse_ace"] call comspec_debug_fnc_log;
} else {
    diag_log "[SSE][POSTINIT][overwatch_sse_ace] END";
};
