#include "script_component.hpp"

if !(["COMSPEC_SSE_CORE_PREINIT_DONE", "XEH_preInit core"] call comspec_debug_fnc_guardOnce) exitWith {};

["comspec_sse_core_XEH_preInit", []] call comspec_debug_fnc_enter;

if !(["core"] call comspec_debug_fnc_isModuleEnabled) exitWith {
    ["WARN", "Boot", "ISOLATION", "SSE Core disabled by debug isolation"] call comspec_debug_fnc_log;
    ["comspec_sse_core_XEH_preInit"] call comspec_debug_fnc_exit;
};

[] call comspec_sse_fnc_initSettings;

if (isNil "comspec_sse_uidCounter") then {
    comspec_sse_uidCounter = 0;
};

if (isNil "comspec_sse_linkGraph") then {
    comspec_sse_linkGraph = [];
};

if (isServer) then {
    if (isNil "comspec_sse_serverQueue") then {
        comspec_sse_serverQueue = [];
    };
    [
        "COMSPEC_SSE_REQUEST_OP",
        "CBA",
        "comspec_sse_requestOp",
        { _this call comspec_sse_fnc_serverHandleOp; }
    ] call comspec_debug_fnc_registerEventHandler;
};

["INFO", "Boot", "PREINIT", "COMSPEC SSE core preInit OK"] call comspec_debug_fnc_log;
["comspec_sse_core_XEH_preInit"] call comspec_debug_fnc_exit;
