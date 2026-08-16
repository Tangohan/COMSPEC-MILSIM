/*
    Met en file un envoi Athena (offline-first).
    Déduplique par clé d’idempotence et plafonne la taille.
*/
params [
    ["_payload", createHashMap, [createHashMap]]
];

if (isNil "comspec_sse_txQueue") then { comspec_sse_txQueue = []; };

private _maxQueue = 80;
private _idem = _payload getOrDefault ["idempotency_key", ""];
if (_idem isEqualTo "") then {
    private _inner = _payload getOrDefault ["payload", createHashMap];
    if (_inner isEqualType createHashMap) then {
        _idem = _inner getOrDefault ["idempotency_key", ""];
    };
};

// Remplace une entrée déjà en attente avec la même clé (rejeu sans doublon)
if (_idem != "") then {
    private _idx = comspec_sse_txQueue findIf {
        private _k = _x getOrDefault ["idempotency_key", ""];
        if (_k isEqualTo "") then {
            private _p = _x getOrDefault ["payload", createHashMap];
            if (_p isEqualType createHashMap) then { _k = _p getOrDefault ["idempotency_key", ""]; };
        };
        _k isEqualTo _idem
    };
    if (_idx >= 0) then {
        comspec_sse_txQueue deleteAt _idx;
    };
};

_payload set ["txStatus", "QUEUED"];
_payload set ["queuedAt", time];
_payload set ["txAttempts", _payload getOrDefault ["txAttempts", 0]];
if (_idem != "") then { _payload set ["idempotency_key", _idem]; };

comspec_sse_txQueue pushBack _payload;

// Optimisation : écarter les plus anciens si file saturée
while { count comspec_sse_txQueue > _maxQueue } do {
    private _drop = comspec_sse_txQueue deleteAt 0;
    [format ["queueOffline drop oldest kind=%1", _drop getOrDefault ["kind", "?"]], "WARN"] call comspec_sse_fnc_log;
};

missionNamespace setVariable ["comspec_sse_txQueue", comspec_sse_txQueue];
[] call comspec_sse_fnc_persistQueue;
[format ["queueOffline size=%1 kind=%2 idem=%3", count comspec_sse_txQueue, _payload getOrDefault ["kind", "?"], _idem]] call comspec_sse_fnc_log;
true
