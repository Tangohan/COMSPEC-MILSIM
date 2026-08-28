/*
    Met en file un envoi Athena (offline-first).
    Déduplique par clé d’idempotence et plafonne la taille.
*/
params [
    ["_payload", createHashMap, [createHashMap]]
];

if (!(_payload isEqualType createHashMap)) then { _payload = createHashMap; };

if (isNil "comspec_sse_txQueue") then { comspec_sse_txQueue = []; };

private _maxQueue = 80;
private _idemKey = _payload getOrDefault ["idempotency_key", ""];
if (!(_idemKey isEqualType "")) then { _idemKey = ""; };
if (_idemKey isEqualTo "") then {
    private _inner = _payload getOrDefault ["payload", createHashMap];
    if (_inner isEqualType createHashMap) then {
        private _innerKey = _inner getOrDefault ["idempotency_key", ""];
        if (_innerKey isEqualType "") then { _idemKey = _innerKey; };
    };
};

// Remplace une entrée déjà en attente avec la même clé (rejeu sans doublon).
// forEach (pas findIf) : findIf + private interne rendait _idem invisible (erreur script).
if (_idemKey isNotEqualTo "") then {
    private _idx = -1;
    {
        if (_x isEqualType createHashMap) then {
            private _k = _x getOrDefault ["idempotency_key", ""];
            if (!(_k isEqualType "")) then { _k = ""; };
            if (_k isEqualTo "") then {
                private _p = _x getOrDefault ["payload", createHashMap];
                if (_p isEqualType createHashMap) then {
                    _k = _p getOrDefault ["idempotency_key", ""];
                    if (!(_k isEqualType "")) then { _k = ""; };
                };
            };
            if (_k isEqualTo _idemKey) exitWith { _idx = _forEachIndex };
        };
    } forEach comspec_sse_txQueue;
    if (_idx >= 0) then {
        comspec_sse_txQueue deleteAt _idx;
    };
};

_payload set ["txStatus", "QUEUED"];
_payload set ["queuedAt", time];
_payload set ["txAttempts", _payload getOrDefault ["txAttempts", 0]];
if (_idemKey isNotEqualTo "") then { _payload set ["idempotency_key", _idemKey]; };

comspec_sse_txQueue pushBack _payload;

while { count comspec_sse_txQueue > _maxQueue } do {
    private _drop = comspec_sse_txQueue deleteAt 0;
    private _dropKind = "?";
    if (_drop isEqualType createHashMap) then {
        _dropKind = _drop getOrDefault ["kind", "?"];
    };
    [format ["queueOffline drop oldest kind=%1", _dropKind], "WARN"] call comspec_sse_fnc_log;
};

missionNamespace setVariable ["comspec_sse_txQueue", comspec_sse_txQueue];
[] call comspec_sse_fnc_persistQueue;
private _kindLog = _payload getOrDefault ["kind", "?"];
[format ["queueOffline size=%1 kind=%2 idem=%3", count comspec_sse_txQueue, _kindLog, _idemKey]] call comspec_sse_fnc_log;
true
