/*
    Applique les attributs Eden SSE.

    AUTO = skeleton + modèle en attente (lazy au premier examen).
    Ne plus appeler generateData/applyModel dans InitPost : c’était la
    régression crash (generateCluster + ACE dogtag/medical sur pose d’unités).
*/
params [
    ["_entity", objNull, [objNull, []]]
];

if (_entity isEqualType []) then {
    _entity = _entity param [0, objNull, [objNull]];
};

if (isNull _entity) exitWith { false };

private _profile = _entity getVariable ["comspec_sse_profile", "RANDOM"];
private _complexity = _entity getVariable ["comspec_sse_complexity", "STANDARD"];
private _generation = _entity getVariable ["comspec_sse_generation", "AUTO"];
private _networkId = _entity getVariable ["comspec_sse_networkId", ""];
private _modelId = _entity getVariable ["comspec_sse_modelId", ""];
private _datasetId = _entity getVariable ["comspec_sse_datasetId", ""];
private _datasetRole = _entity getVariable ["comspec_sse_datasetRole", ""];
private _missionSeed = _entity getVariable ["comspec_sse_missionSeed", ""];
private _region = _entity getVariable ["comspec_sse_region", "IRAQ"];
private _advanced = _entity getVariable ["comspec_sse_advancedData", ""];

_entity setVariable ["comspec_sse_region", _region, true];

private _type = if (_entity isKindOf "CAManBase") then { "PERSON" } else {
    if (!isNil "comspec_sse_fnc_resolveEntityType") then {
        [_entity] call comspec_sse_fnc_resolveEntityType
    } else {
        "OBJECT"
    }
};

// Toujours un skeleton léger (pas de generateData ici).
[_entity, _type, _profile, _complexity] call comspec_sse_fnc_makeSearchable;

if (_generation == "AUTO") then {
    if (_modelId != "") then {
        _entity setVariable ["comspec_sse_pendingModelId", _modelId, true];
    };
    if (_datasetId != "" && {_datasetRole != ""}) then {
        _entity setVariable ["comspec_sse_pendingDatasetId", _datasetId, true];
        _entity setVariable ["comspec_sse_pendingDatasetRole", _datasetRole, true];
    };
};

if (_networkId != "") then {
    private _data = [_entity] call comspec_sse_fnc_getData;
    if (!isNil "_data") then {
        _data = [_data, "networkId", _networkId] call comspec_sse_fnc_setPair;
        [_entity, _data, true] call comspec_sse_fnc_setData;
    };
};

if (_missionSeed != "") then {
    _entity setVariable ["comspec_sse_missionSeed", _missionSeed, true];
};

if (_advanced != "") then {
    private _parsed = call compile _advanced;
    if (_parsed isEqualType []) then {
        [_entity, _parsed] call comspec_sse_fnc_setIdentity;
    };
};

[format ["edenApply %1 lazy model=%2 dataset=%3/%4", _entity, _modelId, _datasetId, _datasetRole]] call comspec_sse_fnc_log;
true
