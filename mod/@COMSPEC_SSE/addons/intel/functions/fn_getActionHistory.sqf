params [
    ["_entity", objNull, [objNull]]
];
if (isNil "comspec_sse_actionHistory") exitWith { [] };
if (isNull _entity) exitWith { +comspec_sse_actionHistory };
private _nid = netId _entity;
comspec_sse_actionHistory select { (_x getOrDefault ["entity", ""]) isEqualTo _nid }
