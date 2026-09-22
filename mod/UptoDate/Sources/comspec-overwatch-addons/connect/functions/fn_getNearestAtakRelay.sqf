/*
    Relais le plus proche (intact d’abord, sinon le plus proche détruit).
    Retour : HashMap (obj, uid, name, pos, range, dist, alive, identity, ip,
    gateway, certificate, slots, used, power_w, throughput_mbps, reliability_pct, grid)
    ou HashMap vide.
*/
private _out = createHashMap;
if (!hasInterface) exitWith { _out };

private _unit = missionNamespace getVariable ["COMSPEC_PlayerUnit", player];
if (isNull _unit) then { _unit = player; };
if (isNull _unit) exitWith { _out };

private _list = missionNamespace getVariable ["COMSPEC_AtakRelays", []];
if (!(_list isEqualType [])) exitWith { _out };

private _bestLive = objNull;
private _bestLiveD = 1e9;
private _bestAny = objNull;
private _bestAnyD = 1e9;

{
    if (isNull _x) then { continue };
    if (!(_x getVariable ["COMSPEC_AtakRelay", false])) then { continue };
    private _d = _unit distance2D _x;
    if (_d < _bestAnyD) then {
        _bestAnyD = _d;
        _bestAny = _x;
    };
    private _alive = alive _x && {damage _x < 0.95};
    if (_alive && {_d < _bestLiveD}) then {
        _bestLiveD = _d;
        _bestLive = _x;
    };
} forEach _list;

private _obj = if (!isNull _bestLive) then { _bestLive } else { _bestAny };
if (isNull _obj) exitWith { _out };

// Récupérer valeurs par défaut depuis config centralisée
private _defaultRange = 2000;
private _defaultSlots = 8;
if (!isNil "ATHENA_fnc_getRealismParam") then {
    _defaultRange = ["radio_relays", "relay_range_m", 2000] call ATHENA_fnc_getRealismParam;
    _defaultSlots = ["radio_relays", "max_relay_connections", 10] call ATHENA_fnc_getRealismParam;
};

private _alive = alive _obj && {damage _obj < 0.95};
private _dist = _unit distance2D _obj;
private _range = _obj getVariable ["COMSPEC_AtakRelayRange", _defaultRange];
if (!(_range isEqualType 0)) then { _range = _defaultRange; };
private _pos = getPosATL _obj;
private _name = _obj getVariable ["COMSPEC_AtakRelayName", "Relais ATAK"];
if (!(_name isEqualType "") || {_name isEqualTo ""}) then { _name = "Relais ATAK"; };

private _slots = _obj getVariable ["COMSPEC_AtakRelaySlots", _defaultSlots];
if (!(_slots isEqualType 0)) then { _slots = _defaultSlots; };
_slots = (round _slots) max 1 min 64;
private _used = 0;
if (_alive) then {
    {
        if (isPlayer _x && {alive _x} && {(_x distance2D _obj) <= _range}) then {
            _used = _used + 1;
        };
    } forEach allPlayers;
};
_used = _used min _slots;

private _power = _obj getVariable ["COMSPEC_AtakRelayPowerW", 25];
if (!(_power isEqualType 0)) then { _power = 25; };
private _thru = _obj getVariable ["COMSPEC_AtakRelayThroughput", 12];
if (!(_thru isEqualType 0)) then { _thru = 12; };
private _rel = _obj getVariable ["COMSPEC_AtakRelayReliability", 92];
if (!(_rel isEqualType 0)) then { _rel = 92; };

if (!_alive) then {
    _power = 0;
    _thru = 0;
    _rel = 0;
} else {
    private _ratio = 1;
    if (_range > 1) then { _ratio = ((_range - _dist) / _range) max 0 min 1; };
    private _dmg = damage _obj;
    _thru = ((_thru * (0.45 + (0.55 * _ratio))) * (1 - (_dmg * 0.7))) max 0;
    _rel = ((_rel * (0.55 + (0.45 * _ratio))) * (1 - (_dmg * 0.8))) max 0 min 100;
    if (_dist > _range) then {
        _rel = _rel * 0.25;
        _thru = _thru * 0.15;
    };
};

private _identity = _obj getVariable ["COMSPEC_AtakRelayIdentity", ""];
if (!(_identity isEqualType "") || {_identity isEqualTo ""}) then { _identity = _name; };
private _ip = _obj getVariable ["COMSPEC_AtakRelayIp", ""];
if (!(_ip isEqualType "") || {_ip isEqualTo ""}) then {
    _ip = format ["10.%1.%2.1", ((round abs (_pos select 0)) mod 220) + 10, ((round abs (_pos select 1)) mod 220) + 10];
};
private _gw = _obj getVariable ["COMSPEC_AtakRelayGateway", ""];
if (!(_gw isEqualType "") || {_gw isEqualTo ""}) then {
    _gw = format ["10.%1.0.1", ((round abs (_pos select 0)) mod 220) + 10];
};
private _cert = _obj getVariable ["COMSPEC_AtakRelayCertificate", ""];
if (!(_cert isEqualType "") || {_cert isEqualTo ""}) then { _cert = "Certificat de relais (non renseigné)"; };

_out set ["obj", _obj];
_out set ["uid", _obj getVariable ["COMSPEC_AtakRelayUid", ""]];
_out set ["name", _name];
_out set ["pos", _pos];
_out set ["range", _range];
_out set ["dist", _dist];
_out set ["alive", _alive];
_out set ["in_range", _dist <= _range];
_out set ["identity", _identity];
_out set ["ip", _ip];
_out set ["gateway", _gw];
_out set ["certificate", _cert];
_out set ["slots", _slots];
_out set ["used", _used];
_out set ["power_w", _power];
_out set ["throughput_mbps", _thru];
_out set ["reliability_pct", _rel];
_out set ["grid", mapGridPosition _pos];
_out
