params ["_txUnit", "_txRadioId", "_rxUnit", "_rxRadioId"];

private _txData = [_txRadioId, "getCurrentChannelData"] call acre_sys_data_fnc_dataEvent;
if !(_txData getVariable ["Iceman_ROIP_enabled", false]) exitWith {
    [_txUnit, _txRadioId, _rxUnit, _rxRadioId] call acre_sys_modes_fnc_sc_speaking
};

private _rxBase = "";
if !(isNil "acre_sys_radio_fnc_getRadioBaseClassname") then {
    _rxBase = [_rxRadioId] call acre_sys_radio_fnc_getRadioBaseClassname;
};
if (_rxBase == "ACRE_MPU5") exitWith {
    [_txUnit, _txRadioId, _rxUnit, _rxRadioId] call acre_sys_modes_fnc_sc_speaking
};

private _linkId = _txData getVariable ["Iceman_ROIP_linkId", ""];
if (!(isNil "Iceman_fnc_roip_isLinkActive") && {!([_linkId] call Iceman_fnc_roip_isLinkActive)}) exitWith {
    [_txUnit, _txRadioId, _rxUnit, _rxRadioId] call acre_sys_modes_fnc_sc_speaking
};

private _gatewayRadioId = _txData getVariable ["Iceman_ROIP_gatewayRadioId", ""];
if (_gatewayRadioId == "" || {isNil "acre_sys_signal_fnc_getSignal"}) exitWith {
    [_txUnit, _txRadioId, _rxUnit, _rxRadioId] call acre_sys_modes_fnc_sc_speaking
};

private _gatewayAvailable = true;
if !(isNil "acre_sys_radio_fnc_getRadioObject") then {
    private _gatewayObject = [_gatewayRadioId] call acre_sys_radio_fnc_getRadioObject;
    _gatewayAvailable = !(isNil "_gatewayObject") && {!isNull _gatewayObject};
};
if (!_gatewayAvailable) exitWith {
    [_txUnit, _txRadioId, _rxUnit, _rxRadioId] call acre_sys_modes_fnc_sc_speaking
};

private _signal = [1, 0];
if (_rxRadioId != _gatewayRadioId) then {
    private _frequency = _txData getVariable ["frequencyTX", 32];
    private _power = _txData getVariable ["Iceman_ROIP_effectivePower", _txData getVariable ["power", 5000]];
    _signal = [_frequency, _power, _rxRadioId, _gatewayRadioId] call acre_sys_signal_fnc_getSignal;
};

[_txRadioId, _rxRadioId, _signal # 0, _signal # 1, 0]
