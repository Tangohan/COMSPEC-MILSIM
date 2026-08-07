/*
    Marque une entité comme transmise.
*/
params [
    ["_entity", objNull, [objNull]],
    ["_state", "TRANSMITTED", [""]]
];

if (isNull _entity) exitWith { false };
[_entity, _state, true] call comspec_sse_fnc_setState;
true
