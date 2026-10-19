diag_log "[SSE][POSTINIT][network] BEGIN";

[] call comspec_sse_fnc_restoreQueue;
if (isNil "comspec_sse_txQueue") then {
    comspec_sse_txQueue = [];
};

// Flush périodique si liaison disponible (45 s)
// Exécuté en contexte suspendable : les envois passent par l'appel non bloquant de la DLL (fn_extAwait).
// Réponses de la DLL : un seul gestionnaire par mission.
if (isNil "comspec_sse_extCbEh") then {
    comspec_sse_extCbEh = addMissionEventHandler ["ExtensionCallback", {
        params ["_name", "_function", ["_data", ""]];
        if (_name isNotEqualTo "comspec" || {!(_function isEqualType "")} || {(_function select [0, 6]) isNotEqualTo "async:"}) exitWith {};
        private _pending = missionNamespace getVariable ["comspec_sse_extPending", createHashMap];
        private _ticket = _function select [6];
        if (_ticket in _pending) then { _pending set [_ticket, [_data]]; };
    }];
};
[{
    if (missionNamespace getVariable ["comspec_sse_flushBusy", false]) exitWith {};
    if !(isNil "comspec_sse_txQueue" || {count comspec_sse_txQueue == 0}) then {
        missionNamespace setVariable ["comspec_sse_flushBusy", true];
        [] spawn {
            if ([] call comspec_sse_fnc_isOnline) then {
                private _n = [] call comspec_sse_fnc_flushQueue;
                if (_n > 0) then {
                    [format ["flushQueue auto: %1 envoyés", _n], "WARN"] call comspec_sse_fnc_log;
                };
            };
            missionNamespace setVariable ["comspec_sse_flushBusy", false];
        };
    };
}, 45] call CBA_fnc_addPerFrameHandler;

// Snapshot profil toutes les 3 min (file non vide ; flushQueue réécrit aussi le profil quand la file se vide)
[{
    if (!isNil "comspec_sse_txQueue" && {count comspec_sse_txQueue > 0}) then {
        [] call comspec_sse_fnc_persistQueue;
    };
}, 180] call CBA_fnc_addPerFrameHandler;

["network postInit OK — offline/sync LOT 7", "WARN"] call comspec_sse_fnc_log;
diag_log "[SSE][POSTINIT][network] END";
