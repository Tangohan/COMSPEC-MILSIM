params ["_radioId", "_event", "_eventData", "_radioData"];

private _channels = _radioData getVariable ["channels", []];
private _maxChannel = ((count _channels) - 1) max 0;
private _channel = (round _eventData) max 0 min _maxChannel;

_radioData setVariable ["currentChannel", _channel];
true
