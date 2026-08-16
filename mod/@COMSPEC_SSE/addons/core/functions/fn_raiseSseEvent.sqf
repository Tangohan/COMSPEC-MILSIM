/*
    Bus d'événements COMSPEC SSE (LOT 1).
    [_eventName, _envelope, _global] call comspec_sse_fnc_raiseSseEvent
*/
params [
    ["_eventName", "", [""]],
    ["_envelope", createHashMap, [createHashMap, []]],
    ["_global", false, [false]]
];

if (_eventName isEqualTo "") exitWith { false };

if (isNil "comspec_sse_eventBusDepth") then { comspec_sse_eventBusDepth = 0; };
if (comspec_sse_eventBusDepth > 8) exitWith {
    ["SSE event bus depth exceeded — dropped " + _eventName] call comspec_sse_fnc_log;
    false
};

private _payload = _envelope;
if (_envelope isEqualType []) then {
    _payload = createHashMapFromArray _envelope;
};
if !(_payload isEqualType createHashMap) then {
    _payload = createHashMap;
};

if ((_payload getOrDefault ["event_uuid", ""]) isEqualTo "") then {
    _payload set ["event_uuid", format ["%1-%2-%3", diag_tickTime, random 99999, _eventName]];
};
if ((_payload getOrDefault ["idempotency_key", ""]) isEqualTo "") then {
    _payload set ["idempotency_key", _payload getOrDefault ["event_uuid", ""]];
};
if ((_payload getOrDefault ["source_system", ""]) isEqualTo "") then {
    _payload set ["source_system", "ARMA_SSE"];
};
if !(_payload getOrDefault ["event_time_set", false]) then {
    _payload set ["event_time", time];
    _payload set ["event_time_set", true];
};

comspec_sse_eventBusDepth = comspec_sse_eventBusDepth + 1;
private _ok = true;
try {
    if (_global) then {
        [_eventName, [_payload]] call CBA_fnc_globalEvent;
    } else {
        [_eventName, [_payload]] call CBA_fnc_localEvent;
    };
} catch {
    _ok = false;
    ["SSE event bus error on " + _eventName] call comspec_sse_fnc_log;
};
comspec_sse_eventBusDepth = (comspec_sse_eventBusDepth - 1) max 0;

_ok
