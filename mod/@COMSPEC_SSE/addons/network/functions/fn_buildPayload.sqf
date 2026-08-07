/*
    Construit un payload SSE pour Athena / Overwatch.
    [_recordId, _category, _sourceType, _collector, _pos, _quality, _data] call comspec_sse_fnc_buildPayload
*/
params [
    ["_recordId", "", [""]],
    ["_category", "intel", [""]],
    ["_sourceType", "unknown", [""]],
    ["_collector", "", [""]],
    ["_pos", [0,0,0], [[]], 3],
    ["_quality", 0, [0]],
    ["_data", createHashMap, [createHashMap, []]]
];

private _missionId = missionNamespace getVariable ["comspec_sse_missionId", "UNKNOWN_MISSION"];

createHashMapFromArray [
    ["mission_id", _missionId],
    ["record_id", _recordId],
    ["category", _category],
    ["source_type", _sourceType],
    ["collector", _collector],
    ["position", _pos],
    ["quality", _quality],
    ["data", _data],
    ["timestamp", time],
    ["schema", "comspec_sse_v0.1"]
]
