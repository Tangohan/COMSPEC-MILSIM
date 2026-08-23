/*
    Active le nœud DOMEX sur un objet (pas une personne).
    [_entity] call comspec_sse_fnc_domexEnsureNode
*/
params [
    ["_entity", objNull, [objNull]]
];

if (isNull _entity) exitWith { false };
if (_entity isKindOf "CAManBase") exitWith { false };

if !(_entity getVariable ["comspec_sse_domex_enabled", false]) then {
    [_entity, createHashMapFromArray [["enabled", true]]] call comspec_sse_fnc_domexApplyObject;
};

if ((_entity getVariable ["comspec_sse_domex_nodeId", ""]) isEqualTo "") then {
    _entity setVariable ["comspec_sse_domex_nodeId", format ["OBJ-%1", netId _entity], true];
};

true
