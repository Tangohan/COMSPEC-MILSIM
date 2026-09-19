/*
    Crée un mât vanilla détruisible et l’enregistre comme relais ATAK.
    Params: [_pos, _range, _name, _meta]
      _meta HashMap optionnel : identity, ip, gateway, certificate, slots, power_w,
      throughput_mbps, reliability_pct
*/
params [
    ["_pos", [0, 0, 0], [[]], 3],
    ["_range", 2000, [0]],
    ["_name", "Relais ATAK", [""]],
    "_meta"
];

if ((count _pos) < 2) exitWith { objNull };
_range = (_range max 50) min 8000;
if (_name isEqualTo "") then { _name = "Relais ATAK"; };
if (isNil "_meta" || {!(_meta isEqualType createHashMap)}) then { _meta = createHashMap; };

private _obj = createVehicle ["Land_Antenna_01_F", _pos, [], 0, "CAN_COLLIDE"];
if (isNull _obj) exitWith { objNull };
_obj setPosATL _pos;
_obj setVariable ["COMSPEC_AtakRelay", true, true];
_obj setVariable ["COMSPEC_AtakRelayRange", _range, true];
_obj setVariable ["COMSPEC_AtakRelayName", _name, true];

private _uid = format ["relay_%1_%2_%3", round (_pos select 0), round (_pos select 1), round random 9999];
_obj setVariable ["COMSPEC_AtakRelayUid", _uid, true];

private _identity = _meta getOrDefault ["identity", _name];
if (!(_identity isEqualType "") || {_identity isEqualTo ""}) then { _identity = _name; };
private _ip = _meta getOrDefault ["ip", ""];
if (!(_ip isEqualType "")) then { _ip = ""; };
private _gw = _meta getOrDefault ["gateway", ""];
if (!(_gw isEqualType "")) then { _gw = ""; };
private _cert = _meta getOrDefault ["certificate", ""];
if (!(_cert isEqualType "") || {_cert isEqualTo ""}) then {
    _cert = format ["Relais %1 — certificat de liaison", _name];
};
private _slots = _meta getOrDefault ["slots", 8];
if (!(_slots isEqualType 0)) then { _slots = 8; };
_slots = (round _slots) max 1 min 64;
private _power = _meta getOrDefault ["power_w", 25];
if (!(_power isEqualType 0)) then { _power = 25; };
private _thru = _meta getOrDefault ["throughput_mbps", 12];
if (!(_thru isEqualType 0)) then { _thru = 12; };
private _rel = _meta getOrDefault ["reliability_pct", 92];
if (!(_rel isEqualType 0)) then { _rel = 92; };

if (_ip isEqualTo "") then {
    _ip = format ["10.%1.%2.1", ((round abs (_pos select 0)) mod 220) + 10, ((round abs (_pos select 1)) mod 220) + 10];
};
if (_gw isEqualTo "") then {
    _gw = format ["10.%1.0.1", ((round abs (_pos select 0)) mod 220) + 10];
};

_obj setVariable ["COMSPEC_AtakRelayIdentity", _identity, true];
_obj setVariable ["COMSPEC_AtakRelayIp", _ip, true];
_obj setVariable ["COMSPEC_AtakRelayGateway", _gw, true];
_obj setVariable ["COMSPEC_AtakRelayCertificate", _cert, true];
_obj setVariable ["COMSPEC_AtakRelaySlots", _slots, true];
_obj setVariable ["COMSPEC_AtakRelayPowerW", _power, true];
_obj setVariable ["COMSPEC_AtakRelayThroughput", _thru, true];
_obj setVariable ["COMSPEC_AtakRelayReliability", _rel, true];

private _list = missionNamespace getVariable ["COMSPEC_AtakRelays", []];
_list = _list select { !isNull _x };
_list pushBackUnique _obj;
missionNamespace setVariable ["COMSPEC_AtakRelays", _list, true];

_obj addEventHandler ["Killed", {
    params ["_obj"];
    [_obj, false] call comspec_overwatch_connect_fnc_syncAtakRelay;
}];
_obj addEventHandler ["Hit", {
    params ["_obj"];
    if (damage _obj >= 0.95) then {
        [_obj, false] call comspec_overwatch_connect_fnc_syncAtakRelay;
    };
}];
_obj addEventHandler ["Deleted", {
    params ["_obj"];
    [_obj, false] call comspec_overwatch_connect_fnc_syncAtakRelay;
}];

[_obj, true] call comspec_overwatch_connect_fnc_syncAtakRelay;
_obj
