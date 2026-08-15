params ["_radioId", "_event", "_eventData"];

private _txId = "";
if (_eventData isEqualType [] && {_eventData isNotEqualTo []}) then {
    _txId = _eventData # 0;
};

if (!(isNil "acre_sys_data_fnc_getScratchData") && {!(isNil "acre_sys_data_fnc_setScratchData")}) then {
    private _current = [_radioId, "currentTransmissions", []] call acre_sys_data_fnc_getScratchData;
    if (_txId != "") then {
        _current = _current - [_txId];
    };
    [_radioId, "currentTransmissions", _current] call acre_sys_data_fnc_setScratchData;

    if (_current isEqualTo []) then {
        private _beeped = [_radioId, "hasBeeped", false] call acre_sys_data_fnc_getScratchData;
        private _pttDown = [_radioId, "PTTDown", false] call acre_sys_data_fnc_getScratchData;
        if (!_pttDown && {_beeped}) then {
            private _volume = [_radioId, "getVolume"] call acre_sys_data_fnc_dataEvent;
            private _volumeScale = [_radioId, "Iceman_WR_rxVolumeScale", 1] call acre_sys_data_fnc_getScratchData;
            _volume = _volume * _volumeScale;
            private _ear = [_radioId, "Iceman_WR_rxEar", "BOTH"] call acre_sys_data_fnc_getScratchData;
            private _position = switch (toUpperANSI _ear) do {
                case "L": {[-2, 0, 0]};
                case "LEFT": {[-2, 0, 0]};
                case "R": {[2, 0, 0]};
                case "RIGHT": {[2, 0, 0]};
                default {[0, 0, 0]};
            };
            if (!(isNil "acre_sys_radio_fnc_playRadioSound")) then {
                [_radioId, "Acre_GenericClickOff", _position, [0, 1, 0], _volume] call acre_sys_radio_fnc_playRadioSound;
            };
        };
        [_radioId, "hasBeeped", false] call acre_sys_data_fnc_setScratchData;
        [_radioId, "receivingSignal", 0] call acre_sys_data_fnc_setScratchData;
    };
};

true
