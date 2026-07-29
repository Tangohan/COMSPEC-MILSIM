/*
    Réponse à un ordre sélectionné dans la boîte de réception.
    Params: [_action] — ACCEPT | REFUSE | STANDBY | COUNTER (aliases ACK | FAILED | EXEC acceptés)
*/
params [["_action", "ACCEPT", [""]]];

if (!hasInterface) exitWith {};

private _display = uiNamespace getVariable ["COMSPEC_OrderInbox_Display", displayNull];
if (isNull _display) exitWith {};

private _list = _display displayCtrl 9401;
if (isNull _list) exitWith {};

private _idx = lbCurSel _list;
if (_idx < 0) exitWith {
    ["Sélectionnez d’abord un ordre.", "order", "warn"] call comspec_overwatch_connect_fnc_announce;
    ["COMSPEC_Warning", ["Sélectionnez d’abord un ordre."]] call comspec_overwatch_connect_fnc_showNotification;
};

private _orderId = _list lbData _idx;
if (_orderId isEqualTo "") exitWith {
    ["Cet ordre n’est plus disponible.", "order", "warn"] call comspec_overwatch_connect_fnc_announce;
    ["COMSPEC_Warning", ["Cet ordre n’est plus disponible."]] call comspec_overwatch_connect_fnc_showNotification;
};

private _noteCtrl = _display displayCtrl 9403;
private _note = if (!isNull _noteCtrl) then { trim (ctrlText _noteCtrl) } else { "" };

private _actionKey = toUpper _action;
private _status = "ACK";
private _finalNote = _note;
private _feedback = "Réponse envoyée.";

switch (_actionKey) do {
    case "ACCEPT";
    case "ACK": {
        _status = "ACK";
        _finalNote = _note;
        _feedback = "Ordre accepté.";
    };
    case "REFUSE";
    case "FAILED": {
        _status = "FAILED";
        _finalNote = format ["Refus : %1", _note];
        _feedback = "Ordre refusé — le commandement a été informé.";
    };
    case "STANDBY";
    case "PENDING": {
        _status = "ACK";
        _finalNote = if (_note isEqualTo "") then { "En attente" } else { format ["En attente — %1", _note] };
        _feedback = "Mise en attente signalée.";
    };
    case "COUNTER";
    case "PROPOSAL": {
        _status = "ACK";
        _finalNote = format ["Proposition de changement : %1", _note];
        _feedback = "Proposition de changement envoyée.";
    };
    case "EXEC": {
        _status = "EXEC";
        _finalNote = _note;
        _feedback = "Ordre signalé en cours d’exécution.";
    };
    default {
        _status = "ACK";
        _finalNote = _note;
        _feedback = "Réponse envoyée.";
    };
};

private _current = "PENDING";
{
    if ((_x getOrDefault ["id", ""]) isEqualTo _orderId) exitWith {
        _current = toUpper (_x getOrDefault ["status", "PENDING"]);
    };
} forEach (missionNamespace getVariable ["COMSPEC_Orders", []]);

if !([_current, _status] call comspec_overwatch_connect_fnc_orderCanTransition) exitWith {
    private _msg = if (_status isEqualTo "EXEC") then {
        "Confirmez d’abord la réception (acceptation) avant de signaler l’exécution."
    } else {
        "Cette réponse n’est pas possible pour l’état actuel de l’ordre."
    };
    [_msg, "order", "warn"] call comspec_overwatch_connect_fnc_announce;
    ["COMSPEC_Warning", [_msg]] call comspec_overwatch_connect_fnc_showNotification;
};

if (_actionKey in ["REFUSE", "FAILED"] && {_note isEqualTo ""}) exitWith {
    ["Indiquez un motif avant de refuser l’ordre.", "order", "warn"] call comspec_overwatch_connect_fnc_announce;
    ["COMSPEC_Warning", ["Indiquez un motif avant de refuser l’ordre."]] call comspec_overwatch_connect_fnc_showNotification;
};

if (_actionKey in ["COUNTER", "PROPOSAL"] && {_note isEqualTo ""}) exitWith {
    ["Décrivez votre proposition de changement avant d’envoyer.", "order", "warn"] call comspec_overwatch_connect_fnc_announce;
    ["COMSPEC_Warning", ["Décrivez votre proposition de changement avant d’envoyer."]] call comspec_overwatch_connect_fnc_showNotification;
};

private _ok = [_orderId, _status, _finalNote] call comspec_overwatch_connect_fnc_updateOrderStatus;
if (!_ok) exitWith {
    ["Impossible d’envoyer la réponse pour cet ordre.", "order", "warn"] call comspec_overwatch_connect_fnc_announce;
    ["COMSPEC_Warning", ["Impossible d’envoyer la réponse pour cet ordre."]] call comspec_overwatch_connect_fnc_showNotification;
};

[_feedback, "order", "info"] call comspec_overwatch_connect_fnc_announce;
["COMSPEC_Info", [_feedback]] call comspec_overwatch_connect_fnc_showNotification;

if (_status isEqualTo "ACK") then {
    private _orderData = createHashMap;
    {
        if ((_x getOrDefault ["id", ""]) isEqualTo _orderId) exitWith { _orderData = _x; };
    } forEach (missionNamespace getVariable ["COMSPEC_Orders", []]);
    private _ordType = toUpper (_orderData getOrDefault ["type", ""]);
    if (_ordType isEqualTo "MOVE") then {
        private _applied = missionNamespace getVariable ["COMSPEC_OrderWaypointsApplied", []];
        if !(_orderId in _applied) then {
            [_orderData] call comspec_overwatch_connect_fnc_orderApplyMoveWaypoint;
        };
    };
};

if (!isNull _noteCtrl) then { _noteCtrl ctrlSetText ""; };
[] call comspec_overwatch_connect_fnc_orderInboxOnLoad;
