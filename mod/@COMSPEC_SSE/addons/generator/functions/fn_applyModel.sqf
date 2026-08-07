/*
    Applique un modèle SSE à une entité (génération enrichie + overrides).
    [_entity, _modelOrId, _createdBy] call comspec_sse_fnc_applyModel
*/
params [
    ["_entity", objNull, [objNull]],
    ["_modelOrId", "", ["", createHashMap]],
    ["_createdBy", "MODEL", [""]]
];

if (isNull _entity) exitWith { false };

private _model = if (_modelOrId isEqualType createHashMap) then {
    _modelOrId
} else {
    [_modelOrId] call comspec_sse_fnc_loadModel
};

if (isNil "_model" || {!(_model isEqualType createHashMap)} || {count _model == 0}) exitWith {
    [format ["applyModel: modèle introuvable (%1)", _modelOrId], "ERROR"] call comspec_sse_fnc_log;
    false
};

private _profile = _model getOrDefault ["profile", "INSURGENT"];
private _complexity = _model getOrDefault ["complexity", "DETAILED"];
private _region = _model getOrDefault ["region", "IRAQ"];
private _theme = _model getOrDefault ["theme", "RANDOM"];

// Probabilités du modèle
private _noise = _model getOrDefault ["noiseProbability", -1];
private _falseP = _model getOrDefault ["falseLeadProbability", -1];
if (_noise >= 0) then { missionNamespace setVariable ["comspec_sse_noiseProbability", _noise]; };
if (_falseP >= 0) then { missionNamespace setVariable ["comspec_sse_falseLeadProbability", _falseP]; };

// Construire cluster pré-rempli depuis le modèle
private _seed = floor random 2147483647;
private _pos = getPosASL _entity;
_seed = [_seed, format ["mdl_%1_%2", _model getOrDefault ["id", ""], netId _entity]] call comspec_sse_fnc_hash;

private _pools = [_region] call comspec_sse_fnc_getNarrativePools;
if (_theme isEqualTo "" || {toUpper _theme == "RANDOM"}) then {
    _theme = [_seed, "theme", _pools getOrDefault ["themes", ["fuel_delivery"]]] call comspec_sse_fnc_pickFromSeed;
};

private _cluster = createHashMapFromArray [
    ["clusterId", format ["CLUS-MDL-%1", [_seed, "c"] call comspec_sse_fnc_hash]],
    ["profile", _profile],
    ["complexity", _complexity],
    ["region", _region],
    ["theme", _theme],
    ["modelId", _model getOrDefault ["id", ""]],
    ["modelName", _model getOrDefault ["name", ""]]
];

// Pools custom
private _aliasPool = _model getOrDefault ["aliasPool", []];
if (count _aliasPool > 0) then {
    _cluster set ["primaryAlias", [_seed, "alias", _aliasPool] call comspec_sse_fnc_pickFromSeed];
};
private _contactPool = _model getOrDefault ["contactPool", []];
if (count _contactPool > 0) then {
    _cluster set ["networkContacts", _contactPool];
};
private _namePool = _model getOrDefault ["namePool", []];
if (count _namePool > 0) then {
    _cluster set ["primaryName", [_seed, "name", _namePool] call comspec_sse_fnc_pickFromSeed];
};

private _forcedId = _model getOrDefault ["forcedIdentity", createHashMap];
if (_forcedId isEqualType createHashMap && {count _forcedId > 0}) then {
    if ((_forcedId getOrDefault ["name", ""]) != "") then { _cluster set ["primaryName", _forcedId get "name"]; };
    if ((_forcedId getOrDefault ["alias", ""]) != "") then { _cluster set ["primaryAlias", _forcedId get "alias"]; };
    if ((_forcedId getOrDefault ["phone", ""]) != "") then { _cluster set ["primaryPhone", _forcedId get "phone"]; };
};

private _locs = _model getOrDefault ["locations", []];
if (count _locs > 0) then {
    private _first = _locs select 0;
    if (_first isEqualType []) then {
        _cluster set ["depotGrid", _first select 1];
    };
};

_entity setVariable ["comspec_sse_modelId", _model getOrDefault ["id", ""], true];
_entity setVariable ["comspec_sse_region", _region, true];

[_entity, _profile, _complexity, _createdBy, _cluster] call comspec_sse_fnc_generateData;

// Appliquer forced identity / phone après génération
if (_forcedId isEqualType createHashMap && {count _forcedId > 0}) then {
    private _pairs = [];
    { _pairs pushBack [_x, _y]; } forEach _forcedId;
    [_entity, _pairs] call comspec_sse_fnc_setIdentity;
};

private _forcedPhone = _model getOrDefault ["forcedPhone", createHashMap];
if (_forcedPhone isEqualType createHashMap && {count _forcedPhone > 0}) then {
    private _pairs = [];
    { _pairs pushBack [_x, _y]; } forEach _forcedPhone;
    [_entity, _pairs] call comspec_sse_fnc_setDigitalData;
};

// SMS templates du modèle
private _smsTpl = _model getOrDefault ["smsTemplates", []];
if (count _smsTpl > 0) then {
    private _devices = [_entity, "digitalDevices"] call comspec_sse_fnc_getSection;
    if (!isNil "_devices" && {_devices isEqualType []} && {count _devices > 0}) then {
        private _d = _devices select 0;
        private _sms = [];
        {
            _sms pushBack (createHashMapFromArray [
                ["from", "NETWORK"],
                ["text", _x],
                ["noise", false]
            ]);
        } forEach _smsTpl;
        _d set ["sms", _sms];
        _devices set [0, _d];
        [_entity, "digitalDevices", _devices, true] call comspec_sse_fnc_setSection;
    };
};

// Computer si demandé
if (_model getOrDefault ["includeComputer", false]) then {
    if (!isNil "comspec_sse_fnc_generateComputer") then {
        private _comp = [_seed + 99, _profile, _complexity, _cluster, _pools] call comspec_sse_fnc_generateComputer;
        private _devices = [_entity, "digitalDevices"] call comspec_sse_fnc_getSection;
        if (isNil "_devices" || {!(_devices isEqualType [])}) then { _devices = []; };
        _devices pushBack _comp;
        [_entity, "digitalDevices", _devices, true] call comspec_sse_fnc_setSection;
    };
};

private _data = [_entity] call comspec_sse_fnc_getData;
if (!isNil "_data") then {
    _data = [_data, ["modelId", _model getOrDefault ["id", ""]]] call BIS_fnc_setToPairs;
    _data = [_data, ["modelName", _model getOrDefault ["name", ""]]] call BIS_fnc_setToPairs;
    [_entity, _data, true] call comspec_sse_fnc_setData;
};

[format ["applyModel %1 -> %2", _model getOrDefault ["name", "?"], _entity]] call comspec_sse_fnc_log;
true
