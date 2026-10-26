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

// Fiche modifiée : la fiche sérialisée du pivot (comspec_sse_fnc_pivotSearch) est à refaire, et ses liens
// déjà annoncés peuvent l'être de nouveau (données régénérées).
["comspec_sse_recordChanged", {
    params [["_ent", objNull, [objNull]]];
    if (isNull _ent) exitWith {};
    _ent setVariable ["comspec_sse_pivotBlob", nil];
    private _emitted = missionNamespace getVariable ["comspec_sse_pivotEmitted", createHashMap];
    private _nid = netId _ent;
    { if (_nid in (_x splitString "|")) then { _emitted deleteAt _x; }; } forEach (keys _emitted);
}] call CBA_fnc_addEventHandler;

// Nouvelle entité SSE (génération Zeus, module) : la liste des candidats du pivot est à refaire.
["comspec_sse_entityEnabled", {
    missionNamespace setVariable ["comspec_sse_pivotCandidates", nil];
}] call CBA_fnc_addEventHandler;

// Registre des découvertes tenu par le serveur (les clients y proposent leurs découvertes).
if (isServer) then {
    // Hook Zeus d'un renseignement exploité sur un poste client (hooks enregistrés côté serveur).
    ["comspec_sse_fireZeusHook", {
        params [["_intelId", "", [""]], ["_entity", objNull, [objNull]], ["_datum", createHashMap, [createHashMap]]];
        [_intelId, _entity, _datum] call comspec_sse_fnc_fireZeusHook;
    }] call CBA_fnc_addEventHandler;

    ["comspec_sse_discoveryState", {
        params ["_id", "_state", ["_ttl", -1]];
        [_id, _state, createHashMapFromArray [["ttl", _ttl]]] call comspec_sse_fnc_setDiscoveryState;
    }] call CBA_fnc_addEventHandler;

    // Échéances des intel périssables (serveur seul).
    [{ [] call comspec_sse_fnc_checkTimeSensitivity; }, 30] call CBA_fnc_addPerFrameHandler;
};

["intel postInit OK"] call comspec_sse_fnc_log;
diag_log "[SSE][POSTINIT][intel] END";
