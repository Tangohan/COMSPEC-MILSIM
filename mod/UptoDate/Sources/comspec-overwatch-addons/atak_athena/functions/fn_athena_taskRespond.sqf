/*
    Réponse à l’ordre sélectionné dans TASK (Accepter / Refuser / En cours / Abort / Supprimer).
    Params: [_action] ACCEPT | REFUSE | EXEC | ABORT | DONE | DISMISS
*/
params [["_action", "ACCEPT", [""]]];

if (!hasInterface) exitWith {};

private _orderId = uiNamespace getVariable ["COMSPEC_ATAK_Task_selectedId", ""];
if (!(_orderId isEqualType "")) then { _orderId = str _orderId; };
_orderId = trim _orderId;

// Repli : id depuis la liste si mémoire vide.
if (_orderId isEqualTo "") then {
    private _group = uiNamespace getVariable ["COMSPEC_ATAK_Task_group", controlNull];
    if (!isNull _group) then {
        private _list = _group controlsGroupCtrl 9902;
        if (!isNull _list) then {
            private _sel = lbCurSel _list;
            if (_sel >= 0) then {
                _orderId = trim (str (_list lbData _sel));
                uiNamespace setVariable ["COMSPEC_ATAK_Task_selectedId", _orderId];
            };
        };
    };
};

if (_orderId isEqualTo "") exitWith {
    ["Sélectionnez d’abord un ordre.", "order", "warn"] call comspec_overwatch_connect_fnc_announce;
};

private _actionKey = toUpper _action;

// Retrait local d’un ordre déjà traité (ne revient pas tant que la session dure).
if (_actionKey in ["DISMISS", "DELETE", "REMOVE"]) exitWith {
    private _dismissed = missionNamespace getVariable ["COMSPEC_OrdersDismissed", []];
    if (!(_dismissed isEqualType [])) then { _dismissed = []; };
    if !(_orderId in _dismissed) then {
        _dismissed pushBack _orderId;
        missionNamespace setVariable ["COMSPEC_OrdersDismissed", _dismissed, false];
    };
    private _orders = missionNamespace getVariable ["COMSPEC_Orders", []];
    if (_orders isEqualType []) then {
        _orders = _orders select {
            !(_x isEqualType createHashMap)
            || {(str (_x getOrDefault ["id", ""])) isNotEqualTo _orderId}
        };
        missionNamespace setVariable ["COMSPEC_Orders", _orders, false];
    };
    uiNamespace setVariable ["COMSPEC_ATAK_Task_selectedId", ""];
    ["Ordre retiré de la liste.", "order", "info"] call comspec_overwatch_connect_fnc_announce;
    [] call comspec_overwatch_atak_athena_fnc_athena_updateTask;
};

private _status = "ACK";
private _note = "";
private _feedback = "Réponse envoyée.";

switch (_actionKey) do {
    case "ACCEPT";
    case "ACK": {
        _status = "ACK";
        _feedback = "Ordre accepté.";
    };
    case "COMPLETE";
    case "DONE": {
        _status = "DONE";
        _feedback = "Ordre signalé comme terminé.";
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
    if (!(_x isEqualType createHashMap)) then { continue };
    if ((str (_x getOrDefault ["id", ""])) isEqualTo _orderId) exitWith {
        _current = toUpper (trim (_x getOrDefault ["status", "PENDING"]));
        _orderData = _x;
    };
} forEach (missionNamespace getVariable ["COMSPEC_Orders", []]);
if (_current isEqualTo "" || {_current isEqualTo "-"}) then { _current = "PENDING"; };

private _blocked = false;
if (!isNil "comspec_overwatch_connect_fnc_orderCanTransition") then {
    if !([_current, _status] call comspec_overwatch_connect_fnc_orderCanTransition) then {
        _blocked = true;
        private _msg = if (_status isEqualTo "EXEC") then {
            "Confirmez d’abord la réception (acceptation) avant de signaler l’exécution."
        } else {
            if (_current in ["FAILED", "CANCELLED", "DONE", "CLOSED"]) then {
                "Cet ordre est déjà clos. Utilisez Supprimer pour le retirer de la liste."
            } else {
                "Cette réponse n’est pas possible pour l’état actuel de l’ordre."
            };
        };
        [_msg, "order", "warn"] call comspec_overwatch_connect_fnc_announce;
    };
};
if (_blocked) exitWith {
    [] call comspec_overwatch_atak_athena_fnc_athena_taskSyncButtons;
};

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
    if (_ok) then {
        private _orders = missionNamespace getVariable ["COMSPEC_Orders", []];
        if (_orders isEqualType []) then {
            {
                if (!(_x isEqualType createHashMap)) then { continue };
                if ((str (_x getOrDefault ["id", ""])) isEqualTo _orderId) exitWith {
                    _x set ["status", _status];
                    if (_note isNotEqualTo "") then { _x set ["note", _note]; };
                    _x set ["updatedAt", serverTime];
                };
            } forEach _orders;
            missionNamespace setVariable ["COMSPEC_Orders", _orders, false];
        };
    };
};

if (!_ok) exitWith {
    ["Impossible d’envoyer la réponse pour cet ordre. Vérifiez la liaison Athena, puis Actualiser.", "order", "warn"] call comspec_overwatch_connect_fnc_announce;
};

[_feedback, "order", "info"] call comspec_overwatch_connect_fnc_announce;
if (_status isEqualTo "ACK" && {!isNil "comspec_overwatch_connect_fnc_playAtakNotification"}) then {
    ["order_ack"] call comspec_overwatch_connect_fnc_playAtakNotification;
};

if (_status isEqualTo "ACK" && {(toUpper (_orderData getOrDefault ["type", ""])) isEqualTo "MOVE"}) then {
    if (!isNil "comspec_overwatch_connect_fnc_orderApplyMoveWaypoint") then {
        private _applied = missionNamespace getVariable ["COMSPEC_OrderWaypointsApplied", []];
        if !(_orderId in _applied) then {
            [_orderData] call comspec_overwatch_connect_fnc_orderApplyMoveWaypoint;
        };
    };
};

[] call comspec_overwatch_atak_athena_fnc_athena_updateTask;
