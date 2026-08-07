/*
    Hash déterministe simple (djb2-like) pour seeds et picks.
    [_seed, _salt] call comspec_sse_fnc_hash -> NUMBER
*/
params [
    ["_seed", 0, [0]],
    ["_salt", 0, [0, ""]]
];

private _saltNum = if (_salt isEqualType "") then {
    private _acc = 5381;
    {
        _acc = ((_acc * 33) + _x) mod 2147483647;
    } forEach (toArray _salt);
    _acc
} else {
    round _salt
};

private _h = (round _seed) + 5381;
_h = ((_h * 33) + _saltNum) mod 2147483647;
if (_h < 0) then { _h = _h + 2147483647; };
_h
