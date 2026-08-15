params ["_radioId", "_event", "_eventData", "_radioData"];

private _volume = _radioData getVariable ["volume", missionNamespace getVariable ["acre_sys_core_defaultRadioVolume", 0.8]];
_volume ^ 3
