/*
    Register callback for local COMSPEC event bus.
    Usage: ["OnOrderIssued", { params ["_payload"]; ... }] call ..._fnc_registerEventHandler;
*/
params ["_eventName", "_handler"];
if (_eventName isEqualTo "" || {isNil "_handler"}) exitWith { false };

private _bus = missionNamespace getVariable ["COMSPEC_EventBus", createHashMap];
private _handlers = _bus getOrDefault [_eventName, []];
_handlers pushBack _handler;
_bus set [_eventName, _handlers];
missionNamespace setVariable ["COMSPEC_EventBus", _bus, true];

true
