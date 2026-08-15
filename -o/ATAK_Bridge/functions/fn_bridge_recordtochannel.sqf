params [["_record", []]];

if !(_record isEqualType [] && {(count _record) >= 4}) exitWith {locationNull};
_record params ["_radioClass", "_channelIndex", "_label", "_pairs"];

private _channel = call acre_main_fnc_fastHashCreate;
{
    if (_x isEqualType [] && {(count _x) == 2} && {(_x # 0) isEqualType ""}) then {
        _channel setVariable [_x # 0, _x # 1];
    };
} forEach _pairs;

if ((_channel getVariable ["mode", ""]) == "") then {_channel setVariable ["mode", "singleChannel"]};
if ((_channel getVariable ["frequencyRX", -1]) < 0) then {_channel setVariable ["frequencyRX", _channel getVariable ["frequencyTX", 32]]};
if ((_channel getVariable ["frequencyTX", -1]) < 0) then {_channel setVariable ["frequencyTX", _channel getVariable ["frequencyRX", 32]]};
if ((_channel getVariable ["power", -1]) < 0) then {_channel setVariable ["power", 5000]};
private _legacyPower = _channel getVariable ["power", 5000];
private _effectivePower = 5000 max _legacyPower;
_channel setVariable ["power", _effectivePower];
if ((_channel getVariable ["channelMode", ""]) == "") then {_channel setVariable ["channelMode", "BASIC"]};
if ((_channel getVariable ["description", ""]) == "") then {_channel setVariable ["description", _label]};
if ((_channel getVariable ["CTCSSTx", -1]) < 0) then {_channel setVariable ["CTCSSTx", 0]};
if ((_channel getVariable ["CTCSSRx", -1]) < 0) then {_channel setVariable ["CTCSSRx", 0]};
if ((_channel getVariable ["modulation", ""]) == "") then {_channel setVariable ["modulation", "FM"]};
if ((_channel getVariable ["encryption", -1]) < 0) then {_channel setVariable ["encryption", 0]};
if ((_channel getVariable ["TEK", -1]) < 0) then {_channel setVariable ["TEK", 1]};
if ((_channel getVariable ["trafficRate", -1]) < 0) then {_channel setVariable ["trafficRate", 16]};
if ((_channel getVariable ["syncLength", -1]) < 0) then {_channel setVariable ["syncLength", 256]};
if ((_channel getVariable ["optionCode", -1]) < 0) then {_channel setVariable ["optionCode", 201]};
if ((_channel getVariable ["squelch", -1]) < 0) then {_channel setVariable ["squelch", 0]};
if ((_channel getVariable ["deviation", -1]) < 0) then {_channel setVariable ["deviation", 8.0]};

_channel setVariable ["rxOnly", false];
_channel setVariable ["Iceman_Bridge_enabled", true];
_channel setVariable ["Iceman_Bridge_radioClass", _radioClass];
_channel setVariable ["Iceman_Bridge_channelIndex", _channelIndex];
_channel setVariable ["Iceman_Bridge_channelLabel", _label];
_channel setVariable ["Iceman_Bridge_recordId", [_record] call Iceman_fnc_bridge_recordid];
_channel setVariable ["Iceman_Bridge_legacyPower", _legacyPower];
_channel setVariable ["Iceman_Bridge_effectivePower", _effectivePower];
_channel setVariable ["Iceman_WR_bridgeEnabled", true];
_channel
