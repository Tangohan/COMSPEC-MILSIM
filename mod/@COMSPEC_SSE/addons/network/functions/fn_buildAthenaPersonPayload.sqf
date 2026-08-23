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

private _first = trim (_id getOrDefault ["first_name", ""]);
private _last = trim (_id getOrDefault ["last_name", ""]);
private _alias = trim (_id getOrDefault ["alias", ""]);
if (_first isEqualTo "") then { _first = trim (_entity getVariable ["COMSPEC_SSE_FirstName", ""]); };
if (_last isEqualTo "") then { _last = trim (_entity getVariable ["COMSPEC_SSE_LastName", ""]); };
if (_alias isEqualTo "") then { _alias = trim (_entity getVariable ["COMSPEC_SSE_Alias", ""]); };

private _name = trim (_id getOrDefault ["name", ""]);
if (_first isEqualTo "" && {_last isEqualTo ""} && {_name isNotEqualTo ""}) then {
    private _parts = _name splitString " ";
    if ((count _parts) > 1) then {
        _first = _parts select 0;
        _last = (_parts select [1, (count _parts) - 1]) joinString " ";
    } else {
        _first = _name;
    };
};

if (_first isEqualTo "" && {_last isEqualTo ""} && {_entity isKindOf "CAManBase"}) then {
    private _unitName = name _entity;
    if (_unitName isNotEqualTo "" && {(_unitName find "Error:") < 0}) then {
        private _parts = _unitName splitString " ";
        if ((count _parts) > 1) then {
            _first = _parts select 0;
            _last = (_parts select [1, (count _parts) - 1]) joinString " ";
        } else {
            _first = _unitName;
        };
    };
};

private _pos = getPosATL _entity;
private _mapId = parseNumber (missionNamespace getVariable ["comspec_sse_mapId", "1"]);
if (_mapId < 1) then { _mapId = 1; };
private _uid = if (isNil "_data") then {["SSE"] call comspec_sse_fnc_generateUID} else {[_data, "uid", ""] call BIS_fnc_getFromPairs};
if (!(_uid isEqualType "")) then { _uid = format ["%1", _uid]; };

if (_first isEqualTo "" && {_last isEqualTo ""} && {_alias isEqualTo ""}) then {
    if (_name isNotEqualTo "") then {
        _alias = _name;
    } else {
        if (_uid isNotEqualTo "") then { _alias = _uid; } else { _alias = "SSE"; };
    };
};

private _case = [] call comspec_sse_fnc_getCaseReference;
private _idem = ["PERSON", _uid] call comspec_sse_fnc_makeIdempotencyKey;

[format ["personPayload uid=%1 first='%2' last='%3' alias='%4'", _uid, _first, _last, _alias]] call comspec_sse_fnc_log;

createHashMapFromArray [
    ["mapId", _mapId],
    ["status", _extra getOrDefault ["status", "civil"]],
    ["last_name", _last],
    ["first_name", _first],
    ["alias", _alias],
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
