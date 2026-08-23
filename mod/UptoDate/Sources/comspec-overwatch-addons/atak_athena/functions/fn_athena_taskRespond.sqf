/*
    Réponse à l’ordre sélectionné dans TASK (Accepter / Refuser / En cours / Abort).
    Params: [_action] ACCEPT | REFUSE | EXEC | ABORT
*/
params [["_action", "ACCEPT", [""]]];

if (!hasInterface) exitWith {};

private _orderId = uiNamespace getVariable ["COMSPEC_ATAK_Task_selectedId", ""];
if (_orderId isEqualTo "") exitWith {
    ["Sélectionnez d’abord un ordre.", "order", "warn"] call comspec_overwatch_connect_fnc_announce;
};

private _actionKey = toUpper _action;
private _status = "ACK";
private _note = "";
private _feedback = "Réponse envoyée.";

switch (_actionKey) do {
    case "ACCEPT";
    case "ACK": {
        _status = "ACK";
        _feedback = "Ordre accepté.";
    };
    case "REFUSE";
    case "FAILED": {
        _status = "FAILED";
        _note = "Refus depuis TASK";
        _feedback = "Ordre refusé — le commandement a été informé.";
    };
    case "EXEC": {
        _status = "EXEC";
        _feedback = "Ordre signalé en cours d’exécution.";
    };
    case "ABORT";
    case "CANCELLED": {
        _status = "CANCELLED";
        _note = "Interrompu depuis TASK";
        _feedback = "L’ordre a été interrompu. Le commandement a été informé.";
    };
    default {
        _status = "ACK";
        _feedback = "Réponse envoyée.";
    };
};

private _current = "PENDING";
private _orderData = createHashMap;
{
    if ((_x getOrDefault ["id", ""]) isEqualTo _orderId) exitWith {
        _current = toUpper (_x getOrDefault ["status", "PENDING"]);
        _orderData = _x;
    };
} forEach (missionNamespace getVariable ["COMSPEC_Orders", []]);

private _blocked = false;
if (!isNil "comspec_overwatch_connect_fnc_orderCanTransition") then {
    if !([_current, _status] call comspec_overwatch_connect_fnc_orderCanTransition) then {
        _blocked = true;
        private _msg = if (_status isEqualTo "EXEC") then {
            "Confirmez d’abord la réception (acceptation) avant de signaler l’exécution."
        } else {
            "Cette réponse n’est pas possible pour l’état actuel de l’ordre."
        };
        [_msg, "order", "warn"] call comspec_overwatch_connect_fnc_announce;
    };
};
if (_blocked) exitWith {};

private _ok = false;
if (!isNil "comspec_overwatch_connect_fnc_updateOrderStatus") then {
    _ok = [_orderId, _status, _note] call comspec_overwatch_connect_fnc_updateOrderStatus;
};
if (!_ok) then {
    private _mapId = str (missionNamespace getVariable ["comspec_overwatch_map_id", 1]);
    private _by = [] call comspec_overwatch_connect_fnc_getCallsign;
    if (_by isEqualTo "") then { _by = name player; };
    private _raw = ["COMSPECExtension" callExtension ["UpdateOrderStatus", [_orderId, _status, _by, _mapId, _note]]] call comspec_overwatch_connect_fnc_extResult;
    _ok = (_raw isEqualType "") && {((toUpper _raw) find "OK") == 0};
};

if (!_ok) exitWith {
    ["Impossible d’envoyer la réponse pour cet ordre.", "order", "warn"] call comspec_overwatch_connect_fnc_announce;
};

[_feedback, "order", "info"] call comspec_overwatch_connect_fnc_announce;

if (_status isEqualTo "ACK" && {(toUpper (_orderData getOrDefault ["type", ""])) isEqualTo "MOVE"}) then {
    if (!isNil "comspec_overwatch_connect_fnc_orderApplyMoveWaypoint") then {
        private _applied = missionNamespace getVariable ["COMSPEC_OrderWaypointsApplied", []];
        if !(_orderId in _applied) then {
            [_orderData] call comspec_overwatch_connect_fnc_orderApplyMoveWaypoint;
        };
    };
};

[] call comspec_overwatch_atak_athena_fnc_athena_updateTask;
