/*
    Register callback for local COMSPEC event bus (localNamespace : propre à la machine, jamais synchronisé).
    Usage: ["OnOrderIssued", { params ["_payload"]; ... }] call ..._fnc_registerEventHandler;
*/
params ["_eventName", "_handler"];
if (_eventName isEqualTo "" || {isNil "_handler"}) exitWith { false };

private _bus = localNamespace getVariable ["COMSPEC_EventBus", createHashMap];
private _handlers = _bus getOrDefault [_eventName, []];
_handlers pushBack _handler;
_bus set [_eventName, _handlers];
// Bus local (cf. en-tête) : les abonnés sont du code, gardé sur cette machine seulement.
localNamespace setVariable ["COMSPEC_EventBus", _bus];

true
