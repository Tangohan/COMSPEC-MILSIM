if (!hasInterface) exitWith {};

["DEBUG", "POSTINIT", "BEGIN", "digital"] call comspec_debug_fnc_log;

if !(["digital"] call comspec_debug_fnc_isModuleEnabled) exitWith {
    ["WARN", "Boot", "ISOLATION", "SSE Digital disabled by debug isolation"] call comspec_debug_fnc_log;
    ["DEBUG", "POSTINIT", "END", "digital (disabled)"] call comspec_debug_fnc_log;
};

try {
    [] call comspec_sse_fnc_initDigitalACE;
} catch {
    ["comspec_sse_fnc_initDigitalACE", _exception, "XEH_postInitClient digital"] call comspec_debug_fnc_exception;
};

["DEBUG", "POSTINIT", "END", "digital"] call comspec_debug_fnc_log;
