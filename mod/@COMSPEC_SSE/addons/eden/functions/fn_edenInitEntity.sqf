/*
    Init Eden/runtime d'une entité marquée SSE.
*/
params [
    ["_entity", objNull, [objNull]]
];

if (isNull _entity) exitWith {};
if (!isServer) exitWith {};

if !(_entity getVariable ["comspec_sse_enabled", false]) exitWith {};

[_entity] call comspec_sse_fnc_edenApplyAttributes;
