#include "script_component.hpp"

[] call comspec_sse_fnc_ensureDebugApi;

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
    // Graine maîtresse tirée (ou lue) et diffusée tôt pour que les clients génèrent comme le serveur.
    [] call comspec_sse_fnc_missionSeed;

    // Greffier : propositions de fiche et opérations atomiques venant des clients.
    ["comspec_sse_recordCommit", {
        params ["_entity", "_data", ["_owner", -1]];
        if (isNull _entity || {!(_data isEqualType [])}) exitWith {};
        [_entity, _data, _owner, true] call comspec_sse_fnc_commitData;
    }] call CBA_fnc_addEventHandler;
    ["comspec_sse_link", {
        _this call comspec_sse_fnc_linkEntities;
    }] call CBA_fnc_addEventHandler;
    ["comspec_sse_recordOp", {
        params ["_op", "_entity", ["_args", []]];
        [_op, _entity, _args] call comspec_sse_fnc_recordOp;
    }] call CBA_fnc_addEventHandler;
    [
        "COMSPEC_SSE_REQUEST_OP",
        "CBA",
        "comspec_sse_requestOp",
        {
            // Requête réseau : acteur obligatoire (requestServerOp envoie toujours player). objNull contournait
            // les contrôles de proximité et « generate réservé à Zeus » ; seul l'appel direct serveur l'admet.
            if (isNull (_this param [1, objNull, [objNull]])) exitWith {
                [format ["serverOp %1 refusée : acteur absent (requête réseau)", _this param [0, ""]], "WARN"] call comspec_sse_fnc_log;
                false
            };
            _this call comspec_sse_fnc_serverHandleOp;
        }
    ] call comspec_debug_fnc_registerEventHandler;
};

["INFO", "Boot", "PREINIT", "COMSPEC SSE core preInit OK"] call comspec_debug_fnc_log;
["comspec_sse_core_XEH_preInit"] call comspec_debug_fnc_exit;
