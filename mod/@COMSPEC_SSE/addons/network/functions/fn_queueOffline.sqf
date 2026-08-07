params [
    ["_payload", createHashMap, [createHashMap]]
];

if (isNil "comspec_sse_txQueue") then { comspec_sse_txQueue = []; };
_payload set ["txStatus", "QUEUED"];
_payload set ["queuedAt", time];
comspec_sse_txQueue pushBack _payload;
missionNamespace setVariable ["comspec_sse_txQueue", comspec_sse_txQueue];
[format ["queueOffline size=%1 kind=%2", count comspec_sse_txQueue, _payload getOrDefault ["kind", "?"]]] call comspec_sse_fnc_log;
true
