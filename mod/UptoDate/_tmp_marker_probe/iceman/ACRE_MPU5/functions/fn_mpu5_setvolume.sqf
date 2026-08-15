params ["_radioId", "_event", "_eventData", "_radioData"];

private _volume = (_eventData max 0) min 1;
_radioData setVariable ["volume", _volume];
true
