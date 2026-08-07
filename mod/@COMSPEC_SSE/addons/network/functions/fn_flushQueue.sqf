if (isNil "comspec_sse_txQueue") exitWith { 0 };
if (count comspec_sse_txQueue == 0) exitWith { 0 };

private _remaining = [];
private _sent = 0;
{
    private _ok = [_x] call comspec_sse_fnc_sendViaOverwatch;
    if (_ok) then {
        _sent = _sent + 1;
        _x set ["txStatus", "TRANSMITTED"];
    } else {
        _remaining pushBack _x;
    };
} forEach comspec_sse_txQueue;

comspec_sse_txQueue = _remaining;
missionNamespace setVariable ["comspec_sse_txQueue", comspec_sse_txQueue];
[format ["flushQueue sent=%1 remaining=%2", _sent, count _remaining]] call comspec_sse_fnc_log;
_sent
