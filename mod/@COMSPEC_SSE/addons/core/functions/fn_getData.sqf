/*
    Lit les données SSE d'une entité.
    [_entity] call comspec_sse_fnc_getData -> ARRAY | nil
*/
params [
    ["_entity", objNull, [objNull]]
];

if (isNull _entity) exitWith { nil };

_entity getVariable ["comspec_sse_data", nil]
