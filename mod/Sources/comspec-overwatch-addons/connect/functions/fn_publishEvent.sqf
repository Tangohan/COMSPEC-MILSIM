/*
    Publish event on local COMSPEC event bus.
*/
params ["_eventName", ["_payload", createHashMap]];
if (_eventName isEqualTo "") exitWith { false };

private _bus = missionNamespace getVariable ["COMSPEC_EventBus", createHashMap];
private _handlers = _bus getOrDefault [_eventName, []];

{
    [_payload] call _x;
} forEach _handlers;

true
