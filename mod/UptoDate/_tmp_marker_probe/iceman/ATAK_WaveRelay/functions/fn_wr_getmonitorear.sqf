params [["_talkgroup", 1]];

private _state = call Iceman_fnc_wr_getState;
private _tg = (round _talkgroup) max 1 min 16;
private _audio = +(_state getOrDefault ["monitorAudio", []]);
private _ear = "BOTH";

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
    _ear = toUpperANSI ((_audio # _idx) # 1);
};

if (!(_ear in ["L", "R", "BOTH", "LEFT", "RIGHT", "CENTER"])) then {
    _ear = "BOTH";
};

switch (_ear) do {
    case "LEFT": {"L"};
    case "RIGHT": {"R"};
    case "CENTER": {"BOTH"};
    default {_ear};
}
