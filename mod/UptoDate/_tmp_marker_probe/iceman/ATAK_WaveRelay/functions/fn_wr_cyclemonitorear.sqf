params [["_talkgroup", 1]];

private _state = call Iceman_fnc_wr_getState;
private _tg = (round _talkgroup) max 1 min 16;
private _monitors = +(_state getOrDefault ["monitorTalkgroups", [1, 2]]);
private _wasMonitored = _tg in _monitors;

if (!_wasMonitored) then {
    _monitors pushBackUnique _tg;
    _monitors sort true;
    _state set ["monitorTalkgroups", _monitors];
};

private _current = [_tg] call Iceman_fnc_wr_getMonitorEar;
private _next = if (!_wasMonitored) then {
    "BOTH"
} else {
    switch (_current) do {
        case "BOTH": {"L"};
        case "L": {"R"};
        default {"BOTH"};
    }
};

private _audio = +(_state getOrDefault ["monitorAudio", []]);
private _idx = _audio findIf {
    _x isEqualType [] &&
    {(count _x) >= 2} &&
    {
        private _rawTg = _x # 0;
        private _entryTg = if (_rawTg isEqualType 0) then {round _rawTg} else {round parseNumber _rawTg};
        _entryTg == _tg
    }
};

if (_idx >= 0) then {
    _audio set [_idx, [_tg, _next]];
} else {
    _audio pushBack [_tg, _next];
};

_state set ["monitorAudio", _audio];
_next
