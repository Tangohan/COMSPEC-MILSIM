/*
    Payload acquisition numérique (labnum / Athena).
    [_entity, _fogOrDevice] call comspec_sse_fnc_buildAthenaDigitalPayload
*/
params [
    ["_entity", objNull, [objNull]],
    ["_fog", createHashMap, [createHashMap]]
];

private _sum = createHashMapFromArray [["ok", false]];
private _pc = createHashMapFromArray [["ok", false]];
private _data = nil;
if (!isNull _entity) then {
    _sum = [_entity] call comspec_sse_fnc_getDeviceSummary;
    _pc = [_entity] call comspec_sse_fnc_getComputerSummary;
    _data = [_entity] call comspec_sse_fnc_getData;
};

private _uid = _fog getOrDefault ["uid", ""];
if (_uid isEqualTo "") then {
    if (isNil "_data") then {
        _uid = ["SSE-DIG"] call comspec_sse_fnc_generateUID;
    } else {
        _uid = [_data, "uid", ["SSE-DIG"] call comspec_sse_fnc_generateUID] call BIS_fnc_getFromPairs;
    };
};

private _pos = _fog getOrDefault ["position", []];
if (!(_pos isEqualType []) || {count _pos < 2}) then {
    _pos = if (isNull _entity) then { getPosATL player } else { getPosATL _entity };
};

private _srcType = _fog getOrDefault ["type", ""];
if (_srcType isEqualTo "" && {!isNull _entity}) then {
    _srcType = _sum getOrDefault ["deviceType", "unknown"];
};
if (_srcType isEqualTo "") then { _srcType = "unknown"; };
private _lines = _fog getOrDefault ["lines", []];

private _domex = createHashMap;
if (!isNull _entity && {!isNil "comspec_sse_fnc_domexGetNode"}) then {
    _domex = [_entity] call comspec_sse_fnc_domexGetNode;
};
if ((_fog getOrDefault ["node_id", ""]) isNotEqualTo "") then {
    _domex set ["node_id", _fog get "node_id"];
    _domex set ["enabled", true];
};
if ((_fog getOrDefault ["device_type", ""]) isNotEqualTo "") then {
    _domex set ["device_type", _fog get "device_type"];
};

private _packets = _domex getOrDefault ["packets", []];
if !(_packets isEqualType []) then { _packets = []; };
private _extra = _fog getOrDefault ["packets", []];
if (_extra isEqualType []) then {
    { _packets pushBack _x } forEach _extra;
};
_domex set ["packets", _packets];

private _origin = _fog getOrDefault ["origin", "terrain"];
if (_origin isEqualTo "") then { _origin = "terrain"; };

private _idem = _fog getOrDefault ["idempotency_key", ""];
if (!(_idem isEqualType "")) then { _idem = ""; };
if (_idem isEqualTo "") then {
    _idem = ["DIG", _uid] call comspec_sse_fnc_makeIdempotencyKey;
    if (!(_idem isEqualType "")) then { _idem = ""; };
    if (_origin isEqualTo "zeus_live" || {(_fog getOrDefault ["mode", ""]) in ["zeus_live", "zeus_map", "zeus_stage"]}) then {
        _idem = format ["%1-L%2", _idem, round (diag_tickTime * 1000)];
    };
};

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
    ["idempotency_key", _idem],
    ["phone_summary", _sum],
    ["computer_summary", _pc],
    ["extraction_lines", _lines],
    ["mode", _fog getOrDefault ["mode", ""]],
    ["domex", _domex],
    ["packets", _packets],
    ["origin", _origin],
    ["schema", "comspec_sse_athena_digital_v0.5"]
]
