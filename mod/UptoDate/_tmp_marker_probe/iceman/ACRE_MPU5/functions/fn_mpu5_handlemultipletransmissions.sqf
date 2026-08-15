params ["_radioId", "_event", "_radios", "_radioData"];

if (!(isNil "acre_sys_radio_fnc_canUnitReceive") && {!([_radioId] call acre_sys_radio_fnc_canUnitReceive)}) exitWith {[]};
if (hasInterface && {!(isNil "Iceman_fnc_wr_hasRadio")} && {!([player] call Iceman_fnc_wr_hasRadio)}) exitWith {[]};

private _pttDown = false;
if (!(isNil "acre_sys_data_fnc_getScratchData")) then {
    _pttDown = [_radioId, "PTTDown", false] call acre_sys_data_fnc_getScratchData;
};
private _fullDuplex = missionNamespace getVariable ["acre_sys_core_fullDuplex", false];
if (_pttDown && {!_fullDuplex}) exitWith {[]};

private _rxData = [_radioId, "getCurrentChannelData"] call acre_sys_data_fnc_dataEvent;
private _wrState = if (!(isNil "Iceman_fnc_wr_getState")) then {call Iceman_fnc_wr_getState} else {createHashMapFromArray []};
private _localFreq = _wrState getOrDefault ["frequency", player getVariable ["Iceman_WR_frequency", "32.0"]];
private _localFreqNumber = if (_localFreq isEqualType 0) then {_localFreq} else {parseNumber _localFreq};
if (_localFreqNumber <= 0) then {_localFreqNumber = 32};

private _rawMonitorTalkgroups = _wrState getOrDefault ["monitorTalkgroups", player getVariable ["Iceman_WR_monitorTalkgroups", []]];
if !(_rawMonitorTalkgroups isEqualType []) then {_rawMonitorTalkgroups = []};
private _monitorTalkgroups = [];
{
    private _tg = if (_x isEqualType 0) then {round _x} else {round parseNumber _x};
    if (_tg >= 1 && {_tg <= 16}) then {
        _monitorTalkgroups pushBackUnique _tg;
    };
} forEach _rawMonitorTalkgroups;
private _bridgeMonitorData = player getVariable ["Iceman_Bridge_monitorChannelData", []];
private _localRadioList = if !(isNil "acre_api_fnc_getCurrentRadioList") then {[] call acre_api_fnc_getCurrentRadioList} else {[]};
private _isBridgeChannel = {
    params ["_data"];
    (_data isEqualType locationNull) && {!isNull _data} && {
        (_data getVariable ["Iceman_Bridge_enabled", false]) ||
        {_data getVariable ["Iceman_WR_bridgeEnabled", false]}
    }
};
private _matchesChannel = {
    params ["_candidateRxData", "_txData"];

    if !(_candidateRxData isEqualType locationNull) exitWith {false};
    if (isNull _candidateRxData) exitWith {false};

    private _rxFreq = _candidateRxData getVariable ["frequencyRX", -1];
    private _rxMode = _candidateRxData getVariable ["mode", "singleChannel"];
    private _rxModulation = _candidateRxData getVariable ["modulation", "FM"];
    private _rxEncryption = _candidateRxData getVariable ["encryption", 0];
    private _rxTek = _candidateRxData getVariable ["TEK", 1];
    private _rxTraffic = _candidateRxData getVariable ["trafficRate", 16];
    private _rxCtcss = _candidateRxData getVariable ["CTCSSRx", 0];

    private _txFrequency = _txData getVariable ["frequencyTX", -2];
    private _frequencyMatches = (_txFrequency isEqualType 0) && {_rxFreq isEqualType 0} && {abs (_txFrequency - _rxFreq) <= 0.001};
    private _matches = (_rxMode == "singleChannel") &&
        {(_txData getVariable ["mode", "singleChannel"]) == "singleChannel"} &&
        {_frequencyMatches} &&
        {(_txData getVariable ["modulation", "FM"]) == _rxModulation} &&
        {(_txData getVariable ["encryption", 0]) == _rxEncryption};

    if (_matches && {_rxEncryption == 1}) then {
        _matches = ((_txData getVariable ["TEK", -1]) == _rxTek) && {(_txData getVariable ["trafficRate", -1]) == _rxTraffic};
    };

    if (_matches && {_rxEncryption == 0} && {_rxCtcss != 0}) then {
        _matches = _rxCtcss == (_txData getVariable ["CTCSSTx", 0]);
    };

    _matches
};
private _squelchBase = {
    params ["_candidateRxData"];

    private _legacyBase = switch (_candidateRxData getVariable ["Iceman_Bridge_radioClass", ""]) do {
        case "ACRE_PRC152": {-118};
        case "ACRE_PRC117F": {-118};
        default {-120};
    };

    -120 min _legacyBase
};
private _wrNodesCache = [];
private _wrNodesReady = false;
private _waveRelayReachable = {
    params [["_txUnit", objNull]];
    if (isNull _txUnit) exitWith {false};

    private _directRange = missionNamespace getVariable ["Iceman_WR_rangeM", 3000];
    if ((player distance _txUnit) <= _directRange) exitWith {true};

    if (isNil "Iceman_fnc_wr_collectNodes") exitWith {false};

    if (!_wrNodesReady) then {
        _wrNodesCache = call Iceman_fnc_wr_collectNodes;
        _wrNodesReady = true;
    };

    (_wrNodesCache findIf {
        ((_x get "unit") isEqualTo _txUnit) &&
        {(_x getOrDefault ["hops", -1]) >= 0}
    }) >= 0
};
private _passesSignal = {
    params ["_candidateRxData", "_signalData", ["_networkReachable", false]];

    if (_networkReachable) exitWith {true};
    if !(_signalData isEqualType [] && {(count _signalData) >= 2}) exitWith {true};
    if (_signalData isEqualTo [0, -992]) exitWith {false};

    private _signalDbM = _signalData # 1;
    private _squelch = ([_candidateRxData] call _squelchBase) + (_candidateRxData getVariable ["squelch", 0]);
    _signalDbM >= _squelch
};
private _okRadios = [];
private _currentTxIds = [];
private _bestSignal = 0;
private _bestEar = "BOTH";
private _bestVolumeScale = 1;
private _diagnosticsEnabled = missionNamespace getVariable ["Iceman_MPU5_diagnostics", false];
private _diagnosticRows = [];

private _earToSpatial = {
    params [["_ear", "BOTH"]];
    switch (toUpperANSI _ear) do {
        case "L": {-1};
        case "LEFT": {-1};
        case "R": {1};
        case "RIGHT": {1};
        default {0};
    }
};

private _earToSoundPosition = {
    params [["_ear", "BOTH"]];
    switch (toUpperANSI _ear) do {
        case "L": {[-2, 0, 0]};
        case "LEFT": {[-2, 0, 0]};
        case "R": {[2, 0, 0]};
        case "RIGHT": {[2, 0, 0]};
        default {[0, 0, 0]};
    }
};

{
    _x params [["_txUnit", objNull], "_txId", ["_signalData", [0, -992]]];
    private _txData = [_txId, "getCurrentChannelData"] call acre_sys_data_fnc_dataEvent;
    private _matches = [_rxData, _txData] call _matchesChannel;
    private _matchedRxData = locationNull;
    private _matchedTalkgroup = -1;
    if (_matches) then {_matchedRxData = _rxData};

    private _txTalkgroup = _txData getVariable ["Iceman_WR_talkgroup", -1];
    private _txFrequencyBank = _txData getVariable ["Iceman_WR_frequencyBank", ""];
    if (_txTalkgroup isEqualType "") then {_txTalkgroup = round parseNumber _txTalkgroup};
    _txTalkgroup = round _txTalkgroup;

    if ((_txTalkgroup < 1 || {_txTalkgroup > 16}) && {!isNull _txUnit}) then {
        private _unitTalkgroup = _txUnit getVariable ["Iceman_WR_activeTG", -1];
        _txTalkgroup = if (_unitTalkgroup isEqualType 0) then {round _unitTalkgroup} else {round parseNumber _unitTalkgroup};
    };
    if ((_txFrequencyBank isEqualTo "") && {!isNull _txUnit}) then {
        _txFrequencyBank = _txUnit getVariable ["Iceman_WR_frequency", ""];
    };

    if (_txTalkgroup < 1 || {_txTalkgroup > 16}) then {
        private _txFrequency = _txData getVariable ["frequencyTX", -1];
        if (_txFrequency isEqualType 0 && {_txFrequency > 0}) then {
            private _candidateTalkgroup = round ((_txFrequency - _localFreqNumber) / 0.025) + 1;
            private _expectedFrequency = _localFreqNumber + ((_candidateTalkgroup - 1) * 0.025);
            if (_candidateTalkgroup >= 1 && {_candidateTalkgroup <= 16} && {abs (_txFrequency - _expectedFrequency) <= 0.001}) then {
                _txTalkgroup = _candidateTalkgroup;
                _txFrequencyBank = _localFreq;
            };
        };
    };

    private _isWaveRelayTalkgroup = (_txTalkgroup >= 1) && {_txTalkgroup <= 16} && {!(_txData getVariable ["Iceman_WR_bridgeEnabled", false])};
    private _isEmergencyTalkgroup = _txTalkgroup == 16;
    private _sameWaveRelayBank = if (_txFrequencyBank isEqualTo "") then {
        true
    } else {
        private _txBankNumber = if (_txFrequencyBank isEqualType 0) then {_txFrequencyBank} else {parseNumber _txFrequencyBank};
        abs (_txBankNumber - _localFreqNumber) <= 0.001
    };
    if (_isWaveRelayTalkgroup && {!_isEmergencyTalkgroup} && {!(_txTalkgroup in _monitorTalkgroups)}) then {
        _matches = false;
        _matchedRxData = locationNull;
    };

    if (!_matches && {(_txTalkgroup in _monitorTalkgroups) || {_isEmergencyTalkgroup}} && {_sameWaveRelayBank}) then {
        _matches = true;
        _matchedRxData = _txData;
        _matchedTalkgroup = _txTalkgroup;
    };

    if (!_matches && {_bridgeMonitorData isEqualType []}) then {
        {
            if (!_matches && {_x isEqualType locationNull} && {!isNull _x}) then {
                _matches = [_x, _txData] call _matchesChannel;
                if (_matches) then {
                    _matchedRxData = _x;
                    _matchedTalkgroup = _x getVariable ["Iceman_WR_talkgroup", _txTalkgroup];
                };
            };
        } forEach _bridgeMonitorData;
    };

    if (_matchedTalkgroup < 1) then {
        _matchedTalkgroup = _matchedRxData getVariable ["Iceman_WR_talkgroup", _txTalkgroup];
        if (_matchedTalkgroup isEqualType "") then {_matchedTalkgroup = round parseNumber _matchedTalkgroup};
        _matchedTalkgroup = round _matchedTalkgroup;
    };

    private _roipLinkId = _matchedRxData getVariable ["Iceman_ROIP_linkId", ""];
    private _roipGatewayRadioId = _matchedRxData getVariable ["Iceman_ROIP_gatewayRadioId", ""];
    private _roipOwnerUid = _matchedRxData getVariable ["Iceman_ROIP_gatewayOwnerUID", ""];
    if (_matches && {_roipOwnerUid != ""} && {_roipOwnerUid == getPlayerUID player} && {_roipGatewayRadioId in _localRadioList}) then {
        private _gatewayOn = [_roipGatewayRadioId, "getOnOffState"] call acre_sys_data_fnc_dataEvent;
        if (!isNil "_gatewayOn" && {_gatewayOn == 1}) then {
            _matches = false;
            _matchedRxData = locationNull;
        };
    };

    private _networkReachable = false;
    private _accepted = false;
    if (_matches) then {
        _networkReachable = [_txUnit] call _waveRelayReachable;
        if (
            !_networkReachable &&
            {_roipLinkId != ""} &&
            {!(_txData getVariable ["Iceman_ROIP_enabled", false])} &&
            {!(isNil "Iceman_fnc_roip_canReceiveLegacy")}
        ) then {
            _networkReachable = [_roipLinkId, _txId, _txData, _txUnit] call Iceman_fnc_roip_canReceiveLegacy;
        };
        if ([_matchedRxData, _signalData, _networkReachable] call _passesSignal) then {
            _accepted = true;
            private _entry = +_x;
            private _signalPercent = if (_signalData isEqualType [] && {(count _signalData) > 0}) then {_signalData # 0} else {0};
            if (_networkReachable) then {
                _signalPercent = 85 max _signalPercent;
                _entry set [2, [_signalPercent, -48]];
            };
            if (_matchedTalkgroup >= 1 && {_matchedTalkgroup <= 16}) then {
                if (!(isNil "Iceman_fnc_wr_getMonitorEar")) then {
                    _bestEar = [_matchedTalkgroup] call Iceman_fnc_wr_getMonitorEar;
                    [_radioId, "setSpatial", [_bestEar] call _earToSpatial] call acre_sys_data_fnc_dataEvent;
                };
                if (!(isNil "Iceman_fnc_wr_getMonitorVolume")) then {
                    _bestVolumeScale = [_matchedTalkgroup] call Iceman_fnc_wr_getMonitorVolume;
                };
                if (!(isNil "acre_sys_data_fnc_setScratchData")) then {
                    [_radioId, "Iceman_WR_rxEar", _bestEar] call acre_sys_data_fnc_setScratchData;
                    [_radioId, "Iceman_WR_rxVolumeScale", _bestVolumeScale] call acre_sys_data_fnc_setScratchData;
                };
            };
            _bestSignal = _bestSignal max _signalPercent;
            _okRadios pushBack _entry;
            _currentTxIds pushBackUnique _txId;
        };
    };

    if (_diagnosticsEnabled) then {
        _diagnosticRows pushBack [
            if (isNull _txUnit) then {"UNKNOWN"} else {name _txUnit},
            _txId,
            _txTalkgroup,
            _txFrequencyBank,
            _signalData,
            if (isNull _txUnit) then {-1} else {round (player distance _txUnit)},
            _matches,
            _networkReachable,
            _accepted
        ];
    };
} forEach _radios;

if (_diagnosticsEnabled && {_diagnosticRows isNotEqualTo []} && {!(isNil "acre_sys_data_fnc_getScratchData")} && {!(isNil "acre_sys_data_fnc_setScratchData")}) then {
    private _signature = str [_localFreq, _monitorTalkgroups, _diagnosticRows];
    private _lastSignature = [_radioId, "Iceman_MPU5_diagSignature", ""] call acre_sys_data_fnc_getScratchData;
    private _lastTime = [_radioId, "Iceman_MPU5_diagTime", -10] call acre_sys_data_fnc_getScratchData;
    if (_signature != _lastSignature || {diag_tickTime - _lastTime >= 5}) then {
        diag_log format [
            "[Iceman MPU5] RX route radio=%1 bank=%2 monitors=%3 accepted=%4/%5 rows=%6",
            _radioId,
            _localFreq,
            _monitorTalkgroups,
            count _okRadios,
            count _radios,
            _diagnosticRows
        ];
        [_radioId, "Iceman_MPU5_diagSignature", _signature] call acre_sys_data_fnc_setScratchData;
        [_radioId, "Iceman_MPU5_diagTime", diag_tickTime] call acre_sys_data_fnc_setScratchData;
    };
};

if (!(isNil "acre_sys_data_fnc_setScratchData")) then {
    [_radioId, "receivingSignal", if (_okRadios isEqualTo []) then {0} else {_bestSignal max 1}] call acre_sys_data_fnc_setScratchData;
    [_radioId, "currentTransmissions", _currentTxIds] call acre_sys_data_fnc_setScratchData;

    private _beeped = false;
    if (!(isNil "acre_sys_data_fnc_getScratchData")) then {
        _beeped = [_radioId, "hasBeeped", false] call acre_sys_data_fnc_getScratchData;
    };

    if (_okRadios isEqualTo []) then {
        if (_beeped && {!_pttDown}) then {
            private _volume = [_radioId, "getVolume"] call acre_sys_data_fnc_dataEvent;
            _volume = _volume * _bestVolumeScale;
            if (!(isNil "acre_sys_radio_fnc_playRadioSound")) then {
                [_radioId, "Acre_GenericClickOff", [_bestEar] call _earToSoundPosition, [0, 1, 0], _volume] call acre_sys_radio_fnc_playRadioSound;
            };
        };
        [_radioId, "hasBeeped", false] call acre_sys_data_fnc_setScratchData;
    } else {
        if (!_beeped) then {
            private _volume = [_radioId, "getVolume"] call acre_sys_data_fnc_dataEvent;
            _volume = _volume * _bestVolumeScale;
            if (!(isNil "acre_sys_radio_fnc_playRadioSound")) then {
                [_radioId, "Acre_GenericClickOn", [_bestEar] call _earToSoundPosition, [0, 1, 0], _volume] call acre_sys_radio_fnc_playRadioSound;
            };
            [_radioId, "hasBeeped", true] call acre_sys_data_fnc_setScratchData;
        };
    };
};

_okRadios
