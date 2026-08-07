#include "script_component.hpp"

if (hasInterface) then {
    // Mode entraînement : feedback après actions via event
    ["comspec_sse_recordExploited", {
        params ["_entity", "_level"];
        if (missionNamespace getVariable ["comspec_sse_trainingMode", false]) then {
            [_entity, _level] call comspec_sse_fnc_trainingFeedback;
        };
    }] call CBA_fnc_addEventHandler;
};

// Flush time-sensitive intel périodique
[{
    if (!isServer && {!hasInterface}) exitWith {};
    [] call comspec_sse_fnc_checkTimeSensitivity;
}, 30] call CBA_fnc_addPerFrameHandler;

["intel postInit OK"] call comspec_sse_fnc_log;
