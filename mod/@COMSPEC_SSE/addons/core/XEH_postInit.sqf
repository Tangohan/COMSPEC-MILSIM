#include "script_component.hpp"

if (isServer) then {
    if (isNil "comspec_sse_missionRecords") then {
        comspec_sse_missionRecords = [];
        publicVariable "comspec_sse_missionRecords";
    };
};

["COMSPEC SSE core postInit OK"] call comspec_sse_fnc_log;
