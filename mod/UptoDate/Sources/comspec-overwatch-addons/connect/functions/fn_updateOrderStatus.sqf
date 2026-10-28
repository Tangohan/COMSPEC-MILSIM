/*
    Met à jour statut ordre: PENDING / ACK / EXEC / DONE / FAILED / CANCELLED / DELIVERED
    Params: [_orderId, _status, _note]
*/
params ["_orderId", ["_status", "ACK"], ["_note", ""]];

if (!(_orderId isEqualType "")) then { _orderId = str _orderId; };
_orderId = trim _orderId;
if (_orderId isEqualTo "") exitWith { false };

private _valid = ["PENDING", "ACK", "EXEC", "DONE", "FAILED", "CANCELLED", "DELIVERED"];
if !((toUpper _status) in _valid) exitWith { false };
_status = toUpper _status;

// Ids stockés en chaîne brute (pollOrders / issueOrder) : str ajouterait des guillemets et ne matcherait jamais.
private _fnc_idText = { params [["_v", ""]]; if (_v isEqualType "") then { trim _v } else { trim (str _v) } };

private _current = "PENDING";
{
    if (!(_x isEqualType createHashMap)) then { continue };
    if (([_x getOrDefault ["id", ""]] call _fnc_idText) isEqualTo _orderId) exitWith {
        _current = toUpper (trim (_x getOrDefault ["status", "PENDING"]));
    };
} forEach (missionNamespace getVariable ["COMSPEC_Orders", []]);
if (_current isEqualTo "" || {_current isEqualTo "-"}) then { _current = "PENDING"; };

if !([_current, _status] call comspec_overwatch_connect_fnc_orderCanTransition) exitWith { false };

private _orders = missionNamespace getVariable ["COMSPEC_Orders", []];
private _updated = false;

{
    if (!(_x isEqualType createHashMap)) then { continue };
    if (([_x getOrDefault ["id", ""]] call _fnc_idText) isEqualTo _orderId) exitWith {
        _x set ["id", _orderId];
        _x set ["status", _status];
        _x set ["updatedAt", serverTime];
        if (_note != "") then { _x set ["note", _note]; };
        _updated = true;
    };
} forEach _orders;

if (!_updated) exitWith { false };

missionNamespace setVariable ["COMSPEC_Orders", _orders, false];

private _by = [] call comspec_overwatch_connect_fnc_orderIssuerLabel;
private _orderLog = missionNamespace getVariable ["COMSPEC_OrderLog", []];
_orderLog pushBack [serverTime, _orderId, _by, "STATUS", _status, _note];
// Journal local borné à 200 entrées (il grossissait à chaque ordre / changement de statut, toute la partie).
if ((count _orderLog) > 200) then { _orderLog deleteRange [0, (count _orderLog) - 200]; };
missionNamespace setVariable ["COMSPEC_OrderLog", _orderLog, false];

private _payload = createHashMapFromArray [["id", _orderId], ["status", _status], ["note", _note], ["by", _by]];
["OnOrderStatusChanged", _payload] call comspec_overwatch_connect_fnc_publishEvent;

// Sync Athena (ordres web) — le motif est transmis pour refus / proposition
private _mapId = str (missionNamespace getVariable ["comspec_overwatch_map_id", 1]);
// POST hors du thread de jeu : file + un seul spawn qui envoie dans l'ordre via extAwait (le retour
// de l'extension n'était pas utilisé ; l'appel synchrone figeait le jeu à chaque changement de statut).
private _statusQueue = missionNamespace getVariable ["COMSPEC_OrderStatusQueue", []];
_statusQueue pushBack [_orderId, _status, _by, _mapId, _note];
missionNamespace setVariable ["COMSPEC_OrderStatusQueue", _statusQueue, false];
if !(missionNamespace getVariable ["COMSPEC_OrderStatusBusy", false]) then {
    missionNamespace setVariable ["COMSPEC_OrderStatusBusy", true, false];
    0 spawn {
        private _done = false;
        while { !_done } do {
            private _job = [];
            // Bloc atomique : pas de fenêtre où une entrée ajoutée resterait sans envoi.
            isNil {
                private _q = missionNamespace getVariable ["COMSPEC_OrderStatusQueue", []];
                if (_q isEqualTo []) then {
                    missionNamespace setVariable ["COMSPEC_OrderStatusBusy", false, false];
                } else {
                    _job = _q deleteAt 0;
                };
            };
            if (_job isEqualTo []) then {
                _done = true;
            } else {
                ["COMSPECExtension", "UpdateOrderStatus", _job] call comspec_overwatch_connect_fnc_extAwait;
            };
        };
    };
};

true
