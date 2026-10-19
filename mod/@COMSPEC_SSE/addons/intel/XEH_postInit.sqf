#include "script_component.hpp"

diag_log "[SSE][POSTINIT][intel] BEGIN";

if (hasInterface) then {
    [] call comspec_sse_fnc_createMapMarkers;

    // Mode entraînement : feedback après actions via event
    ["comspec_sse_recordExploited", {
        params ["_entity", "_level", ["_origin", -1]];
        // Événement global : le retour d'entraînement ne concerne que le joueur qui a agi.
        if (_origin isEqualTo clientOwner && {missionNamespace getVariable ["comspec_sse_trainingMode", false]}) then {
            [_entity, _level] call comspec_sse_fnc_trainingFeedback;
        };
    }] call CBA_fnc_addEventHandler;
};

// Registre des découvertes tenu par le serveur (les clients y proposent leurs découvertes).
if (isServer) then {
    ["comspec_sse_discoveryState", {
        params ["_id", "_state", ["_ttl", -1]];
        [_id, _state, createHashMapFromArray [["ttl", _ttl]]] call comspec_sse_fnc_setDiscoveryState;
    }] call CBA_fnc_addEventHandler;

    // Échéances des intel périssables (serveur seul).
    [{ [] call comspec_sse_fnc_checkTimeSensitivity; }, 30] call CBA_fnc_addPerFrameHandler;
};

["intel postInit OK"] call comspec_sse_fnc_log;
diag_log "[SSE][POSTINIT][intel] END";
