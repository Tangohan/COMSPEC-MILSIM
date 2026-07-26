/*
    Réponse à un ordre sélectionné dans la boîte de réception.
    Params: [_status] — ACK | EXEC | FAILED
*/
params [["_status", "ACK", [""]]];

if (!hasInterface) exitWith {};

private _display = uiNamespace getVariable ["COMSPEC_OrderInbox_Display", displayNull];
if (isNull _display) exitWith {};

private _list = _display displayCtrl 9401;
if (isNull _list) exitWith {};

private _idx = lbCurSel _list;
if (_idx < 0) exitWith {
    ["COMSPEC_Warning", ["Sélectionnez d’abord un ordre."]] call comspec_overwatch_connect_fnc_showNotification;
};

private _orderId = _list lbData _idx;
if (_orderId isEqualTo "") exitWith {};

private _ok = [_orderId, _status, ""] call comspec_overwatch_connect_fnc_updateOrderStatus;
if (!_ok) exitWith {
    ["COMSPEC_Warning", ["Impossible de mettre à jour cet ordre."]] call comspec_overwatch_connect_fnc_showNotification;
};

private _label = switch (toUpper _status) do {
    case "ACK": { "Order acknowledged" };
    case "EXEC": { "Ordre en cours d’exécution" };
    case "FAILED": { "Order reported as failed" };
    default { "Statut d’ordre mis à jour" };
};

["COMSPEC_Info", [_label]] call comspec_overwatch_connect_fnc_showNotification;
[] call comspec_overwatch_connect_fnc_orderInboxOnLoad;
