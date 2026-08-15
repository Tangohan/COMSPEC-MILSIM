params [["_radioId", ""], ["_event", ""], ["_eventData", []], ["_radioData", locationNull]];

private _channelNumber = 0;
if ((typeName _radioData) == "LOCATION" && {!isNull _radioData}) then {
    _channelNumber = _radioData getVariable ["currentChannel", 0];
};
if (_eventData isEqualType 0) then {
    _channelNumber = _eventData;
};

private _description = "";
if ((typeName _radioData) == "LOCATION" && {!isNull _radioData}) then {
    private _channels = _radioData getVariable ["channels", []];
    if (_channels isNotEqualTo []) then {
        _channelNumber = (round _channelNumber) max 0 min ((count _channels) - 1);
        private _channel = _channels # _channelNumber;
        if ((typeName _channel) == "LOCATION" && {!isNull _channel}) then {
            _description = _channel getVariable ["description", ""];
        };
    };
};

if (_description == "") then {
    _description = format ["TG%1", _channelNumber + 1];
};

_description
