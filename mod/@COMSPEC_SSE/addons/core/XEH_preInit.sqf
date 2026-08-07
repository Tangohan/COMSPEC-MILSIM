#include "script_component.hpp"

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
    ["comspec_sse_requestOp", {
        _this call comspec_sse_fnc_serverHandleOp;
    }] call CBA_fnc_addEventHandler;
};

["COMSPEC SSE core preInit OK"] call comspec_sse_fnc_log;
