/*
    Vide la file offline vers Overwatch / Athena.
    Batch limité + backoff + plafond de tentatives.
*/
if (isNil "comspec_sse_txQueue") exitWith { 0 };
if (count comspec_sse_txQueue == 0) exitWith { 0 };

private _maxAttempts = 5;
private _batchMax = 8;
private _remaining = [];
private _sent = 0;
private _dropped = 0;
private _processed = 0;

{
    if (_processed >= _batchMax) then {
        _remaining pushBack _x;
    } else {
        private _attempts = _x getOrDefault ["txAttempts", 0];
        private _retryAfter = _x getOrDefault ["txRetryAfter", 0];
        if (_retryAfter > time) then {
            _remaining pushBack _x;
        } else {
            _processed = _processed + 1;
            if (_attempts >= _maxAttempts) then {
                _x set ["txStatus", "FAILED"];
                _dropped = _dropped + 1;
                [format ["flushQueue drop after %1 attempts kind=%2", _attempts, _x getOrDefault ["kind", "?"]], "WARN"] call comspec_sse_fnc_log;
            } else {
                private _ok = [_x] call comspec_sse_fnc_sendViaOverwatch;
                if (_ok) then {
                    _sent = _sent + 1;
                    _x set ["txStatus", "TRANSMITTED"];
                    _x set ["txAttempts", _attempts + 1];
                } else {
                    private _next = _attempts + 1;
                    private _exp = (_next - 1) min 3;
                    private _delay = 15 * (2 ^ _exp);
                    _x set ["txAttempts", _next];
                    _x set ["txStatus", "QUEUED"];
                    _x set ["txRetryAfter", time + _delay];
                    _remaining pushBack _x;
                };
            };
        };
    };
} forEach comspec_sse_txQueue;

comspec_sse_txQueue = _remaining;
missionNamespace setVariable ["comspec_sse_txQueue", comspec_sse_txQueue];
[] call comspec_sse_fnc_persistQueue;
[format ["flushQueue sent=%1 remaining=%2 dropped=%3 batch=%4", _sent, count _remaining, _dropped, _processed], "WARN"] call comspec_sse_fnc_log;
_sent
