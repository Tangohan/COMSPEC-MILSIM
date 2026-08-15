params ["_radioId"];

if (!(isNil "acre_sys_radio_fnc_canUnitTransmit")) then {
    if !([_radioId] call acre_sys_radio_fnc_canUnitTransmit) exitWith {false};
};

private _channelNumber = [_radioId, "getCurrentChannel"] call acre_sys_data_fnc_dataEvent;
private _channels = [_radioId, "getState", "channels"] call acre_sys_data_fnc_dataEvent;
if (isNil "_channels" || {!(_channels isEqualType [])} || {_channels isEqualTo []}) exitWith {false};

private _channel = _channels # ((0 max _channelNumber) min ((count _channels) - 1));
if (_channel getVariable ["rxOnly", false]) exitWith {false};

private _volume = [_radioId, "getVolume"] call acre_sys_data_fnc_dataEvent;
[_radioId, "Acre_GenericBeep", _volume] call Iceman_fnc_mpu5_playTalkgroupCue;
if (!(isNil "acre_sys_data_fnc_setScratchData")) then {
    [_radioId, "PTTDown", true] call acre_sys_data_fnc_setScratchData;
};

true
