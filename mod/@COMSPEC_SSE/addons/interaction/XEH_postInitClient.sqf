if (!hasInterface) exitWith {};

["DEBUG", "POSTINIT", "BEGIN", "interaction"] call comspec_debug_fnc_log;

if !(["interaction"] call comspec_debug_fnc_isModuleEnabled) exitWith {
    ["WARN", "Boot", "ISOLATION", "SSE ACE / Interaction disabled by debug isolation"] call comspec_debug_fnc_log;
    ["DEBUG", "POSTINIT", "END", "interaction (disabled)"] call comspec_debug_fnc_log;
};

try {
    [] call comspec_sse_fnc_initACE;
} catch {
    ["comspec_sse_fnc_initACE", _exception, "XEH_postInitClient interaction"] call comspec_debug_fnc_exception;
};

["DEBUG", "POSTINIT", "END", "interaction"] call comspec_debug_fnc_log;
