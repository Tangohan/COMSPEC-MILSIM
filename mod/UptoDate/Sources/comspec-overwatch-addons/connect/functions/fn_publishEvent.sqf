/*
    Publish event on local COMSPEC event bus (localNamespace, fn_registerEventHandler).
*/
params ["_eventName", ["_payload", createHashMap]];
if (_eventName isEqualTo "") exitWith { false };

private _bus = localNamespace getVariable ["COMSPEC_EventBus", createHashMap];
private _handlers = _bus getOrDefault [_eventName, []];

{
    [_payload] call _x;
} forEach _handlers;

true
