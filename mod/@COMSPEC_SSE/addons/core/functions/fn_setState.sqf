/*
    [_entity, _state, _public, _force] call comspec_sse_fnc_setState
    États: UNTOUCHED DISCOVERED SEARCHED PARTIALLY_EXPLOITED EXPLOITED COLLECTED TRANSMITTED
    L'état ne fait qu'avancer dans cet ordre (sauf _force) : un marquage « exploité » ou une extraction DL-7 après la
    collecte ramenait la pièce à EXPLOITED, ce qui rouvrait la garde de collecte (doublon de chaîne de possession).
*/
params [
    ["_entity", objNull, [objNull]],
    ["_state", "UNTOUCHED", [""]],
    ["_public", true, [true]],
    ["_force", false, [true]]
];

private _data = [_entity] call comspec_sse_fnc_getData;
if (isNil "_data") exitWith { false };

_state = toUpper _state;
private _order = ["UNTOUCHED", "DISCOVERED", "SEARCHED", "PARTIALLY_EXPLOITED", "EXPLOITED", "COLLECTED", "TRANSMITTED"];
private _current = [_data, "state", "UNTOUCHED"] call BIS_fnc_getFromPairs;
if (!(_current isEqualType "")) then { _current = "UNTOUCHED"; };
_current = toUpper _current;
if (!_force && {(_order find _state) >= 0} && {(_order find _state) < (_order find _current)}) exitWith {
    [format ["setState %1 : %2 ignoré (déjà %3)", _entity, _state, _current]] call comspec_sse_fnc_log;
    false
};
_data = [_data, "state", _state] call comspec_sse_fnc_setPair;

if (_state in ["SEARCHED", "PARTIALLY_EXPLOITED", "EXPLOITED", "COLLECTED", "TRANSMITTED"]) then {
    _data = [_data, "searched", true] call comspec_sse_fnc_setPair;
};
if (_state in ["EXPLOITED", "COLLECTED", "TRANSMITTED"]) then {
    _data = [_data, "exploited", true] call comspec_sse_fnc_setPair;
};

[_entity, _data, _public] call comspec_sse_fnc_setData;
[format ["setState %1 -> %2", _entity, _state]] call comspec_sse_fnc_log;
true
