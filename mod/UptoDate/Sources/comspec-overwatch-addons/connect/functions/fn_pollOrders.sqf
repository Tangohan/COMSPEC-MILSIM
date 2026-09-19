/*
    Interroge Athena (GetOrders). Les ordres du poste sont une photo de cette lecture
    (identifiants uniques), pas une file qui s’empile. Les ordres émis ici (source != "web")
    sont conservés. Notifie via receiveOrder pour les nouveaux IDs.
*/

if (!hasInterface) exitWith { false };

if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };

if (isNull player || {!([player] call comspec_overwatch_connect_fnc_hasTerminal)}) exitWith { false };

private _txGate = [true] call comspec_overwatch_connect_fnc_canTransmit;
if !(_txGate getOrDefault ["can_transmit", true]) exitWith { false };

private _mapId = str (missionNamespace getVariable ["comspec_overwatch_map_id", 1]);

if (_mapId isEqualTo "" || {_mapId isEqualTo "0"}) then { _mapId = "1"; };

private _callsign = [] call comspec_overwatch_connect_fnc_getCallsign;

private _sessionStart = missionNamespace getVariable ["COMSPEC_OrdersSessionStartedAt", ""];
if (!(_sessionStart isEqualType "") || {_sessionStart isEqualTo ""}) then {
    private _st = systemTimeUTC;
    private _p = {
        params ["_n"];
        _n = floor _n;
        if (_n < 10) then { "0" + str _n } else { str _n };
    };
    _sessionStart = format ["%1-%2-%3T%4:%5:%6Z",
        _st select 0,
        [_st select 1] call _p,
        [_st select 2] call _p,
        [_st select 3] call _p,
        [_st select 4] call _p,
        [_st select 5] call _p
    ];
    missionNamespace setVariable ["COMSPEC_OrdersSessionStartedAt", _sessionStart, false];
};

private _raw = ["COMSPECExtension" callExtension ["GetOrders", [_mapId, "40", _callsign, _sessionStart]]] call comspec_overwatch_connect_fnc_extResult;

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

private _legacy = missionNamespace getVariable ["COMSPEC_OrdersBeforeSession", []];
if (!(_legacy isEqualType [])) then { _legacy = []; };
_legacy = _legacy apply { trim (str _x) };

// Les ordres du poste sont une photo de la dernière lecture, pas une file qui s’empile.
// On ne conserve localement que les ordres émis ici (source != "web").
private _byId = createHashMap;
{
    if (!(_x isEqualType createHashMap)) then { continue };
    private _oid = trim (str (_x getOrDefault ["id", ""]));
    if (_oid isEqualTo "") then { continue };
    if (_oid in _legacy) then { continue };
    if (_oid in _dismissed) then { continue };
    if ((_x getOrDefault ["source", ""]) isEqualTo "web") then { continue };
    _x set ["id", _oid];
    _byId set [_oid, _x];
} forEach _orders;

private _tab = toString [9];

private _primed = missionNamespace getVariable ["COMSPEC_OrdersSessionPrimed", false];

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

    if (!_primed) then {
        if (!(_id in _legacy)) then { _legacy pushBack _id; };
        continue;
    };

    if (_id in _legacy) then { continue };

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

    if (_id in _byId) then {
        private _existing = _byId get _id;
        if (!(_existing isEqualType createHashMap)) then { continue };
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
_orders = values _byId;
if (!(_orders isEqualType [])) then { _orders = []; };
_orders = _orders select { _x isEqualType createHashMap };
if ((count _orders) > 40) then {
    _orders = [_orders, [], { _x getOrDefault ["updatedAt", 0] }, "DESCEND"] call BIS_fnc_sortBy;
    _orders resize 40;
};
missionNamespace setVariable ["COMSPEC_Orders", _orders, false];

private _canPush = true;
if (!isNil "comspec_overwatch_connect_fnc_diagIsolateAllows") then {
    _canPush = ["orders_push"] call comspec_overwatch_connect_fnc_diagIsolateAllows;
};

private _quiet = false;
if (!isNil "comspec_overwatch_connect_fnc_uplinkQuiet") then {
    _quiet = [] call comspec_overwatch_connect_fnc_uplinkQuiet;
};

if (!_primed) then {
    missionNamespace setVariable ["COMSPEC_OrdersBeforeSession", _legacy, false];
    private _t0 = missionNamespace getVariable ["COMSPEC_OrdersSessionFirstOkAt", -1];
    if (_t0 < 0) then {
        _t0 = diag_tickTime;
        missionNamespace setVariable ["COMSPEC_OrdersSessionFirstOkAt", _t0, false];
    };
    private _got = count _legacy;
    if (_got > 0 || {(diag_tickTime - _t0) >= 45}) then {
        missionNamespace setVariable ["COMSPEC_OrdersSessionPrimed", true, false];
        missionNamespace setVariable ["COMSPEC_OrdersPollBootstrapped", true, false];
        private _seen = missionNamespace getVariable ["COMSPEC_OrdersSeen", []];
        if (!(_seen isEqualType [])) then { _seen = []; };
        _seen = _seen apply { trim (str _x) };
        { if (!(_x in _seen)) then { _seen pushBack _x; }; } forEach _legacy;
        if (count _seen > 80) then { _seen deleteRange [0, (count _seen) - 80]; };
        missionNamespace setVariable ["COMSPEC_OrdersSeen", _seen, false];
        ["INFO", "Ordres", format [
            "Partie en cours : %1 ordre(s) d’avant ignoré(s)",
            _got
        ]] call comspec_overwatch_connect_fnc_log;
    };
    ["ordres", count _lines, format [
        " · hors partie · ignorés %1 · mémoire %2",
        count _legacy,
        count _orders
    ]] call comspec_overwatch_connect_fnc_noteUplinkReturn;
} else {
    if (_quiet) then { _canPush = false; };

    private _phoneOpen = false;
    if (!isNil "comspec_overwatch_atak_athena_fnc_athena_phoneDisplay") then {
        _phoneOpen = !isNull ([] call comspec_overwatch_atak_athena_fnc_athena_phoneDisplay);
    };

    ["ordres", count _lines, format [
        " · nouveaux %1 · mémoire %2%3",
        count _newOnes,
        count _orders,
        if (_canPush) then { "" } else { " · affichage reporté" }
    ]] call comspec_overwatch_connect_fnc_noteUplinkReturn;

    private _fnc_markSeen = {
        params ["_list"];
        if (!(_list isEqualType [])) exitWith {};
        private _seen = missionNamespace getVariable ["COMSPEC_OrdersSeen", []];
        if (!(_seen isEqualType [])) then { _seen = []; };
        _seen = _seen apply { trim (str _x) };
        {
            if (!(_x isEqualType createHashMap)) then { continue };
            private _nid = trim (str (_x getOrDefault ["id", ""]));
            if (_nid isNotEqualTo "" && {!(_nid in _seen)}) then { _seen pushBack _nid; };
        } forEach _list;
        if ((count _seen) > 80) then { _seen deleteRange [0, (count _seen) - 80]; };
        missionNamespace setVariable ["COMSPEC_OrdersSeen", _seen, false];
    };

    if (!_canPush) then {
        [_newOnes] call _fnc_markSeen;
    } else {
        if ((count _newOnes) > 0) then {
            [_newOnes select 0] call comspec_overwatch_connect_fnc_receiveOrder;
            if ((count _newOnes) > 1) then {
                [_newOnes select [1, (count _newOnes) - 1]] call _fnc_markSeen;
            };
        };
    };

    if (
        _canPush
        && {_phoneOpen}
        && {!(missionNamespace getVariable ["COMSPEC_DiagIsolateActive", false])}
    ) then {
        if (!isNil "comspec_overwatch_atak_athena_fnc_athena_syncOrdersToGroupChat") then {
            [] call comspec_overwatch_atak_athena_fnc_athena_syncOrdersToGroupChat;
        };
    };
};

_added > 0
