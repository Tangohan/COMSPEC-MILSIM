/*
    Ajoute une entité logique (n'existe pas physiquement dans Arma).
    [_id, _kind, _meta] call comspec_sse_fnc_addLogicalEntity
    kind: ALIAS | ORGANIZATION | PHONE_NUMBER | DEPOT | BANK_ACCOUNT | RADIO_CALLSIGN | UNKNOWN_PERSON
*/
params [
    ["_id", "", [""]],
    ["_kind", "ALIAS", [""]],
    ["_meta", createHashMap, [createHashMap]]
];

if (_id == "") exitWith { "" };
if (isNil "comspec_sse_logicalEntities") then { comspec_sse_logicalEntities = createHashMap; };

private _rec = createHashMapFromArray [
    ["id", _id],
    ["kind", toUpper _kind],
    ["label", _meta getOrDefault ["label", _id]],
    ["linkedEntity", _meta getOrDefault ["linkedEntity", ""]],
    ["tags", _meta getOrDefault ["tags", []]],
    ["createdAt", time],
    ["meta", _meta]
];

comspec_sse_logicalEntities set [_id, _rec];
["SSE_NetworkLinked", [_id, _kind, _rec]] call comspec_sse_fnc_emitEvent;
_id
