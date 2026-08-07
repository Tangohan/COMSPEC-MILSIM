/*
    Payload acquisition numérique (labnum / Athena).
    [_entity, _fogOrDevice] call comspec_sse_fnc_buildAthenaDigitalPayload
*/
params [
    ["_entity", objNull, [objNull]],
    ["_fog", createHashMap, [createHashMap]]
];

private _sum = [_entity] call comspec_sse_fnc_getDeviceSummary;
private _pc = [_entity] call comspec_sse_fnc_getComputerSummary;
private _data = [_entity] call comspec_sse_fnc_getData;
private _uid = if (isNil "_data") then {
    _fog getOrDefault ["uid", ["SSE-DIG"] call comspec_sse_fnc_generateUID]
} else {
    [_data, "uid", _fog getOrDefault ["uid", "?"]] call BIS_fnc_getFromPairs
};

private _pos = if (isNull _entity) then { getPosATL player } else { getPosATL _entity };

createHashMapFromArray [
    ["mission_id", missionNamespace getVariable ["comspec_sse_missionId", "UNKNOWN_MISSION"]],
    ["record_id", _uid],
    ["category", "digital"],
    ["source_type", _fog getOrDefault ["type", _sum getOrDefault ["deviceType", "unknown"]]],
    ["collector", name player],
    ["position", _pos],
    ["grid_reference", mapGridPosition _pos],
    ["quality", _fog getOrDefault ["quality", 0]],
    ["case_reference", [] call comspec_sse_fnc_getCaseReference],
    ["idempotency_key", ["DIG", _uid] call comspec_sse_fnc_makeIdempotencyKey],
    ["phone_summary", _sum],
    ["computer_summary", _pc],
    ["extraction_lines", _fog getOrDefault ["lines", []]],
    ["mode", _fog getOrDefault ["mode", ""]],
    ["schema", "comspec_sse_athena_digital_v0.4"]
]
