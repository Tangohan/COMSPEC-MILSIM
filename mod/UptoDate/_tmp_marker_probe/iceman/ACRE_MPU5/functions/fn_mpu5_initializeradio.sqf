params ["_radioId", "_event", "_eventData", "_radioData"];

_eventData params [["_baseName", "ACRE_MPU5"], ["_preset", "default"]];

private _presetData = nil;
if (!(isNil "acre_sys_data_fnc_getPresetData")) then {
    _presetData = [_baseName, _preset] call acre_sys_data_fnc_getPresetData;
};
if (isNil "_presetData") then {
    _presetData = [32] call Iceman_fnc_mpu5_buildPreset;
};

private _channels = [];
{
    _channels pushBack ([_x] call Iceman_fnc_mpu5_cloneChannel);
} forEach (_presetData getVariable ["channels", []]);

_radioData setVariable ["channels", _channels];
_radioData setVariable ["volume", missionNamespace getVariable ["acre_sys_core_defaultRadioVolume", 0.8]];
_radioData setVariable ["currentChannel", 0];
_radioData setVariable ["radioOn", 1];
_radioData setVariable ["audioPath", "TOPAUDIO"];
_radioData setVariable ["powerSource", "BAT"];
_radioData setVariable ["ACRE_INTERNAL_RADIOSPATIALIZATION", 0];

if (!(isNil "acre_sys_data_fnc_setScratchData")) then {
    [_radioId, "currentTransmissions", []] call acre_sys_data_fnc_setScratchData;
    [_radioId, "PTTDown", false] call acre_sys_data_fnc_setScratchData;
    [_radioId, "receivingSignal", 0] call acre_sys_data_fnc_setScratchData;
};

true
