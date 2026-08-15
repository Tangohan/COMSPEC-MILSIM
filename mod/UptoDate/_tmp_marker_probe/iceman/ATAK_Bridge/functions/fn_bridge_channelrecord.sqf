params [
    ["_radioClass", "ACRE_PRC152"],
    ["_channelIndex", 0]
];

if (isNil "acre_api_fnc_getPresetChannelData") exitWith {[]};

private _data = [_radioClass, "default", _channelIndex] call acre_api_fnc_getPresetChannelData;
if (isNil "_data") exitWith {[]};
if !(_data isEqualType locationNull) exitWith {[]};
if (isNull _data) exitWith {[]};

private _label = _data getVariable ["description", ""];
if (_label == "") then {_label = _data getVariable ["name", ""]};
if (_label == "") then {_label = format ["CH%1", _channelIndex + 1]};

private _keys = [
    "mode",
    "frequencyTX",
    "frequencyRX",
    "power",
    "encryption",
    "channelMode",
    "description",
    "name",
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
    "rxOnly"
];

private _pairs = [];
private _sentinel = "__ICEMAN_BRIDGE_UNSET__";
{
    private _value = _data getVariable [_x, _sentinel];
    if !(_value isEqualTo _sentinel) then {
        _pairs pushBack [_x, _value];
    };
} forEach _keys;

[_radioClass, _channelIndex, _label, _pairs]
