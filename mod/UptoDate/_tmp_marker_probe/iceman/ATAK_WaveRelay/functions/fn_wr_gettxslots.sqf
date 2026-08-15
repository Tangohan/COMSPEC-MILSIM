params [["_source", objNull]];

private _state = call Iceman_fnc_wr_getState;
private _useState = !(_source isEqualType []);
private _raw = if (_useState) then {
    _state getOrDefault ["txSlots", []]
} else {
    +_source
};

if (_raw isEqualTo []) then {
    _raw = +(_state getOrDefault ["txTalkgroups", [1]]);
};

private _slots = [0, 0, 0, 0];
private _looksLikeSlotMap = ((count _raw) == 4) && {
    (_raw findIf {
        private _n = if (_x isEqualType 0) then {round _x} else {round parseNumber _x};
        _n == 0
    }) >= 0
};

if (_looksLikeSlotMap) then {
    for "_i" from 0 to 3 do {
        private _tg = if ((_raw # _i) isEqualType 0) then {round (_raw # _i)} else {round parseNumber (_raw # _i)};
        if (_tg >= 1 && {_tg <= 16}) then {
            _slots set [_i, _tg];
        };
    };
} else {
    private _i = 0;
    {
        private _tg = if (_x isEqualType 0) then {round _x} else {round parseNumber _x};
        if (_tg >= 1 && {_tg <= 16} && {_i < 4}) then {
            _slots set [_i, _tg];
            _i = _i + 1;
        };
    } forEach _raw;
};

private _seen = [];
for "_i" from 0 to 3 do {
    private _tg = _slots # _i;
    if (_tg <= 0 || {_tg in _seen}) then {
        _slots set [_i, 0];
    } else {
        _seen pushBack _tg;
    };
};

if (_useState) then {
    _state set ["txSlots", +_slots];
    _state set ["txTalkgroups", _slots select {_x > 0}];
};

_slots
