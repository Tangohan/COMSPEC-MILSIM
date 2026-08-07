/*
    [_entity, _state, _public] call comspec_sse_fnc_setState
    États: UNTOUCHED DISCOVERED SEARCHED PARTIALLY_EXPLOITED EXPLOITED COLLECTED TRANSMITTED
*/
params [
    ["_entity", objNull, [objNull]],
    ["_state", "UNTOUCHED", [""]],
    ["_public", true, [true]]
];

private _data = [_entity] call comspec_sse_fnc_getData;
if (isNil "_data") exitWith { false };

_state = toUpper _state;
_data = [_data, ["state", _state]] call BIS_fnc_setToPairs;

if (_state in ["SEARCHED", "PARTIALLY_EXPLOITED", "EXPLOITED", "COLLECTED", "TRANSMITTED"]) then {
    _data = [_data, ["searched", true]] call BIS_fnc_setToPairs;
};
if (_state in ["EXPLOITED", "COLLECTED", "TRANSMITTED"]) then {
    _data = [_data, ["exploited", true]] call BIS_fnc_setToPairs;
};

[_entity, _data, _public] call comspec_sse_fnc_setData;
[format ["setState %1 -> %2", _entity, _state]] call comspec_sse_fnc_log;
true
