params [["_talkgroup", 1], ["_delta", 0]];

private _state = call Iceman_fnc_wr_getState;
private _tg = (round _talkgroup) max 1 min 16;
private _monitors = +(_state getOrDefault ["monitorTalkgroups", [1, 2]]);
if !(_tg in _monitors) then {
    _monitors pushBackUnique _tg;
    _monitors sort true;
    _state set ["monitorTalkgroups", _monitors];
};

private _current = [_tg] call Iceman_fnc_wr_getMonitorVolume;
private _next = _current + _delta;
_next = 0.25 max (1 min _next);
_next = round (_next * 4) / 4;

private _volumes = +(_state getOrDefault ["monitorVolume", []]);
private _idx = _volumes findIf {
    _x isEqualType [] &&
    {(count _x) >= 2} &&
    {
        private _rawTg = _x # 0;
        private _entryTg = if (_rawTg isEqualType 0) then {round _rawTg} else {round parseNumber _rawTg};
        _entryTg == _tg
    }
};

if (_idx >= 0) then {
    _volumes set [_idx, [_tg, _next]];
} else {
    _volumes pushBack [_tg, _next];
};

_state set ["monitorVolume", _volumes];
_next
