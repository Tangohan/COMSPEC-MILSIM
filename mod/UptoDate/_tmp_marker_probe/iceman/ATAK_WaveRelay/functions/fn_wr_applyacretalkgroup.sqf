params [["_talkgroup", 1]];

if (isNil "acre_api_fnc_setRadioChannel") exitWith {false};

private _state = call Iceman_fnc_wr_getState;
private _radio = _state getOrDefault ["acreLastRadio", player getVariable ["Iceman_WR_radioId", ""]];
if (_radio == "" && {!(isNil "acre_api_fnc_getRadioByType")}) then {
    private _candidate = ["ACRE_MPU5"] call acre_api_fnc_getRadioByType;
    if (!(isNil "_candidate") && {_candidate isEqualType ""}) then {_radio = _candidate};
};
if (_radio == "") exitWith {false};

private _freq = _state getOrDefault ["frequency", "32.0"];
private _tg = (round _talkgroup) max 1 min 16;
private _operator = name player;
if (_operator == "") then {_operator = "Unknown"};

private _expectedSignature = format ["%1|%2|%3", _radio, _freq, _operator];
private _channelsReady = true;
if ((_state getOrDefault ["acreChannelSignature", ""]) != _expectedSignature) then {
    _channelsReady = call Iceman_fnc_wr_syncAcreChannels;
};
if (!_channelsReady) exitWith {false};

private _currentTalkgroup = -1;
if !(isNil "acre_api_fnc_getRadioChannel") then {
    _currentTalkgroup = [_radio] call acre_api_fnc_getRadioChannel;
};
if (_currentTalkgroup != _tg) then {
    if !([_radio, _tg] call acre_api_fnc_setRadioChannel) exitWith {false};
};

_state set ["activeTalkgroup", _tg];
_state set ["acreLastRadio", _radio];
_state set ["acreLastTalkgroup", _tg];

if ((player getVariable ["Iceman_WR_activeTG", -1]) != _tg) then {
    player setVariable ["Iceman_WR_activeTG", _tg, true];
};
if ((player getVariable ["Iceman_WR_radioId", ""]) != _radio) then {
    player setVariable ["Iceman_WR_radioId", _radio, true];
};

true
