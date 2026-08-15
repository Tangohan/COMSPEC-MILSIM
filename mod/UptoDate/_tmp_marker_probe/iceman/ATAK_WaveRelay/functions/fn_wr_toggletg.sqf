params [["_kind", "tx"], ["_talkgroup", 1]];

private _state = call Iceman_fnc_wr_getState;
private _tg = (round _talkgroup) max 1 min 16;

if (_kind == "tx") exitWith {
    private _targetSlot = round (_state getOrDefault ["txEditSlot", 1]);
    _targetSlot = _targetSlot max 1 min 4;
    private _currentSlot = [_tg] call Iceman_fnc_wr_txSlotForTg;
    private _nextSlot = if (_currentSlot == _targetSlot) then {0} else {_targetSlot};
    [_tg, _nextSlot] call Iceman_fnc_wr_setTxSlot;
    if (_nextSlot > 0) then {
        _state set ["activeTalkgroup", _tg];
    } else {
        private _slots = call Iceman_fnc_wr_getTxSlots;
        private _first = _slots findIf {_x > 0};
        _state set ["activeTalkgroup", if (_first >= 0) then {_slots # _first} else {1}];
    };
    true
};

private _key = "monitorTalkgroups";
private _list = +(_state getOrDefault [_key, []]);
private _wasEnabled = _tg in _list;
if (_wasEnabled) then {
    _list = _list - [_tg];
} else {
    _list pushBackUnique _tg;
};
_list sort true;

_state set [_key, _list];

if (_kind == "monitor") then {
    private _audio = +(_state getOrDefault ["monitorAudio", []]);
    if (_wasEnabled) then {
        _audio = _audio select {
            _x isEqualType [] &&
            {(count _x) >= 2} &&
            {
                private _rawTg = _x # 0;
                private _entryTg = if (_rawTg isEqualType 0) then {round _rawTg} else {round parseNumber _rawTg};
                _entryTg != _tg
            }
        };
    } else {
        private _idx = _audio findIf {
            _x isEqualType [] &&
            {(count _x) >= 2} &&
            {
                private _rawTg = _x # 0;
                private _entryTg = if (_rawTg isEqualType 0) then {round _rawTg} else {round parseNumber _rawTg};
                _entryTg == _tg
            }
        };
        if (_idx < 0) then {
            _audio pushBack [_tg, "BOTH"];
        };
    };
    _state set ["monitorAudio", _audio];
};

call Iceman_fnc_wr_readUi;
true
