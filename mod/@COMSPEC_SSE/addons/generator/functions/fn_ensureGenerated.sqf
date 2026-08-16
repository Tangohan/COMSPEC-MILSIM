/*
    Lazy generation — appelé au premier examen.
    [_entity] call comspec_sse_fnc_ensureGenerated
*/
params [
    ["_entity", objNull, [objNull]]
];

if (isNull _entity) exitWith { false };

if (_entity getVariable ["comspec_sse_generating", false]) exitWith {
    false
};

private _bridgeBii = {
    params ["_ent"];
    if (
        isNull _ent
        || {!(missionNamespace getVariable ["comspec_sse_biiBridgeEnabled", true])}
        || {isNil "comspec_sse_fnc_biiIsPresent"}
        || {!([] call comspec_sse_fnc_biiIsPresent)}
        || {missionNamespace getVariable ["comspec_sse_biiInEnsureBridge", false]}
    ) exitWith {};
    missionNamespace setVariable ["comspec_sse_biiInEnsureBridge", true];
    if (!isNil "comspec_sse_fnc_biiImportEntityVars") then {
        [_ent] call comspec_sse_fnc_biiImportEntityVars;
    };
    if (
        missionNamespace getVariable ["comspec_sse_biiExportToBii", true]
        && {!isNil "comspec_sse_fnc_biiExportEntityVars"}
    ) then {
        [_ent] call comspec_sse_fnc_biiExportEntityVars;
    };
    missionNamespace setVariable ["comspec_sse_biiInEnsureBridge", false];
};

private _pendingDs = _entity getVariable ["comspec_sse_pendingDatasetId", ""];
private _pendingRole = _entity getVariable ["comspec_sse_pendingDatasetRole", ""];
if (
    _pendingDs isNotEqualTo ""
    && {_pendingRole isNotEqualTo ""}
    && {!isNil "comspec_sse_fnc_loadDataset"}
    && {!isNil "comspec_sse_fnc_applyDatasetRole"}
) exitWith {
    _entity setVariable ["comspec_sse_pendingDatasetId", "", true];
    _entity setVariable ["comspec_sse_pendingDatasetRole", "", true];
    private _ds = [_pendingDs] call comspec_sse_fnc_loadDataset;
    if (!isNil "_ds" && {_ds isEqualType createHashMap} && {count _ds > 0}) then {
        private _roles = _ds getOrDefault ["roles", []];
        private _idx = _roles findIf { (_x getOrDefault ["roleId", ""]) == _pendingRole };
        if (_idx >= 0) then {
            [_entity, _roles select _idx, _ds, "LAZY"] call comspec_sse_fnc_applyDatasetRole;
        };
    };
    [_entity] call _bridgeBii;
    true
};

private _pendingModel = _entity getVariable ["comspec_sse_pendingModelId", ""];
if (_pendingModel isNotEqualTo "" && {!isNil "comspec_sse_fnc_applyModel"}) exitWith {
    _entity setVariable ["comspec_sse_pendingModelId", "", true];
    [_entity, _pendingModel, "LAZY"] call comspec_sse_fnc_applyModel;
    [_entity] call _bridgeBii;
    true
};

private _data = [_entity] call comspec_sse_fnc_getData;
if (isNil "_data") then {
    private _type = [_entity] call comspec_sse_fnc_resolveEntityType;
    [_entity, _type] call comspec_sse_fnc_makeSearchable;
    _data = [_entity] call comspec_sse_fnc_getData;
};

if ([_data, "lazyReady", false] call comspec_sse_fnc_getPair) exitWith {
    [_entity] call _bridgeBii;
    true
};
if ([_data, "generated", false] call comspec_sse_fnc_getPair) exitWith {
    _data = [_data, "lazyReady", true] call comspec_sse_fnc_setPair;
    [_entity, _data, true] call comspec_sse_fnc_setData;
    [_entity] call _bridgeBii;
    true
};

private _profile = [_data, "profile", "RANDOM"] call comspec_sse_fnc_getPair;
private _complexity = [_data, "complexity", "STANDARD"] call comspec_sse_fnc_getPair;

[format ["ensureGenerated (lazy) %1", _entity]] call comspec_sse_fnc_log;
[_entity, _profile, _complexity, "LAZY"] call comspec_sse_fnc_generateData;
[_entity] call _bridgeBii;
true
