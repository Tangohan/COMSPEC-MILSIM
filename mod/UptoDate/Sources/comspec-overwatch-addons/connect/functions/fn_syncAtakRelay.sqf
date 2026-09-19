/*
    Remonte un relais (fiche complète + intact / détruit) vers le poste.
    Params: [_obj, _aliveHint]
*/
params [
    ["_obj", objNull, [objNull]],
    ["_aliveHint", true, [true]]
];
if (isNull _obj) exitWith { false };

private _uid = _obj getVariable ["COMSPEC_AtakRelayUid", ""];
if (_uid isEqualTo "") then { _uid = netId _obj; };
private _pos = getPosATL _obj;
private _range = _obj getVariable ["COMSPEC_AtakRelayRange", 2000];
if (!(_range isEqualType 0)) then { _range = 2000; };
private _alive = _aliveHint && {alive _obj} && {damage _obj < 0.95};
private _name = _obj getVariable ["COMSPEC_AtakRelayName", "Relais ATAK"];
if (!(_name isEqualType "") || {_name isEqualTo ""}) then { _name = "Relais ATAK"; };
private _identity = _obj getVariable ["COMSPEC_AtakRelayIdentity", _name];
private _ip = _obj getVariable ["COMSPEC_AtakRelayIp", ""];
private _gw = _obj getVariable ["COMSPEC_AtakRelayGateway", ""];
private _cert = _obj getVariable ["COMSPEC_AtakRelayCertificate", ""];
private _slots = _obj getVariable ["COMSPEC_AtakRelaySlots", 8];
private _power = _obj getVariable ["COMSPEC_AtakRelayPowerW", 25];
private _thru = _obj getVariable ["COMSPEC_AtakRelayThroughput", 12];
private _rel = _obj getVariable ["COMSPEC_AtakRelayReliability", 92];
if (!(_slots isEqualType 0)) then { _slots = 8; };
if (!(_power isEqualType 0)) then { _power = 25; };
if (!(_thru isEqualType 0)) then { _thru = 12; };
if (!(_rel isEqualType 0)) then { _rel = 92; };
if (!_alive) then {
    _power = 0;
    _thru = 0;
    _rel = 0;
};

private _used = 0;
if (_alive) then {
    {
        if (isPlayer _x && {alive _x} && {(_x distance2D _obj) <= _range}) then {
            _used = _used + 1;
        };
    } forEach allPlayers;
};

private _q = toString [34];
private _esc = {
    params ["_s"];
    if (!(_s isEqualType "")) then { _s = str _s; };
    (_s splitString _q joinString "'") splitString "\" joinString "/"
};

private _extra = format [
    "{""name"":""%1"",""identity"":""%2"",""ip"":""%3"",""gateway"":""%4"",""certificate"":""%5"",""slots"":%6,""used"":%7,""power_w"":%8,""throughput_mbps"":%9,""reliability_pct"":%10}",
    [_name] call _esc,
    [_identity] call _esc,
    [_ip] call _esc,
    [_gw] call _esc,
    [_cert] call _esc,
    round _slots,
    round _used,
    round _power,
    (_thru toFixed 1),
    round _rel
];

private _fnc_num = { (_this select 0) toFixed (_this select 1) };
["UpdateRelay", [
    _uid,
    [_pos select 0, 2] call _fnc_num,
    [_pos select 1, 2] call _fnc_num,
    [_pos select 2, 2] call _fnc_num,
    str (round _range),
    if (_alive) then {"1"} else {"0"},
    _extra
], "Relais ATAK", false, false, "system", false] call comspec_overwatch_connect_fnc_callExtLogged;
true
