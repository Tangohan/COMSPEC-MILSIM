/*
    Applique un rôle de dataset sur une entité.
    [_entity, _role, _dataset, _createdBy] call comspec_sse_fnc_applyDatasetRole
*/
params [
    ["_entity", objNull, [objNull]],
    ["_role", createHashMap, [createHashMap]],
    ["_dataset", createHashMap, [createHashMap]],
    ["_createdBy", "DATASET", [""]]
];

if (isNull _entity || {count _role == 0}) exitWith { false };

private _modelId = _role getOrDefault ["modelId", ""];
private _profile = _role getOrDefault ["profile", "INSURGENT"];
private _complexity = _role getOrDefault ["complexity", "DETAILED"];
private _region = _dataset getOrDefault ["region", "IRAQ"];
private _networkId = _dataset getOrDefault ["networkId", ""];
private _seed = _dataset getOrDefault ["seed", ""];

_entity setVariable ["comspec_sse_enabled", true, true];
_entity setVariable ["comspec_sse_region", _region, true];
_entity setVariable ["comspec_sse_datasetId", _dataset getOrDefault ["id", ""], true];
_entity setVariable ["comspec_sse_datasetRole", _role getOrDefault ["roleId", ""], true];
_entity setVariable ["comspec_sse_missionSeed", _seed, true];
_entity setVariable ["comspec_sse_networkId", _networkId, true];
_entity setVariable ["comspec_sse_profile", _profile, true];
_entity setVariable ["comspec_sse_complexity", _complexity, true];

if (_modelId != "") then {
    [_entity, _modelId, _createdBy] call comspec_sse_fnc_applyModel;
} else {
    [_entity, _profile, _complexity, _createdBy] call comspec_sse_fnc_generateData;
};

private _forcedId = _role getOrDefault ["forcedIdentity", createHashMap];
if (_forcedId isEqualType createHashMap && {count _forcedId > 0}) then {
    private _pairs = [];
    { _pairs pushBack [_x, _y]; } forEach _forcedId;
    [_entity, _pairs] call comspec_sse_fnc_setIdentity;
};

private _forcedPhone = _role getOrDefault ["forcedPhone", createHashMap];
if (_forcedPhone isEqualType createHashMap && {count _forcedPhone > 0}) then {
    private _pairs = [];
    { _pairs pushBack [_x, _y]; } forEach _forcedPhone;
    [_entity, _pairs] call comspec_sse_fnc_setDigitalData;
};

if (!isNil "comspec_sse_fnc_attachIntelLayers") then {
    [_entity] call comspec_sse_fnc_attachIntelLayers;
};

private _data = [_entity] call comspec_sse_fnc_getData;
if (!isNil "_data") then {
    _data = [_data, "datasetId", _dataset getOrDefault ["id", ""]] call comspec_sse_fnc_setPair;
    _data = [_data, "datasetRole", _role getOrDefault ["roleId", ""]] call comspec_sse_fnc_setPair;
    _data = [_data, "missionSeed", _seed] call comspec_sse_fnc_setPair;
    if (_networkId != "") then {
        _data = [_data, "networkId", _networkId] call comspec_sse_fnc_setPair;
    };
    [_entity, _data, true] call comspec_sse_fnc_setData;
};

[format ["applyDatasetRole %1 -> %2", _role getOrDefault ["roleId", "?"], _entity], "WARN"] call comspec_sse_fnc_log;
true
