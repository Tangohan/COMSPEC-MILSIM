params ["_radioId", "_event", "_eventData", "_radioData"];

private _channel = (_radioData getVariable ["currentChannel", 0]) + 1;
format ["MPU-5 TG%1", _channel]
