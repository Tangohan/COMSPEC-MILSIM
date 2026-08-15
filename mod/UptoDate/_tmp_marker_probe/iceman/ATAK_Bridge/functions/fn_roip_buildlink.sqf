params [
    ["_radioRecord", []],
    ["_talkgroup", 1]
];

if !(_radioRecord isEqualType [] && {(count _radioRecord) >= 6}) exitWith {[]};
_radioRecord params ["_radioId", "_radioClass", "_channelNumber", "_label", "_on", "_pairs"];
if (!_on || {_radioId == ""} || {_pairs isEqualTo []}) exitWith {[]};

private _wrState = if !(isNil "Iceman_fnc_wr_getState") then {call Iceman_fnc_wr_getState} else {createHashMap};
private _bank = _wrState getOrDefault ["frequency", player getVariable ["Iceman_WR_frequency", "32.0"]];
if !(_bank isEqualType "") then {_bank = str _bank};

private _tg = (round _talkgroup) max 1 min 16;
private _uid = getPlayerUID player;
if (_uid == "") then {_uid = netId player};
private _ownerName = name player;
if (_ownerName == "") then {_ownerName = "Unknown"};

private _legacyPower = 5000;
{
    if ((_x # 0) == "power") exitWith {_legacyPower = _x # 1};
} forEach _pairs;
if !(_legacyPower isEqualType 0) then {_legacyPower = 5000};

[
    1,
    format ["ROIP:%1:%2", _uid, _radioId],
    _bank,
    _tg,
    _radioId,
    _radioClass,
    _channelNumber,
    _label,
    _pairs,
    _ownerName,
    _uid,
    CBA_missionTime,
    _legacyPower
]
