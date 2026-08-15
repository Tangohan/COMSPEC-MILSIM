params [
    ["_key", -1, [0]],
    ["_modifiers", [false, false, false], [[]]],
    ["_down", true, [true]]
];

if (_key < 0) exitWith {false};
_modifiers = [
    _modifiers param [0, false],
    _modifiers param [1, false],
    _modifiers param [2, false]
];

private _state = call Iceman_fnc_wr_getState;
private _bindings = +(_state getOrDefault ["pttKeyBindings", []]);
if (_bindings isEqualTo []) then {_bindings = call Iceman_fnc_wr_cachePttKeybinds};
private _active = +(_state getOrDefault ["ctabPttActive", []]);

if (_down) exitWith {
    private _index = _bindings findIf {(_x # 1) == _key && {(_x # 2) isEqualTo _modifiers}};
    if (_index < 0) exitWith {false};

    private _binding = _bindings # _index;
    private _slot = _binding # 0;
    private _slots = call Iceman_fnc_wr_getTxSlots;
    if ((_slots # (_slot - 1)) <= 0) exitWith {false};

    private _handled = [_slot, true] call Iceman_fnc_wr_keyTx;
    _handled = _handled || {_slot in (_state getOrDefault ["txKeysDown", []])};
    if (_handled && {(_active findIf {(_x # 0) == _slot && {(_x # 1) == _key}}) < 0}) then {
        _active pushBack [_slot, _key, +(_binding # 2)];
        _state set ["ctabPttActive", _active];
    };

    _handled
};

private _releaseSlots = [];
private _remaining = [];
{
    _x params ["_slot", "_baseKey", "_requiredModifiers"];
    private _modifierReleased =
        (_key in [42, 54] && {_requiredModifiers # 0}) ||
        {_key in [29, 157] && {_requiredModifiers # 1}} ||
        {_key in [56, 184] && {_requiredModifiers # 2}};

    if (_key == _baseKey || {_modifierReleased}) then {
        _releaseSlots pushBackUnique _slot;
    } else {
        _remaining pushBack _x;
    };
} forEach _active;

_state set ["ctabPttActive", _remaining];
private _handled = false;
{
    if ([_x, false] call Iceman_fnc_wr_keyTx) then {_handled = true};
} forEach _releaseSlots;

_handled
