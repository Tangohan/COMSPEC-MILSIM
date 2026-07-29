/*
    Met à jour statut ordre: PENDING / ACK / EXEC / FAILED / CANCELLED / DELIVERED
    Params: [_orderId, _status, _note]
*/
params ["_orderId", ["_status", "ACK"], ["_note", ""]];

private _valid = ["PENDING", "ACK", "EXEC", "FAILED", "CANCELLED", "DELIVERED"];
if !((toUpper _status) in _valid) exitWith { false };

private _current = "PENDING";
{
    if ((_x getOrDefault ["id", ""]) isEqualTo _orderId) exitWith {
        _current = toUpper (_x getOrDefault ["status", "PENDING"]);
    };
} forEach (missionNamespace getVariable ["COMSPEC_Orders", []]);

if !([_current, toUpper _status] call comspec_overwatch_connect_fnc_orderCanTransition) exitWith { false };

private _orders = missionNamespace getVariable ["COMSPEC_Orders", []];
private _updated = false;

{
    if ((_x getOrDefault ["id", ""]) isEqualTo _orderId) exitWith {
        _x set ["status", toUpper _status];
        _x set ["updatedAt", serverTime];
        if (_note != "") then { _x set ["note", _note]; };
        _updated = true;
    };
} forEach _orders;

if (!_updated) exitWith { false };

missionNamespace setVariable ["COMSPEC_Orders", _orders, true];

private _orderLog = missionNamespace getVariable ["COMSPEC_OrderLog", []];
_orderLog pushBack [serverTime, _orderId, name player, "STATUS", _status, _note];
missionNamespace setVariable ["COMSPEC_OrderLog", _orderLog, true];

private _payload = createHashMapFromArray [["id", _orderId], ["status", _status], ["note", _note], ["by", name player]];
["OnOrderStatusChanged", _payload] call comspec_overwatch_connect_fnc_publishEvent;

// Sync Athena (ordres web) — le motif est transmis pour refus / proposition
private _mapId = str (missionNamespace getVariable ["comspec_overwatch_map_id", 1]);
private _by = [] call comspec_overwatch_connect_fnc_getCallsign;
if (_by isEqualTo "") then { _by = name player; };
["COMSPECExtension" callExtension ["UpdateOrderStatus", [_orderId, toUpper _status, _by, _mapId, _note]]] call comspec_overwatch_connect_fnc_extResult;

true
