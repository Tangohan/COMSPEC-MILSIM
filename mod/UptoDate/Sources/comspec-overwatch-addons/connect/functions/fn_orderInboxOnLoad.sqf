/*
    Remplit la liste des ordres destinés au joueur.
*/
if (!hasInterface) exitWith {};

private _display = uiNamespace getVariable ["COMSPEC_OrderInbox_Display", displayNull];
if (isNull _display) exitWith {};

// Rafraîchir depuis Athena avant affichage
[] call comspec_overwatch_connect_fnc_pollOrders;

private _list = _display displayCtrl 9401;
if (isNull _list) exitWith {};
lbClear _list;

private _myName = name player;
private _myCallsign = [] call comspec_overwatch_connect_fnc_getCallsign;
private _orders = missionNamespace getVariable ["COMSPEC_Orders", []];
private _shown = [];

{
    private _order = _x;
    if (!(_order isEqualType createHashMap)) then { continue };
    if (!([_order] call comspec_overwatch_connect_fnc_orderConcernsPlayer)) then { continue };

    private _issuer = _order getOrDefault ["issuer", ""];
    private _target = trim (_order getOrDefault ["target", ""]);
    private _explicitMe = (_target != "") && {
        (toLower _target) isEqualTo (toLower _myCallsign)
        || {(toLower _target) isEqualTo (toLower _myName)}
    };
    // Masquer les ordres émis par soi sauf s’ils nous ciblent explicitement
    if ((_issuer isEqualTo _myName || {_issuer isEqualTo _myCallsign}) && {!_explicitMe}) then {
        continue;
    };

    private _id = _order getOrDefault ["id", ""];
    private _type = _order getOrDefault ["type", "MOVE"];
    // Signal terminal : pas listé comme ordre à répondre
    if ((toUpper _type) in ["VIBRATE", "NOTIFY", "HELMET_SNAP", "HELMET_SNAP_HD", "HELMET_STREAM"]) then { continue };
    private _status = _order getOrDefault ["status", "PENDING"];
    private _prio = _order getOrDefault ["priority", "IMPORTANT"];

    private _typeLabel = [_order] call comspec_overwatch_connect_fnc_orderTypeLabel;
    private _statusLabel = switch (toUpper _status) do {
        case "ACK": { "Accepté / confirmé" };
        case "EXEC": { "En cours" };
        case "FAILED": { "Refusé / échec" };
        case "DELIVERED": { "Reçu" };
        case "CANCELLED": { "Annulé" };
        default { "En attente de réponse" };
    };

    private _idx = _list lbAdd format ["[%1] %2 — %3 (%4)", _statusLabel, _typeLabel, _issuer, _prio];
    _list lbSetData [_idx, _id];
    _shown pushBack _id;
} forEach _orders;

uiNamespace setVariable ["COMSPEC_OrderInbox_Ids", _shown];

private _hint = _display displayCtrl 9402;
if (!isNull _hint) then {
    private _txt = if ((count _shown) == 0) then {
        "Aucun ordre en attente pour vous."
    } else {
        format ["%1 ordre(s) — sélectionnez puis choisissez une réponse.", count _shown]
    };
    _hint ctrlSetStructuredText parseText format ["<t align='center' size='0.55' color='#8aa0b4'>%1</t>", _txt];
};
