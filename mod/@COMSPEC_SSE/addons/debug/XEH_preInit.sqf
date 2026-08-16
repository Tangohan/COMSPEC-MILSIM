#include "script_component.hpp"

if !(["COMSPEC_DEBUG_PREINIT_DONE", "XEH_preInit debug"] call comspec_debug_fnc_guardOnce) exitWith {};

["comspec_debug_XEH_preInit", []] call comspec_debug_fnc_enter;

[] call comspec_debug_fnc_initSettings;

missionNamespace setVariable ["COMSPEC_DEBUG_CALL_DEPTH", 0];
missionNamespace setVariable ["COMSPEC_DEBUG_CALL_STACK", []];
missionNamespace setVariable ["COMSPEC_DEBUG_ACE_ACTIONS", createHashMap];
missionNamespace setVariable ["COMSPEC_DEBUG_ACE_BY_CLASS", createHashMap];
missionNamespace setVariable ["COMSPEC_DEBUG_ACE_TOTAL", 0];
missionNamespace setVariable ["COMSPEC_DEBUG_ACE_INHERITED", 0];
missionNamespace setVariable ["COMSPEC_DEBUG_ACE_DUPLICATES", 0];
missionNamespace setVariable ["COMSPEC_DEBUG_EH_REGISTRY", createHashMap];
missionNamespace setVariable ["COMSPEC_DEBUG_BREADCRUMBS", []];

if ([] call comspec_debug_fnc_isSafeMode) then {
    ["WARN", "SAFE MODE", "ACTIVE", "COMSPEC Overwatch/SSE running in diagnostic safe mode"] call comspec_debug_fnc_log;
    missionNamespace setVariable ["COMSPEC_DEBUG_ENABLE_SSE_ACE", false];
    missionNamespace setVariable ["COMSPEC_DEBUG_ENABLE_SSE_DIGITAL", false];
    missionNamespace setVariable ["COMSPEC_DEBUG_ENABLE_BIOMETRICS", false];
    missionNamespace setVariable ["COMSPEC_DEBUG_ENABLE_SSE_ZEUS", false];
    missionNamespace setVariable ["COMSPEC_DEBUG_ENABLE_MARKERS", false];
    missionNamespace setVariable ["COMSPEC_DEBUG_ENABLE_ATAK", false];
    missionNamespace setVariable ["COMSPEC_DEBUG_DISABLE_INTERACTION", true];
    missionNamespace setVariable ["COMSPEC_DEBUG_DISABLE_DIGITAL", true];
    missionNamespace setVariable ["COMSPEC_DEBUG_DISABLE_BIOMETRICS", true];
    missionNamespace setVariable ["COMSPEC_DEBUG_DISABLE_COMPAT_ACE", true];
    missionNamespace setVariable ["COMSPEC_DEBUG_DISABLE_COMPAT_BII", true];
    missionNamespace setVariable ["COMSPEC_DEBUG_DISABLE_OVERWATCH_SSE_ACE", true];
};

["INFO", "Boot", "PREINIT", "Debug instrumentation ready"] call comspec_debug_fnc_log;
["comspec_debug_XEH_preInit"] call comspec_debug_fnc_exit;
