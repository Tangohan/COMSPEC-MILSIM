#include "script_component.hpp"

if (isNil "comspec_sse_logicalEntities") then {
    comspec_sse_logicalEntities = createHashMap;
};
if (isNil "comspec_sse_actionHistory") then {
    comspec_sse_actionHistory = [];
};
if (isNil "comspec_sse_zeusHooks") then {
    comspec_sse_zeusHooks = createHashMap;
};
if (isNil "comspec_sse_modClassRegistry") then {
    comspec_sse_modClassRegistry = createHashMap;
};
if (isNil "comspec_sse_discoveryStates") then {
    comspec_sse_discoveryStates = createHashMap;
};
if (isNil "comspec_sse_biometricIndex") then {
    comspec_sse_biometricIndex = createHashMap;
};
if (isNil "comspec_sse_missionGraph") then {
    comspec_sse_missionGraph = createHashMapFromArray [
        ["entities", []],
        ["links", []],
        ["logical", []],
        ["exportedAt", 0]
    ];
};

[] call comspec_sse_fnc_initIntelSettings;
["intel preInit OK"] call comspec_sse_fnc_log;
