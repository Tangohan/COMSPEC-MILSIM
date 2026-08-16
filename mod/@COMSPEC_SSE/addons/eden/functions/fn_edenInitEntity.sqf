/*
    Init Eden/runtime d'une entité marquée SSE.
    CBA Extended_InitPost passe [_unit] dans _this — ne pas rewraper.
*/
params [
    ["_entity", objNull, [objNull, []]]
];

if (_entity isEqualType []) then {
    _entity = _entity param [0, objNull, [objNull]];
};

if (isNull _entity) exitWith {};
if (!isServer) exitWith {};

if !(_entity getVariable ["comspec_sse_enabled", false]) exitWith {};

[_entity] call comspec_sse_fnc_edenApplyAttributes;
