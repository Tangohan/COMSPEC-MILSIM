params ["_radioId", "_event", "_eventData", "_radioData"];

private _spatial = 0;
if (_eventData isEqualType "") then {
    private _upper = toUpper _eventData;
    if (_upper == "LEFT") then {_spatial = -1};
    if (_upper == "RIGHT") then {_spatial = 1};
} else {
    _spatial = _eventData;
};

_radioData setVariable ["ACRE_INTERNAL_RADIOSPATIALIZATION", _spatial];
true
