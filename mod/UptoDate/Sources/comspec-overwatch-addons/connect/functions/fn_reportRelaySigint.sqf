/*
    Si l’opérateur émet, chaque relais intact à portée envoie un gisement réel.
    Params: [_unit, _callSign]
*/
params [
    ["_unit", objNull, [objNull]],
    ["_callSign", "", [""]]
];
if (isNull _unit) exitWith { false };
if (_callSign isEqualTo "") then { _callSign = name _unit; };

private _now = time;
private _last = _unit getVariable ["COMSPEC_RelaySigintAt", -99];
if ((_now - _last) < 4) exitWith { false };
_unit setVariable ["COMSPEC_RelaySigintAt", _now, false];

private _list = missionNamespace getVariable ["COMSPEC_AtakRelays", []];
private _sent = 0;
private _fnc_num = { (_this select 0) toFixed (_this select 1) };
{
    if (isNull _x || {!alive _x}) then { continue };
    if (!(_x getVariable ["COMSPEC_AtakRelay", false])) then { continue };
    private _range = _x getVariable ["COMSPEC_AtakRelayRange", 2000];
    if (!(_range isEqualType 0)) then { _range = 2000; };
    if ((_x distance2D _unit) > _range) then { continue };
    private _rpos = getPosATL _x;
    private _dir = _x getDir _unit;
    "COMSPECExtension" callExtension ["SendSigint", [
        _callSign,
        [_rpos select 0, 2] call _fnc_num,
        [_rpos select 1, 2] call _fnc_num,
        [_dir, 1] call _fnc_num
    ]];
    _sent = _sent + 1;
} forEach _list;
_sent > 0
