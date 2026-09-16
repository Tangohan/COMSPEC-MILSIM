/*
    Remonte un relais (position, portée, vivant/détruit) vers le poste.
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

private _fnc_num = { (_this select 0) toFixed (_this select 1) };
["UpdateRelay", [
    _uid,
    [_pos select 0, 2] call _fnc_num,
    [_pos select 1, 2] call _fnc_num,
    [_pos select 2, 2] call _fnc_num,
    str (round _range),
    if (_alive) then {"1"} else {"0"}
], "Relais ATAK", false, false, "system", false] call comspec_overwatch_connect_fnc_callExtLogged;
true
