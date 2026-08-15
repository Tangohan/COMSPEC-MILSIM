params [["_talkgroup", 1]];

private _state = call Iceman_fnc_wr_getState;
private _tg = (round _talkgroup) max 1 min 16;
private _volumes = +(_state getOrDefault ["monitorVolume", []]);
private _volume = 1;

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
    private _rawVol = (_volumes # _idx) # 1;
    _volume = if (_rawVol isEqualType 0) then {_rawVol} else {parseNumber _rawVol};
};

0 max (1 min _volume)
