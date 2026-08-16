if (!hasInterface) exitWith {};

["DEBUG", "POSTINIT", "BEGIN", "biometrics"] call comspec_debug_fnc_log;

if !(["biometrics"] call comspec_debug_fnc_isModuleEnabled) exitWith {
    ["WARN", "Boot", "ISOLATION", "SSE Biometrics disabled by debug isolation"] call comspec_debug_fnc_log;
    ["DEBUG", "POSTINIT", "END", "biometrics (disabled)"] call comspec_debug_fnc_log;
};

try {
    [] call comspec_sse_fnc_initBiometricsACE;
} catch {
    ["comspec_sse_fnc_initBiometricsACE", _exception, "XEH_postInitClient biometrics"] call comspec_debug_fnc_exception;
};

["DEBUG", "POSTINIT", "END", "biometrics"] call comspec_debug_fnc_log;
