#include "script_component.hpp"

if !(["COMSPEC_SSE_CORE_POSTINIT_DONE", "XEH_postInit core"] call comspec_debug_fnc_guardOnce) exitWith {};

["comspec_sse_core_XEH_postInit", []] call comspec_debug_fnc_enter;

if !(["core"] call comspec_debug_fnc_isModuleEnabled) exitWith {
    ["WARN", "Boot", "ISOLATION", "SSE Core postInit skipped"] call comspec_debug_fnc_log;
    ["comspec_sse_core_XEH_postInit"] call comspec_debug_fnc_exit;
};

if (isServer) then {
    if (isNil "comspec_sse_missionRecords") then {
        comspec_sse_missionRecords = [];
        publicVariable "comspec_sse_missionRecords";
    };
};

try {
    [] call comspec_sse_fnc_onSseEvent;
} catch {
    ["comspec_sse_fnc_onSseEvent", _exception, "core postInit"] call comspec_debug_fnc_exception;
};

["INFO", "Boot", "POSTINIT", "COMSPEC SSE core postInit OK"] call comspec_debug_fnc_log;
["comspec_sse_core_XEH_postInit"] call comspec_debug_fnc_exit;
