#include "script_component.hpp"

// Renseignements live DOMEX : la liste publique de l'objet n'est écrite que par le serveur (fn_domexAddLivePacket).
if (isServer) then {
    ["comspec_sse_domexLiveAppend", {
        params [["_entity", objNull, [objNull]], ["_packet", createHashMap, [createHashMap]]];
        if (isNull _entity || {count _packet == 0}) exitWith {};
        private _live = _entity getVariable ["comspec_sse_domex_livePackets", []];
        if !(_live isEqualType []) then { _live = []; };
        _live pushBack _packet;
        _entity setVariable ["comspec_sse_domex_livePackets", _live, true];
    }] call CBA_fnc_addEventHandler;
};

if (!hasInterface) exitWith {};

[] call comspec_sse_fnc_registerZenDomexLive;

// Modules ZEN du SSE (dossier, identité, biométrie, SEEK, documents). ZEN peut se
// charger après nous : on attend qu'il soit prêt (45 s au plus).
[{ !isNil "zen_custom_modules_fnc_register" }, {
    [] call comspec_sse_fnc_registerZenSseModules;
}, [], 45] call CBA_fnc_waitUntilAndExecute;
