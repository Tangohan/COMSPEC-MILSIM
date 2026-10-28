#include "script_component.hpp"

if !(["COMSPEC_SSE_CORE_POSTINIT_DONE", "XEH_postInit core"] call comspec_debug_fnc_guardOnce) exitWith {};

["comspec_sse_core_XEH_postInit", []] call comspec_debug_fnc_enter;

if !(["core"] call comspec_debug_fnc_isModuleEnabled) exitWith {
    ["WARN", "Boot", "ISOLATION", "SSE Core postInit skipped"] call comspec_debug_fnc_log;
    ["comspec_sse_core_XEH_postInit"] call comspec_debug_fnc_exit;
};

if (isServer) then {
    if (isNil "comspec_sse_missionRecords") then {
        comspec_sse_missionRecords = [];
    };
};

if (hasInterface) then {
    // Fiche publiée par le serveur : installer les menus d'objet là où ils manquent
    // (serveur dédié, objets Eden, entités activées par un autre poste).
    ["comspec_sse_recordChanged", {
        params ["_ent"];
        if (isNull _ent || {_ent isKindOf "CAManBase"}) exitWith {};
        if (_ent getVariable ["comspec_sse_aceMenusInstalled", false]) exitWith {};
        ["comspec_sse_entityEnabled", [_ent]] call CBA_fnc_localEvent;
    }] call CBA_fnc_addEventHandler;

    // Arrivée en cours de partie : les fiches sont déjà là (JIP), mais aucun événement n'est rejoué.
    // Parcours étalé (200 objets par image, doublons de vehicles retirés) : une seule boucle figeait l'image
    // à chaque arrivée sur une mission chargée en compositions (milliers d'objets).
    [{
        private _list = (allMissionObjects "All") + vehicles;
        _list = _list arrayIntersect _list;
        [{
            params ["_args", "_pfh"];
            _args params ["_list", "_i"];
            private _end = (_i + 200) min (count _list);
            for "_j" from _i to (_end - 1) do {
                private _o = _list select _j;
                if (
                    !isNull _o
                    && {_o getVariable ["comspec_sse_enabled", false]}
                    && {!(_o isKindOf "CAManBase")}
                    && {!(_o getVariable ["comspec_sse_aceMenusInstalled", false])}
                ) then {
                    ["comspec_sse_entityEnabled", [_o]] call CBA_fnc_localEvent;
                };
            };
            _args set [1, _end];
            if (_end >= (count _list)) then { [_pfh] call CBA_fnc_removePerFrameHandler; };
        }, 0, [_list, 0]] call CBA_fnc_addPerFrameHandler;
    }, [], 5] call CBA_fnc_waitAndExecute;
};

try {
    [] call comspec_sse_fnc_onSseEvent;
} catch {
    ["comspec_sse_fnc_onSseEvent", _exception, "core postInit"] call comspec_debug_fnc_exception;
};

["INFO", "Boot", "POSTINIT", "COMSPEC SSE core postInit OK"] call comspec_debug_fnc_log;
["comspec_sse_core_XEH_postInit"] call comspec_debug_fnc_exit;
