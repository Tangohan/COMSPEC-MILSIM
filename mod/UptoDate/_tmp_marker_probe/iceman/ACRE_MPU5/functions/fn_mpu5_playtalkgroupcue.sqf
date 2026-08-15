params [
    ["_radioId", "", [""]],
    ["_sound", "Acre_GenericBeep", [""]],
    ["_volume", 1, [0]]
];

if (_radioId == "" || {isNil "acre_sys_sounds_fnc_playSound"}) exitWith {false};

private _talkgroup = player getVariable ["Iceman_WR_activeTG", 1];
if (!(isNil "acre_sys_data_fnc_dataEvent")) then {
    private _channelData = [_radioId, "getCurrentChannelData"] call acre_sys_data_fnc_dataEvent;
    if (_channelData isEqualType locationNull && {!isNull _channelData}) then {
        private _channelTalkgroup = _channelData getVariable ["Iceman_WR_talkgroup", _talkgroup];
        _talkgroup = if (_channelTalkgroup isEqualType 0) then {round _channelTalkgroup} else {round parseNumber _channelTalkgroup};
    };
};
if !(_talkgroup isEqualType 0) then {_talkgroup = round parseNumber _talkgroup};
_talkgroup = _talkgroup max 1 min 16;

private _ear = "BOTH";
if (!(isNil "Iceman_fnc_wr_getMonitorEar")) then {
    _ear = [_talkgroup] call Iceman_fnc_wr_getMonitorEar;
};

private _position = switch (toUpperANSI _ear) do {
    case "L": {[-2, 0, 0]};
    case "LEFT": {[-2, 0, 0]};
    case "R": {[2, 0, 0]};
    case "RIGHT": {[2, 0, 0]};
    default {[0, 0, 0]};
};

[_sound, _position, [0, 1, 0], _volume, false] call acre_sys_sounds_fnc_playSound;
true
