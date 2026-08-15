params ["_radioId"];

private _volume = [_radioId, "getVolume"] call acre_sys_data_fnc_dataEvent;
[_radioId, "Acre_GenericClickOff", _volume] call Iceman_fnc_mpu5_playTalkgroupCue;
if (!(isNil "acre_sys_data_fnc_setScratchData")) then {
    [_radioId, "PTTDown", false] call acre_sys_data_fnc_setScratchData;
};

true
