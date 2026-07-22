/*
    Crée un ordre C2 local + envoi backend via SendChat (fallback) / SubmitOrder (si dispo côté DLL).
*/
params [
    ["_orderType", "MOVE"],
    ["_target", ""],
    ["_payload", ""],
    ["_priority", "IMPORTANT"],
    ["_parentOrderId", ""]
];

private _validTypes = ["MOVE", "HOLD", "RECON", "CAS", "QRF"];
if !(_orderType in _validTypes) then { _orderType = "MOVE"; };

private _id = format ["ORD-%1-%2", round (serverTime * 1000), floor random 9999];
private _issuer = name player;
private _now = serverTime;

private _order = createHashMapFromArray [
    ["id", _id],
    ["parentId", _parentOrderId],
    ["type", _orderType],
    ["target", _target],
    ["payload", _payload],
    ["priority", _priority],
    ["issuer", _issuer],
    ["status", "PENDING"],
    ["createdAt", _now],
    ["updatedAt", _now]
];

private _orders = missionNamespace getVariable ["COMSPEC_Orders", []];
_orders pushBack _order;
missionNamespace setVariable ["COMSPEC_Orders", _orders, true];

private _orderLog = missionNamespace getVariable ["COMSPEC_OrderLog", []];
_orderLog pushBack [
    _now,
    _id,
    _issuer,
    _orderType,
    _target,
    "PENDING"
];
missionNamespace setVariable ["COMSPEC_OrderLog", _orderLog, true];

private _encoded = format ["ORDER|%1|%2|%3|%4|%5|%6", _id, _orderType, _target, _priority, _issuer, _payload];
"COMSPECExtension" callExtension ["SendChat", [_issuer, _encoded]];

["OnOrderIssued", _order] call comspec_overwatch_connect_fnc_publishEvent;
// Diffusion multi-clients (le bus d’évènements local ne traverse pas le réseau)
[_order] remoteExecCall ["comspec_overwatch_connect_fnc_receiveOrder", 0, false];

_order
