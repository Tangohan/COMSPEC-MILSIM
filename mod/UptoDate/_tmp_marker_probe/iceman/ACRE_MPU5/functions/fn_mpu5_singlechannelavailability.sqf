params ["_txRadioId", "_rxRadioId"];

private _getBase = {
    params ["_radioId"];
    if !(isNil "acre_sys_radio_fnc_getRadioBaseClassname") exitWith {
        [_radioId] call acre_sys_radio_fnc_getRadioBaseClassname
    };
    if !(isNil "acre_api_fnc_getBaseRadio") exitWith {
        [_radioId] call acre_api_fnc_getBaseRadio
    };
    ""
};

private _txBase = [_txRadioId] call _getBase;
private _rxBase = [_rxRadioId] call _getBase;
private _txIsMpu5 = _txBase == "ACRE_MPU5";
private _rxIsMpu5 = _rxBase == "ACRE_MPU5";

if (!_txIsMpu5 && {!_rxIsMpu5}) exitWith {
    [_txRadioId, _rxRadioId] call acre_sys_modes_fnc_sc_muting
};

private _txData = [_txRadioId, "getCurrentChannelData"] call acre_sys_data_fnc_dataEvent;
private _rxData = [_rxRadioId, "getCurrentChannelData"] call acre_sys_data_fnc_dataEvent;

private _channelsMatch = {
    params ["_candidateRx", "_candidateTx"];
    if !(_candidateRx isEqualType locationNull && {!isNull _candidateRx}) exitWith {false};
    if !(_candidateTx isEqualType locationNull && {!isNull _candidateTx}) exitWith {false};

    private _rxFrequency = _candidateRx getVariable ["frequencyRX", -1];
    private _txFrequency = _candidateTx getVariable ["frequencyTX", -2];
    private _matches = _rxFrequency isEqualType 0 && {_txFrequency isEqualType 0} && {abs (_rxFrequency - _txFrequency) <= 0.001};
    _matches = _matches && {(_candidateRx getVariable ["modulation", "FM"]) == (_candidateTx getVariable ["modulation", "FM"])};
    _matches = _matches && {(_candidateRx getVariable ["encryption", 0]) == (_candidateTx getVariable ["encryption", 0])};

    if (_matches && {(_candidateRx getVariable ["encryption", 0]) == 1}) then {
        _matches = (_candidateRx getVariable ["TEK", -1]) == (_candidateTx getVariable ["TEK", -2]);
        _matches = _matches && {(_candidateRx getVariable ["trafficRate", -1]) == (_candidateTx getVariable ["trafficRate", -2])};
    };
    if (_matches && {(_candidateRx getVariable ["encryption", 0]) == 0}) then {
        private _ctcss = _candidateRx getVariable ["CTCSSRx", 0];
        if (_ctcss != 0) then {_matches = _ctcss == (_candidateTx getVariable ["CTCSSTx", -1])};
    };
    _matches
};

if (_txIsMpu5 && {_rxIsMpu5}) exitWith {
    private _txTg = _txData getVariable ["Iceman_WR_talkgroup", -1];
    if (_txTg isEqualType "") then {_txTg = round parseNumber _txTg};
    _txTg = round _txTg;
    if (_txTg < 1 || {_txTg > 16}) exitWith {[_txRadioId, _rxRadioId] call acre_sys_modes_fnc_sc_muting};

    private _txBank = _txData getVariable ["Iceman_WR_frequencyBank", ""];
    private _rxBank = _rxData getVariable ["Iceman_WR_frequencyBank", player getVariable ["Iceman_WR_frequency", ""]];
    private _txBankNumber = if (_txBank isEqualType 0) then {_txBank} else {parseNumber _txBank};
    private _rxBankNumber = if (_rxBank isEqualType 0) then {_rxBank} else {parseNumber _rxBank};
    if (abs (_txBankNumber - _rxBankNumber) > 0.001) exitWith {false};

    private _rxTg = _rxData getVariable ["Iceman_WR_talkgroup", -1];
    if (_rxTg isEqualType "") then {_rxTg = round parseNumber _rxTg};
    private _wrState = if !(isNil "Iceman_fnc_wr_getState") then {call Iceman_fnc_wr_getState} else {createHashMap};
    private _monitors = _wrState getOrDefault ["monitorTalkgroups", player getVariable ["Iceman_WR_monitorTalkgroups", []]];
    if !(_monitors isEqualType []) then {_monitors = []};
    (_txTg == round _rxTg) || {_txTg in _monitors} || {_txTg == 16}
};

if (_txIsMpu5 && {!_rxIsMpu5}) exitWith {
    if !(_txData getVariable ["Iceman_ROIP_enabled", false]) exitWith {false};
    private _linkId = _txData getVariable ["Iceman_ROIP_linkId", ""];
    if (!(isNil "Iceman_fnc_roip_isLinkActive") && {!([_linkId] call Iceman_fnc_roip_isLinkActive)}) exitWith {false};
    [_rxData, _txData] call _channelsMatch
};

if (!_txIsMpu5 && {_rxIsMpu5}) exitWith {
    private _candidates = [];
    if (_rxData getVariable ["Iceman_ROIP_enabled", false]) then {_candidates pushBack _rxData};
    {
        if (_x isEqualType locationNull && {!isNull _x}) then {_candidates pushBackUnique _x};
    } forEach (player getVariable ["Iceman_Bridge_monitorChannelData", []]);

    (_candidates findIf {
        private _linkId = _x getVariable ["Iceman_ROIP_linkId", ""];
        private _active = if (isNil "Iceman_fnc_roip_isLinkActive") then {true} else {[_linkId] call Iceman_fnc_roip_isLinkActive};
        _active && {[_x, _txData] call _channelsMatch}
    }) >= 0
};

[_txRadioId, _rxRadioId] call acre_sys_modes_fnc_sc_muting
