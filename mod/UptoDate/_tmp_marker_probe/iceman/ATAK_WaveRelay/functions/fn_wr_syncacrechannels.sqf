if (!hasInterface) exitWith {false};
if (isNil "acre_api_fnc_getRadioByType" || {isNil "acre_sys_data_fnc_dataEvent"}) exitWith {false};

private _radio = ["ACRE_MPU5"] call acre_api_fnc_getRadioByType;
if (isNil "_radio" || {!(_radio isEqualType "")} || {_radio == ""}) exitWith {false};
player setVariable ["Iceman_WR_radioId", _radio, true];

private _state = call Iceman_fnc_wr_getState;
private _freqText = _state getOrDefault ["frequency", "32.0"];
private _operator = name player;
if (_operator == "") then {_operator = "Unknown"};

private _baseFreq = parseNumber _freqText;
if (_baseFreq <= 0) then {_baseFreq = 32};
_baseFreq = 30 max (511.5 min _baseFreq);

private _signature = format ["%1|%2|%3", _radio, _freqText, _operator];
if ((_state getOrDefault ["acreChannelSignature", ""]) == _signature) exitWith {true};

private _channels = [_radio, "getState", "channels"] call acre_sys_data_fnc_dataEvent;
if (isNil "_channels" || {!(_channels isEqualType [])} || {_channels isEqualTo []}) then {
    [_radio, "initializeComponent", ["ACRE_MPU5", "default"]] call acre_sys_data_fnc_dataEvent;
    _channels = [_radio, "getState", "channels"] call acre_sys_data_fnc_dataEvent;
};
if (isNil "_channels" || {!(_channels isEqualType [])} || {_channels isEqualTo []}) exitWith {false};

private _limit = 15 min ((count _channels) - 1);
for "_i" from 0 to _limit do {
    private _channel = _channels # _i;
    if ((typeName _channel) == "LOCATION" && {!isNull _channel}) then {
        private _tg = _i + 1;
        private _acreFreq = (_baseFreq + (_i * 0.025)) min 511.975;
        _channel setVariable ["mode", "singleChannel"];
        _channel setVariable ["description", format ["TG%1 - %2", _tg, _operator]];
        _channel setVariable ["frequencyTX", _acreFreq];
        _channel setVariable ["frequencyRX", _acreFreq];
        _channel setVariable ["power", 5000];
        _channel setVariable ["encryption", 0];
        _channel setVariable ["channelMode", "BASIC"];
        _channel setVariable ["CTCSSTx", 0];
        _channel setVariable ["CTCSSRx", 0];
        _channel setVariable ["modulation", "FM"];
        _channel setVariable ["TEK", 1];
        _channel setVariable ["trafficRate", 16];
        _channel setVariable ["syncLength", 256];
        _channel setVariable ["phase", 256];
        _channel setVariable ["squelch", 0];
        _channel setVariable ["deviation", 8.0];
        _channel setVariable ["optionCode", 201];
        _channel setVariable ["Iceman_WR_talkgroup", _tg];
        _channel setVariable ["Iceman_WR_frequencyBank", _freqText];
        _channel setVariable ["Iceman_WR_bridgeEnabled", false];
        _channel setVariable ["rxOnly", false];

        _channel setVariable ["Iceman_Bridge_enabled", false];
        _channel setVariable ["Iceman_Bridge_radioClass", ""];
        _channel setVariable ["Iceman_Bridge_channelIndex", -1];
        _channel setVariable ["Iceman_Bridge_channelLabel", ""];
        _channel setVariable ["Iceman_Bridge_recordId", ""];
        _channel setVariable ["Iceman_Bridge_legacyPower", 5000];
        _channel setVariable ["Iceman_Bridge_effectivePower", 5000];

        _channel setVariable ["Iceman_ROIP_enabled", false];
        _channel setVariable ["Iceman_ROIP_linkId", ""];
        _channel setVariable ["Iceman_ROIP_gatewayRadioId", ""];
        _channel setVariable ["Iceman_ROIP_gatewayOwner", ""];
        _channel setVariable ["Iceman_ROIP_gatewayOwnerUID", ""];
        _channel setVariable ["Iceman_ROIP_radioClass", ""];
        _channel setVariable ["Iceman_ROIP_channelNumber", -1];
        _channel setVariable ["Iceman_ROIP_channelLabel", ""];
        _channel setVariable ["Iceman_ROIP_legacyPower", 5000];
        _channel setVariable ["Iceman_ROIP_effectivePower", 5000];
    };
};

[_radio, "setState", ["channels", _channels]] call acre_sys_data_fnc_dataEvent;
_state set ["acreChannelSignature", _signature];

true
