params ["_radioId", "_event", "_eventData", "_radioData"];

_radioData setVariable ["radioOn", if (_eventData isEqualTo 0) then {0} else {1}];
true
