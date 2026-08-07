/*
    Construit un payload compatible POST /api/sse/persons
    [_entity, _extra] call comspec_sse_fnc_buildAthenaPersonPayload
*/
params [
    ["_entity", objNull, [objNull]],
    ["_extra", createHashMap, [createHashMap]]
];

[_entity] call comspec_sse_fnc_ensureGenerated;
private _data = [_entity] call comspec_sse_fnc_getData;
private _id = [_entity, "identity"] call comspec_sse_fnc_getSection;
if (isNil "_id" || {!(_id isEqualType createHashMap)}) then { _id = createHashMap; };

private _name = _id getOrDefault ["name", "Unknown"];
private _parts = _name splitString " ";
private _first = if (count _parts > 1) then { _parts select 0 } else { _name };
private _last = if (count _parts > 1) then { (_parts select [1, count _parts - 1]) joinString " " } else { "" };

private _pos = getPosATL _entity;
private _mapId = parseNumber (missionNamespace getVariable ["comspec_sse_mapId", "1"]);
private _uid = if (isNil "_data") then {["SSE"] call comspec_sse_fnc_generateUID} else {[_data, "uid", ""] call BIS_fnc_getFromPairs};
private _case = [] call comspec_sse_fnc_getCaseReference;
private _idem = ["PERSON", _uid] call comspec_sse_fnc_makeIdempotencyKey;

createHashMapFromArray [
    ["mapId", _mapId],
    ["status", _extra getOrDefault ["status", "civil"]],
    ["last_name", _last],
    ["first_name", _first],
    ["alias", _id getOrDefault ["alias", ""]],
    ["nationality", _id getOrDefault ["nationality", ""]],
    ["language_spoken", _id getOrDefault ["language", ""]],
    ["affiliation", _id getOrDefault ["role", ""]],
    ["circumstances", _extra getOrDefault ["circumstances", "perquisition"]],
    ["confidence_level", _extra getOrDefault ["confidence_level", "moyenne"]],
    ["biometrics_simulated", true],
    ["consent_recorded", false],
    ["capture_pos_x", _pos select 0],
    ["capture_pos_y", _pos select 1],
    ["capture_pos_z", _pos select 2],
    ["grid_reference", mapGridPosition _pos],
    ["location_description", _extra getOrDefault ["location_description", ""]],
    ["submitter_callsign", name player],
    ["target_unit_netid", netId _entity],
    ["sse_uid", _uid],
    ["case_reference", _case],
    ["idempotency_key", _idem],
    ["mission_id", missionNamespace getVariable ["comspec_sse_missionId", "UNKNOWN_MISSION"]],
    ["schema", "comspec_sse_athena_person_v0.4"]
]
