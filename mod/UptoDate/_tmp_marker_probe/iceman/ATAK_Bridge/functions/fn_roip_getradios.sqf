if (isNil "acre_api_fnc_getCurrentRadioList" || {isNil "acre_sys_data_fnc_dataEvent"}) exitWith {[]};

private _radioIds = [] call acre_api_fnc_getCurrentRadioList;
private _allowed = ["ACRE_PRC152", "ACRE_PRC117F"];
private _records = [];

{
    private _radioId = _x;
    private _base = "";
    if !(isNil "acre_sys_radio_fnc_getRadioBaseClassname") then {
        _base = [_radioId] call acre_sys_radio_fnc_getRadioBaseClassname;
    };
    if (_base == "" && {!(isNil "acre_api_fnc_getBaseRadio")}) then {
        _base = [_radioId] call acre_api_fnc_getBaseRadio;
    };

    if (_base in _allowed) then {
        private _channelNumber = if !(isNil "acre_api_fnc_getRadioChannel") then {
            [_radioId] call acre_api_fnc_getRadioChannel
        } else {
            ([_radioId, "getCurrentChannel"] call acre_sys_data_fnc_dataEvent) + 1
        };

        private _channel = [_radioId, "getCurrentChannelData"] call acre_sys_data_fnc_dataEvent;
        if (_channel isEqualType locationNull && {!isNull _channel} && {_channelNumber > 0}) then {
            private _label = _channel getVariable ["description", ""];
            if (_label == "") then {_label = _channel getVariable ["name", ""]};
            if (_label == "") then {_label = format ["Channel %1", _channelNumber]};

            private _on = [_radioId, "getOnOffState"] call acre_sys_data_fnc_dataEvent;
            if (isNil "_on") then {_on = 1};

            _records pushBack [
                _radioId,
                _base,
                _channelNumber,
                _label,
                _on == 1,
                [_channel] call Iceman_fnc_roip_serializeChannel
            ];
        };
    };
} forEach _radioIds;

_records
