/*
    Crée un ordre C2 local + envoi backend via SendChat (fallback) / SubmitOrder (si dispo côté DLL).
    _targetType (optionnel) : "group" | "solo" | "fire_team" | "channel" | "all" — remonté au
    parseur PHP (AtakOrderRepository::parseOrderChatBody) pour un vrai routage web au lieu du
    "all" fixé en dur quand ce champ est absent. Laisser vide si le type de cible n'est pas connu
    à l'appel (comportement inchangé : diffusion à tous côté web, comme avant ce paramètre).
*/
params [
    ["_orderType", "MOVE"],
    ["_target", ""],
    ["_payload", ""],
    ["_priority", "IMPORTANT"],
    ["_parentOrderId", ""],
    ["_targetType", ""]
];

if (!hasInterface) exitWith { createHashMap };
if (isNull player) exitWith { createHashMap };

private _typeUp = toUpper _orderType;
private _validTypes = ["MOVE", "HOLD", "RECON", "CAS", "QRF", "FRAGO", "CUSTOM"];
private _isCustomType = (_typeUp select [0, 4]) isEqualTo "TYP_"
    || {(_typeUp select [0, 4]) isEqualTo "TPL_"}
    || {(_typeUp select [0, 7]) isEqualTo "CUSTOM_"};
if (!(_typeUp in _validTypes) && {!_isCustomType}) then { _orderType = "MOVE"; };

private _id = format ["ORD-%1-%2", round (serverTime * 1000), floor random 9999];
private _issuer = name player;
private _now = serverTime;

private _order = createHashMapFromArray [
    ["id", _id],
    ["parentId", _parentOrderId],
    ["type", _orderType],
    ["typeLabel", [_orderType] call comspec_overwatch_connect_fnc_orderTypeLabel],
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

// "TT:<type>|" en tête du payload : rétrocompatible (un payload sans ce préfixe se comporte
// exactement comme avant), parsé côté PHP sans décaler les positions ORDER|... existantes.
private _encodedPayload = if (_targetType != "") then {
    format ["TT:%1|%2", _targetType, _payload]
} else {
    _payload
};
private _encoded = format ["ORDER|%1|%2|%3|%4|%5|%6", _id, _orderType, _target, _priority, _issuer, _encodedPayload];
"COMSPECExtension" callExtension ["SendChat", [_issuer, _encoded]];

["OnOrderIssued", _order] call comspec_overwatch_connect_fnc_publishEvent;
// Diffusion multi-clients (le bus d’évènements local ne traverse pas le réseau)
[_order] remoteExecCall ["comspec_overwatch_connect_fnc_receiveOrder", 0, false];

_order
