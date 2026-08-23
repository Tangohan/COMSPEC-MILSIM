#include "script_component.hpp"

diag_log "[SSE][POSTINIT][intel] BEGIN";

if (hasInterface) then {
    [] call comspec_sse_fnc_createMapMarkers;

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
diag_log "[SSE][POSTINIT][intel] END";
