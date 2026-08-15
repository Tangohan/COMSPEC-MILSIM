params [["_link", []]];

if !(_link isEqualType [] && {(count _link) >= 13}) exitWith {locationNull};
_link params ["", "_linkId", "_bank", "_tg", "_gatewayRadioId", "_radioClass", "_channelNumber", "_label", "_pairs", "_ownerName", "_ownerUid", "", "_legacyPower"];

private _channel = call acre_main_fnc_fastHashCreate;
{
    if (_x isEqualType [] && {(count _x) == 2} && {(_x # 0) isEqualType ""}) then {
        _channel setVariable [_x # 0, _x # 1];
    };
} forEach _pairs;

if ((_channel getVariable ["frequencyRX", -1]) < 0) then {_channel setVariable ["frequencyRX", _channel getVariable ["frequencyTX", 32]]};
if ((_channel getVariable ["frequencyTX", -1]) < 0) then {_channel setVariable ["frequencyTX", _channel getVariable ["frequencyRX", 32]]};
if !(_legacyPower isEqualType 0) then {_legacyPower = _channel getVariable ["power", 5000]};
private _effectivePower = 5000 max _legacyPower;

_channel setVariable ["mode", "singleChannel"];
_channel setVariable ["power", _effectivePower];
_channel setVariable ["channelMode", _channel getVariable ["channelMode", "BASIC"]];
_channel setVariable ["description", format ["ROIP TG%1 | %2", _tg, _label]];
_channel setVariable ["CTCSSTx", _channel getVariable ["CTCSSTx", 0]];
_channel setVariable ["CTCSSRx", _channel getVariable ["CTCSSRx", 0]];
_channel setVariable ["modulation", _channel getVariable ["modulation", "FM"]];
_channel setVariable ["encryption", _channel getVariable ["encryption", 0]];
_channel setVariable ["TEK", _channel getVariable ["TEK", 1]];
_channel setVariable ["trafficRate", _channel getVariable ["trafficRate", 16]];
_channel setVariable ["syncLength", _channel getVariable ["syncLength", 256]];
_channel setVariable ["optionCode", _channel getVariable ["optionCode", 201]];
_channel setVariable ["squelch", _channel getVariable ["squelch", 0]];
_channel setVariable ["deviation", _channel getVariable ["deviation", 8.0]];
_channel setVariable ["rxOnly", false];

_channel setVariable ["Iceman_WR_talkgroup", _tg];
_channel setVariable ["Iceman_WR_frequencyBank", _bank];
_channel setVariable ["Iceman_WR_bridgeEnabled", true];
_channel setVariable ["Iceman_ROIP_enabled", true];
_channel setVariable ["Iceman_ROIP_linkId", _linkId];
_channel setVariable ["Iceman_ROIP_gatewayRadioId", _gatewayRadioId];
_channel setVariable ["Iceman_ROIP_gatewayOwner", _ownerName];
_channel setVariable ["Iceman_ROIP_gatewayOwnerUID", _ownerUid];
_channel setVariable ["Iceman_ROIP_radioClass", _radioClass];
_channel setVariable ["Iceman_ROIP_channelNumber", _channelNumber];
_channel setVariable ["Iceman_ROIP_channelLabel", _label];
_channel setVariable ["Iceman_ROIP_legacyPower", _legacyPower];
_channel setVariable ["Iceman_ROIP_effectivePower", _effectivePower];

_channel setVariable ["Iceman_Bridge_enabled", true];
_channel setVariable ["Iceman_Bridge_radioClass", _radioClass];
_channel setVariable ["Iceman_Bridge_channelIndex", _channelNumber - 1];
_channel setVariable ["Iceman_Bridge_channelLabel", _label];
_channel setVariable ["Iceman_Bridge_recordId", _linkId];
_channel setVariable ["Iceman_Bridge_legacyPower", _legacyPower];
_channel setVariable ["Iceman_Bridge_effectivePower", _effectivePower];

_channel
