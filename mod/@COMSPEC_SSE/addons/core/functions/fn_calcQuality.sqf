/*
    Calcule un score de qualité 0-100.
    [_base, _hasKit, _durationFactor, _objectCondition] call comspec_sse_fnc_calcQuality
*/
params [
    ["_base", 50, [0]],
    ["_hasKit", true, [true]],
    ["_durationFactor", 1, [0]],
    ["_objectCondition", 1, [0]]
];

private _q = _base;
if (_hasKit) then { _q = _q + 20 } else { _q = _q - 15 };
_q = _q * (0.6 + 0.4 * (_durationFactor min 1.5));
_q = _q * (_objectCondition max 0.2 min 1.2);
_q = (_q max 5) min 99;
round _q
