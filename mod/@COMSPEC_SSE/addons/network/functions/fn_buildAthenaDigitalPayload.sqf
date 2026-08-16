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
    private _fallback = ["SSE-DIG"] call comspec_sse_fnc_generateUID;
    _fog getOrDefault ["uid", _fallback]
} else {
    [_data, "uid", _fog getOrDefault ["uid", "?"]] call BIS_fnc_getFromPairs
};

private _pos = if (isNull _entity) then { getPosATL player } else { getPosATL _entity };

private _srcType = _fog getOrDefault ["type", ""];
if (_srcType isEqualTo "") then { _srcType = _sum getOrDefault ["deviceType", "unknown"]; };
private _lines = _fog getOrDefault ["lines", []];

createHashMapFromArray [
    ["mission_id", missionNamespace getVariable ["comspec_sse_missionId", "UNKNOWN_MISSION"]],
    ["record_id", _uid],
    ["category", "digital"],
    ["source_type", _srcType],
    ["collector", name player],
    ["position", _pos],
    ["grid_reference", mapGridPosition _pos],
    ["quality", _fog getOrDefault ["quality", 0]],
    ["case_reference", [] call comspec_sse_fnc_getCaseReference],
    ["idempotency_key", ["DIG", _uid] call comspec_sse_fnc_makeIdempotencyKey],
    ["phone_summary", _sum],
    ["computer_summary", _pc],
    ["extraction_lines", _lines],
    ["mode", _fog getOrDefault ["mode", ""]],
    ["schema", "comspec_sse_athena_digital_v0.4"]
]
