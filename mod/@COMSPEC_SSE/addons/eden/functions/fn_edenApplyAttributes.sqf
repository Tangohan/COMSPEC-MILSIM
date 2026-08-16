/*
    Applique les attributs Eden SSE.
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
private _region = _entity getVariable ["comspec_sse_region", "IRAQ"];
private _advanced = _entity getVariable ["comspec_sse_advancedData", ""];

_entity setVariable ["comspec_sse_region", _region, true];

if (_generation == "AUTO") then {
    if (_modelId != "") then {
        [_entity, _modelId, "EDEN"] call comspec_sse_fnc_applyModel;
    } else {
        [_entity, _profile, _complexity, "EDEN"] call comspec_sse_fnc_generateData;
    };
} else {
    [_entity, "PERSON", _profile, _complexity] call comspec_sse_fnc_makeSearchable;
};

if (_networkId != "") then {
    private _data = [_entity] call comspec_sse_fnc_getData;
    if (!isNil "_data") then {
        _data = [_data, ["networkId", _networkId]] call BIS_fnc_setToPairs;
        [_entity, _data, true] call comspec_sse_fnc_setData;
    };
};

if (_advanced != "") then {
    private _parsed = call compile _advanced;
    if (_parsed isEqualType []) then {
        [_entity, _parsed] call comspec_sse_fnc_setIdentity;
    };
};

[format ["edenApply %1 model=%2 profile=%3", _entity, _modelId, _profile]] call comspec_sse_fnc_log;
true
