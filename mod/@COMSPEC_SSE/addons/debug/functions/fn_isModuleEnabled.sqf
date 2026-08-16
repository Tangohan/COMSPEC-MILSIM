/*
    Isolation par module.

    Keys: core | ace | digital | zeus | markers | atak | biometrics | interaction | compat_ace | compat_bii | overwatch_sse_ace
*/
params [
    ["_module", "", [""]]
];

_module = toLower _module;

if ([] call comspec_debug_fnc_isSafeMode) exitWith {
    _module in ["core", "debug", "logger"]
};

private _map = createHashMapFromArray [
    ["core", "COMSPEC_DEBUG_ENABLE_SSE_CORE"],
    ["ace", "COMSPEC_DEBUG_ENABLE_SSE_ACE"],
    ["interaction", "COMSPEC_DEBUG_ENABLE_SSE_ACE"],
    ["digital", "COMSPEC_DEBUG_ENABLE_SSE_DIGITAL"],
    ["zeus", "COMSPEC_DEBUG_ENABLE_SSE_ZEUS"],
    ["markers", "COMSPEC_DEBUG_ENABLE_MARKERS"],
    ["atak", "COMSPEC_DEBUG_ENABLE_ATAK"],
    ["biometrics", "COMSPEC_DEBUG_ENABLE_BIOMETRICS"],
    ["compat_ace", "COMSPEC_DEBUG_ENABLE_COMPAT_ACE"],
    ["compat_bii", "COMSPEC_DEBUG_ENABLE_COMPAT_BII"],
    ["overwatch_sse_ace", "COMSPEC_DEBUG_ENABLE_OVERWATCH_SSE_ACE"]
];

private _var = _map getOrDefault [_module, ""];
if (_var isEqualTo "") exitWith { true };

private _disableMap = createHashMapFromArray [
    ["interaction", "COMSPEC_DEBUG_DISABLE_INTERACTION"],
    ["ace", "COMSPEC_DEBUG_DISABLE_INTERACTION"],
    ["digital", "COMSPEC_DEBUG_DISABLE_DIGITAL"],
    ["biometrics", "COMSPEC_DEBUG_DISABLE_BIOMETRICS"],
    ["compat_ace", "COMSPEC_DEBUG_DISABLE_COMPAT_ACE"],
    ["compat_bii", "COMSPEC_DEBUG_DISABLE_COMPAT_BII"],
    ["overwatch_sse_ace", "COMSPEC_DEBUG_DISABLE_OVERWATCH_SSE_ACE"]
];
private _dis = _disableMap getOrDefault [_module, ""];
if (_dis isNotEqualTo "" && {missionNamespace getVariable [_dis, false]}) exitWith { false };

missionNamespace getVariable [_var, true]
