/*
    Interroge Athena (GetOrders) et fusionne les ordres web dans COMSPEC_Orders.
    Notifie via receiveOrder pour les nouveaux IDs.
    Les identifiants sont toujours des chaînes : évite les doublons au refresh.
*/

if (!hasInterface) exitWith { false };

if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };

private _txGate = [true] call comspec_overwatch_connect_fnc_canTransmit;
if !(_txGate getOrDefault ["can_transmit", true]) exitWith { false };

private _mapId = str (missionNamespace getVariable ["comspec_overwatch_map_id", 1]);

if (_mapId isEqualTo "" || {_mapId isEqualTo "0"}) then { _mapId = "1"; };

private _callsign = [] call comspec_overwatch_connect_fnc_getCallsign;

private _raw = ["COMSPECExtension" callExtension ["GetOrders", [_mapId, "40", _callsign]]] call comspec_overwatch_connect_fnc_extResult;

if (!(_raw isEqualType "") || {_raw isEqualTo ""}) exitWith {
    ["ordres", 0, " (vide)"] call comspec_overwatch_connect_fnc_noteUplinkReturn;
    false
};

if ((_raw select [0, 3]) != "OK|") exitWith {
    ["ordres", 0, " (erreur)"] call comspec_overwatch_connect_fnc_noteUplinkReturn;
    false
};

private _body = _raw select [3];

private _lines = _body splitString (toString [10]);

private _orders = missionNamespace getVariable ["COMSPEC_Orders", []];

if (!(_orders isEqualType [])) then { _orders = []; };

private _dismissed = missionNamespace getVariable ["COMSPEC_OrdersDismissed", []];
if (!(_dismissed isEqualType [])) then { _dismissed = []; };
_dismissed = _dismissed apply { trim (str _x) };
missionNamespace setVariable ["COMSPEC_OrdersDismissed", _dismissed, false];

// Index unique par id (chaîne) — écrase d’éventuels doublons déjà présents.
private _byId = createHashMap;
{
    if (!(_x isEqualType createHashMap)) then { continue };
    private _oid = trim (str (_x getOrDefault ["id", ""]));
    if (_oid isEqualTo "") then { continue };
    _x set ["id", _oid];
    private _prev = _byId getOrDefault [_oid, createHashMap];
    if (_prev isEqualType createHashMap && {count _prev > 0}) then {
        private _prevUpd = _prev getOrDefault ["updatedAt", 0];
        private _curUpd = _x getOrDefault ["updatedAt", 0];
        if (_curUpd < _prevUpd) then { continue };
    };
    _byId set [_oid, _x];
} forEach _orders;

private _tab = toString [9];

private _added = 0;

private _newOnes = [];

{
    private _line = _x;

    if (_line isEqualTo "") then { continue };

    private _cols = _line splitString _tab;

    if ((count _cols) < 6) then { continue };

    private _unblank = {
        params ["_s"];
        _s = trim _s;
        if (_s isEqualTo "-") then { "" } else { _s };
    };

    private _id = trim (str ([_cols select 0] call _unblank));
    if (_id isEqualTo "") then { continue };

    if (_id in _dismissed) then { continue };

    private _type = [_cols select 1] call _unblank;
    private _target = [_cols select 2] call _unblank;
    private _priority = [_cols select 3] call _unblank;
    private _issuer = [_cols select 4] call _unblank;
    private _status = [_cols select 5] call _unblank;
    private _payload = if ((count _cols) > 6) then { [_cols select 6] call _unblank } else { "" };
    private _targetType = if ((count _cols) > 7) then { [_cols select 7] call _unblank } else { "all" };
    private _targetRef = if ((count _cols) > 8) then { [_cols select 8] call _unblank } else { "" };
    private _aliases = if ((count _cols) > 9) then { [_cols select 9] call _unblank } else { "" };
    private _typeLabel = if ((count _cols) > 10) then { [_cols select 10] call _unblank } else { "" };

    if (_type isEqualTo "") then { _type = "MOVE"; };
    if (_targetType isEqualTo "") then { _targetType = "all"; };
    if (_status isEqualTo "") then { _status = "PENDING"; };
    _status = toUpper _status;

    private _existing = _byId getOrDefault [_id, createHashMap];

    if (_existing isEqualType createHashMap && {count _existing > 0}) then {
        _existing set ["id", _id];
        _existing set ["type", _type];
        _existing set ["target", _target];
        _existing set ["priority", _priority];
        _existing set ["issuer", _issuer];
        _existing set ["status", _status];
        _existing set ["payload", _payload];
        _existing set ["targetType", _targetType];
        _existing set ["targetRef", _targetRef];
        _existing set ["aliases", _aliases];
        _existing set ["typeLabel", _typeLabel];
        _existing set ["source", "web"];
        _existing set ["updatedAt", serverTime];
        _byId set [_id, _existing];
        continue;
    };

    private _order = createHashMapFromArray [
        ["id", _id],
        ["parentId", ""],
        ["type", _type],
        ["target", _target],
        ["payload", _payload],
        ["priority", _priority],
        ["issuer", _issuer],
        ["status", _status],
        ["targetType", _targetType],
        ["targetRef", _targetRef],
        ["aliases", _aliases],
        ["typeLabel", _typeLabel],
        ["source", "web"],
        ["createdAt", serverTime],
        ["updatedAt", serverTime]
    ];

    // Ne garder localement que ce qui nous concerne (filet si le serveur n’a pas filtré)
    if (!([_order] call comspec_overwatch_connect_fnc_orderConcernsPlayer)) then { continue };

    _byId set [_id, _order];

    // Ne rejouer notif / vibration que si encore à livrer (évite re-buzz à la reconnexion).
    private _stUp = toUpper _status;
    if (_stUp in ["PENDING", "DELIVERED", ""]) then {
        _newOnes pushBack _order;
    };

    _added = _added + 1;

} forEach _lines;

// Liste sans doublons (valeurs de l’index par id).
_orders = [];
{
    _orders pushBack _y;
} forEach _byId;
missionNamespace setVariable ["COMSPEC_Orders", _orders, false];

private _canPush = true;
if (!isNil "comspec_overwatch_connect_fnc_diagIsolateAllows") then {
    _canPush = ["orders_push"] call comspec_overwatch_connect_fnc_diagIsolateAllows;
};

["ordres", count _lines, format [
    " · nouveaux %1 · mémoire %2%3",
    count _newOnes,
    count _orders,
    if (_canPush) then { "" } else { " · affichage reporté" }
]] call comspec_overwatch_connect_fnc_noteUplinkReturn;

if (_canPush) then {
    {
        [_x] call comspec_overwatch_connect_fnc_receiveOrder;
    } forEach _newOnes;

    if (!isNil "comspec_overwatch_atak_athena_fnc_athena_syncOrdersToGroupChat") then {
        [] call comspec_overwatch_atak_athena_fnc_athena_syncOrdersToGroupChat;
    };
};

_added > 0
