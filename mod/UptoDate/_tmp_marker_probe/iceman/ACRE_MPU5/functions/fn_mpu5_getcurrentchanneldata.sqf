params ["_radioId", "_event", "_eventData", "_radioData"];

private _channels = _radioData getVariable ["channels", []];
if (_channels isEqualTo []) exitWith {call acre_main_fnc_fastHashCreate};

private _channelNumber = (_radioData getVariable ["currentChannel", 0]) max 0 min ((count _channels) - 1);
[_channels # _channelNumber] call Iceman_fnc_mpu5_publicChannelData
