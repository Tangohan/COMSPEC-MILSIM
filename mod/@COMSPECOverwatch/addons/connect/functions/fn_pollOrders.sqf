/*

    Interroge Athena (GetOrders) et fusionne les ordres web dans COMSPEC_Orders.

    Notifie via receiveOrder pour les nouveaux IDs.

*/

if (!hasInterface) exitWith { false };

if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };



private _mapId = str (missionNamespace getVariable ["comspec_overwatch_map_id", 1]);

if (_mapId isEqualTo "" || {_mapId isEqualTo "0"}) then { _mapId = "1"; };

private _callsign = [] call comspec_overwatch_connect_fnc_getCallsign;



private _raw = ["COMSPECExtension" callExtension ["GetOrders", [_mapId, "40", _callsign]]] call comspec_overwatch_connect_fnc_extResult;

if (!(_raw isEqualType "") || {_raw isEqualTo ""}) exitWith { false };

if ((_raw select [0, 3]) != "OK|") exitWith { false };



private _body = _raw select [3];

private _lines = _body splitString (toString [10]);

private _orders = missionNamespace getVariable ["COMSPEC_Orders", []];

if (!(_orders isEqualType [])) then { _orders = []; };



private _byId = createHashMap;

{

    if (!(_x isEqualType createHashMap)) then { continue };

    private _oid = _x getOrDefault ["id", ""];

    if (_oid != "") then { _byId set [_oid, _x]; };

} forEach _orders;



private _tab = toString [9];

private _added = 0;

private _newOnes = [];



{

    private _line = _x;

    if (_line isEqualTo "") then { continue };

    private _cols = _line splitString _tab;

    if ((count _cols) < 6) then { continue };



    private _id = _cols select 0;

    private _type = _cols select 1;

    private _target = _cols select 2;

    private _priority = _cols select 3;

    private _issuer = _cols select 4;

    private _status = _cols select 5;

    private _payload = if ((count _cols) > 6) then { _cols select 6 } else { "" };

    private _targetType = if ((count _cols) > 7) then { _cols select 7 } else { "all" };

    private _targetRef = if ((count _cols) > 8) then { _cols select 8 } else { "" };

    private _aliases = if ((count _cols) > 9) then { _cols select 9 } else { "" };



    if (_id isEqualTo "") then { continue };



    private _existing = _byId getOrDefault [_id, createHashMap];

    if (_existing isEqualType createHashMap && {count _existing > 0}) then {

        _existing set ["type", _type];

        _existing set ["target", _target];

        _existing set ["priority", _priority];

        _existing set ["issuer", _issuer];

        _existing set ["status", _status];

        _existing set ["payload", _payload];

        _existing set ["targetType", _targetType];

        _existing set ["targetRef", _targetRef];

        _existing set ["aliases", _aliases];

        _existing set ["source", "web"];

        _existing set ["updatedAt", serverTime];

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

        ["source", "web"],

        ["createdAt", serverTime],

        ["updatedAt", serverTime]

    ];



    // Ne garder localement que ce qui nous concerne (filet si le serveur n’a pas filtré)

    if (!([_order] call comspec_overwatch_connect_fnc_orderConcernsPlayer)) then { continue };



    _orders pushBack _order;

    _byId set [_id, _order];

    _newOnes pushBack _order;

    _added = _added + 1;

} forEach _lines;



missionNamespace setVariable ["COMSPEC_Orders", _orders, false];



{

    [_x] call comspec_overwatch_connect_fnc_receiveOrder;

} forEach _newOnes;



_added > 0

