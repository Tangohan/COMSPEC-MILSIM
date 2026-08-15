params [["_slots", []]];

private _clean = if (_slots isEqualType []) then {
    [_slots] call Iceman_fnc_wr_getTxSlots
} else {
    call Iceman_fnc_wr_getTxSlots
};

private _parts = [];
for "_i" from 0 to 3 do {
    private _tg = _clean # _i;
    if (_tg > 0) then {
        _parts pushBack format ["TX%1 TG-%2", _i + 1, _tg];
    };
};

if (_parts isEqualTo []) exitWith {"None"};
_parts joinString ", "
