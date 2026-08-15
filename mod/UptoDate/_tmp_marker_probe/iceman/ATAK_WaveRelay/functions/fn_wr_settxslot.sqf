params [["_talkgroup", 1], ["_slot", 0]];

private _state = call Iceman_fnc_wr_getState;
private _tg = (round _talkgroup) max 1 min 16;
private _targetSlot = (round _slot) max 0 min 4;
private _slots = call Iceman_fnc_wr_getTxSlots;

for "_i" from 0 to 3 do {
    if ((_slots # _i) == _tg || {(_targetSlot > 0) && {_i == (_targetSlot - 1)}}) then {
        _slots set [_i, 0];
    };
};

if (_targetSlot > 0) then {
    _slots set [_targetSlot - 1, _tg];
    _state set ["activeTalkgroup", _tg];
};

_state set ["txSlots", +_slots];
_state set ["txTalkgroups", _slots select {_x > 0}];
if (_targetSlot > 0) then {
    _state set ["activeTalkgroup", _tg];
} else {
    private _first = _slots findIf {_x > 0};
    _state set ["activeTalkgroup", if (_first >= 0) then {_slots # _first} else {1}];
};
player setVariable ["Iceman_WR_txSlots", +_slots, true];
player setVariable ["Iceman_WR_txTalkgroups", +(_slots select {_x > 0}), true];

_targetSlot
