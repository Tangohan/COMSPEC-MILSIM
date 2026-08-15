params ["_radioId", "_event", "_eventData", "_radioData"];

_eventData params [["_channelNumber", 0], ["_channelData", locationNull]];

if !(_channelData isEqualType locationNull && {!isNull _channelData}) exitWith {false};

private _channels = _radioData getVariable ["channels", []];
if (_channels isEqualTo []) exitWith {false};

_channelNumber = (round _channelNumber) max 0 min ((count _channels) - 1);
private _channel = _channels # _channelNumber;

{
    private _value = _channelData getVariable _x;
    if (!isNil "_value") then {
        _channel setVariable [_x, _value];
    };
} forEach [
    "mode",
    "frequencyTX",
    "frequencyRX",
    "power",
    "encryption",
    "channelMode",
    "description",
    "CTCSSTx",
    "CTCSSRx",
    "modulation",
    "TEK",
    "trafficRate",
    "syncLength",
    "phase",
    "squelch",
    "deviation",
    "optionCode",
    "rxOnly",
    "Iceman_WR_talkgroup",
    "Iceman_WR_frequencyBank",
    "Iceman_Bridge_enabled",
    "Iceman_Bridge_radioClass",
    "Iceman_Bridge_channelIndex",
    "Iceman_Bridge_channelLabel",
    "Iceman_Bridge_recordId",
    "Iceman_Bridge_legacyPower",
    "Iceman_Bridge_effectivePower",
    "Iceman_WR_bridgeEnabled",
    "Iceman_ROIP_enabled",
    "Iceman_ROIP_linkId",
    "Iceman_ROIP_gatewayRadioId",
    "Iceman_ROIP_gatewayOwner",
    "Iceman_ROIP_gatewayOwnerUID",
    "Iceman_ROIP_radioClass",
    "Iceman_ROIP_channelNumber",
    "Iceman_ROIP_channelLabel",
    "Iceman_ROIP_legacyPower",
    "Iceman_ROIP_effectivePower"
];

if !(_channelData getVariable ["Iceman_Bridge_enabled", false]) then {
    _channel setVariable ["Iceman_Bridge_enabled", false];
    _channel setVariable ["Iceman_Bridge_radioClass", ""];
    _channel setVariable ["Iceman_Bridge_channelIndex", -1];
    _channel setVariable ["Iceman_Bridge_channelLabel", ""];
    _channel setVariable ["Iceman_Bridge_recordId", ""];
    _channel setVariable ["Iceman_Bridge_legacyPower", _channel getVariable ["power", 5000]];
    _channel setVariable ["Iceman_Bridge_effectivePower", _channel getVariable ["power", 5000]];
    _channel setVariable ["Iceman_WR_bridgeEnabled", false];
};

if !(_channelData getVariable ["Iceman_ROIP_enabled", false]) then {
    _channel setVariable ["Iceman_ROIP_enabled", false];
    _channel setVariable ["Iceman_ROIP_linkId", ""];
    _channel setVariable ["Iceman_ROIP_gatewayRadioId", ""];
    _channel setVariable ["Iceman_ROIP_gatewayOwner", ""];
    _channel setVariable ["Iceman_ROIP_gatewayOwnerUID", ""];
    _channel setVariable ["Iceman_ROIP_radioClass", ""];
    _channel setVariable ["Iceman_ROIP_channelNumber", -1];
    _channel setVariable ["Iceman_ROIP_channelLabel", ""];
    _channel setVariable ["Iceman_ROIP_legacyPower", _channel getVariable ["power", 5000]];
    _channel setVariable ["Iceman_ROIP_effectivePower", _channel getVariable ["power", 5000]];
};

true
