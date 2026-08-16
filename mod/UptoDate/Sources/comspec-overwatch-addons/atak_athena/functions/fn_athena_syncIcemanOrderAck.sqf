/*
    ACK synchro : ouverture d’un message cTab IceMan contenant ATHENA_ORDER_ID=…
    → UpdateOrderStatus ACK (même canal que l’inbox Athena).
*/
if (!hasInterface) exitWith {};

private _ackDone = missionNamespace getVariable ["COMSPEC_IcemanOrderAcks", []];
if (!(_ackDone isEqualType [])) then { _ackDone = []; };

private _receiver = missionNamespace getVariable ["cTab_player", player];
if (isNull _receiver) then { _receiver = player; };

private _key = "";
if (!isNil "cTab_fnc_getPlayerEncryptionKey") then {
    _key = call cTab_fnc_getPlayerEncryptionKey;
};
if (_key isEqualTo "") exitWith {};

private _msgArray = _receiver getVariable ["cTab_messages_" + _key, []];
if (!(_msgArray isEqualType []) || {(count _msgArray) < 1}) exitWith {};

private _extractOrderId = {
    params ["_body"];
    if (!(_body isEqualType "") || {_body isEqualTo ""}) exitWith { "" };
    private _marker = "";
    if (_body find "ATHENA_ORDER_ID=" >= 0) then {
        _marker = "ATHENA_ORDER_ID=";
    } else {
        if (_body find "ORDER_ID=" >= 0) then { _marker = "ORDER_ID="; };
    };
    if (_marker isEqualTo "") exitWith { "" };
    private _tail = _body select [(_body find _marker) + count _marker];
    _tail = (_tail splitString "<") select 0;
    _tail = (_tail splitString "|") select 0;
    _tail = (_tail splitString toString [10]) select 0;
    _tail = (_tail splitString toString [13]) select 0;
    _tail = (_tail splitString " ") select 0;
    _tail = (_tail splitString "&") select 0;
    trim _tail
};

private _by = "";
if (!isNil "comspec_overwatch_connect_fnc_getCallsign") then {
    _by = [] call comspec_overwatch_connect_fnc_getCallsign;
};
if (_by isEqualTo "") then { _by = name player; };

{
    if (!(_x isEqualType [])) then { continue };
    if ((count _x) < 3) then { continue };
    _x params ["_title", "_body", "_msgState"];
    // 1 = ouvert / lu côté cTab
    if (!(_msgState isEqualType 0) || {_msgState < 1}) then { continue };

    private _oid = [_body] call _extractOrderId;
    if (_oid isEqualTo "") then { continue };
    if (_oid in _ackDone) then { continue };

    _ackDone pushBack _oid;
    while { (count _ackDone) > 60 } do { _ackDone deleteAt 0; };
    missionNamespace setVariable ["COMSPEC_IcemanOrderAcks", _ackDone, false];

    if (!isNil "comspec_overwatch_connect_fnc_updateOrderStatus") then {
        [_oid, "ACK", "Lu sur ATAK Reports"] call comspec_overwatch_connect_fnc_updateOrderStatus;
    } else {
        private _mapId = str (missionNamespace getVariable ["comspec_overwatch_map_id", 1]);
        ["COMSPECExtension" callExtension ["UpdateOrderStatus", [_oid, "ACK", _by, _mapId, "Lu sur ATAK Reports"]]]
            call comspec_overwatch_connect_fnc_extResult;
    };
} forEach _msgArray;
