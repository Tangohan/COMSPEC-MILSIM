private _bindings = [];
if (isNil "CBA_fnc_getKeybind") exitWith {_bindings};

for "_slot" from 1 to 4 do {
    private _entry = ["Iceman ATAK", format ["waveRelayTX%1", _slot]] call CBA_fnc_getKeybind;
    if !(isNil "_entry") then {
        private _allBindings = _entry param [8, []];
        {
            if (_x isEqualType [] && {(count _x) >= 2}) then {
                private _key = _x param [0, -1];
                private _modifiers = +(_x param [1, [false, false, false]]);
                if (_key > 0 && {(count _modifiers) >= 3}) then {
                    _bindings pushBackUnique [_slot, _key, _modifiers select [0, 3]];
                };
            };
        } forEach _allBindings;
    };
};

private _state = call Iceman_fnc_wr_getState;
_state set ["pttKeyBindings", _bindings];
_bindings
