params [["_talkgroup", 1]];

private _state = call Iceman_fnc_wr_getState;
private _tg = (round _talkgroup) max 1 min 16;
private _current = [_tg] call Iceman_fnc_wr_getMonitorVolume;
private _next = switch (true) do {
    case (_current < 0.50): {0.50};
    case (_current < 0.75): {0.75};
    case (_current < 1.00): {1.00};
    default {0.25};
};

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
