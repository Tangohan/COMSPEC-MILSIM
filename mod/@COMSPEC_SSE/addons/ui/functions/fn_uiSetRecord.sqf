/*
    Définit le record SSE courant pour tous les écrans UI.
    [_entity] call comspec_sse_fnc_uiSetRecord
*/
params [
    ["_entity", objNull, [objNull]]
];

missionNamespace setVariable ["comspec_sse_uiRecord", _entity];
if (!isNull _entity) then {
    [_entity] call comspec_sse_fnc_ensureGenerated;
};
true
