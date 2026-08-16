/*
    Écrit les données SSE sur une entité (public).
    [_entity, _data, _public] call comspec_sse_fnc_setData
*/
params [
    ["_entity", objNull, [objNull]],
    ["_data", [], [[]]],
    ["_public", true, [true]]
];

if (isNull _entity) exitWith { false };
if !(_data isEqualType []) exitWith { false };

_entity setVariable ["comspec_sse_data", _data, _public];
_entity setVariable ["comspec_sse_enabled", true, _public];

[format ["setData %1 uid=%2", _entity, [_data, "uid", "?"] call comspec_sse_fnc_getPair]] call comspec_sse_fnc_log;

true
