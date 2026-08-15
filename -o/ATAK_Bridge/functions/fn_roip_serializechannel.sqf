params ["_channel"];

if !(_channel isEqualType locationNull && {!isNull _channel}) exitWith {[]};

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
private _sentinel = "__ICEMAN_ROIP_UNSET__";
{
    private _value = _channel getVariable [_x, _sentinel];
    if !(_value isEqualTo _sentinel) then {
        _pairs pushBack [_x, _value];
    };
} forEach _keys;

_pairs
