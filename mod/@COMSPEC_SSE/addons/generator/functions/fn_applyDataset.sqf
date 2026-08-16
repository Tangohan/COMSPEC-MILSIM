/*
    Applique un dataset sur les unités dans un rayon.
    [_datasetId, _center, _radius, _level] call comspec_sse_fnc_applyDataset
*/
params [
    ["_datasetId", "falcon", [""]],
    ["_center", objNull, [objNull, []]],
    ["_radius", 50, [0]],
    ["_level", -1, [0]]
];

private _ds = [_datasetId] call comspec_sse_fnc_loadDataset;
if (isNil "_ds" || {!(_ds isEqualType createHashMap)} || {count _ds == 0}) exitWith {
    [format ["applyDataset: dataset introuvable (%1)", _datasetId], "ERROR"] call comspec_sse_fnc_log;
    []
};

private _pos = if (_center isEqualType []) then { _center } else {
    if (isNull _center) then { getPosATL player } else { getPosATL _center }
};

if (_level < 0) then {
    _level = missionNamespace getVariable ["comspec_sse_scenarioLevel", _ds getOrDefault ["defaultLevel", 1]];
};
_level = (_level max 0) min 3;
missionNamespace setVariable ["comspec_sse_scenarioLevel", _level, true];
missionNamespace setVariable ["comspec_sse_activeDataset", _ds getOrDefault ["id", "falcon"], true];
missionNamespace setVariable ["comspec_sse_missionSeed", _ds getOrDefault ["seed", ""], true];

private _roles = _ds getOrDefault ["roles", []];
private _eligible = _roles select { (_x getOrDefault ["minLevel", 0]) <= _level };
private _units = nearestObjects [_pos, ["CAManBase"], _radius];
private _created = [];
private _roleMap = createHashMap; // roleId -> unit

private _n = (count _eligible) min (count _units);
for "_i" from 0 to (_n - 1) do {
    private _role = _eligible select _i;
    private _u = _units select _i;
    [_u, _role, _ds, "DATASET"] call comspec_sse_fnc_applyDatasetRole;
    _roleMap set [_role getOrDefault ["roleId", format ["r%1", _i]], _u];
    _created pushBack _u;
};

// Liens relationnels si les deux côtés existent
{
    private _fromId = _x getOrDefault ["from", ""];
    private _toId = _x getOrDefault ["to", ""];
    private _rel = _x getOrDefault ["relation", "LINKED"];
    private _a = _roleMap getOrDefault [_fromId, objNull];
    private _b = _roleMap getOrDefault [_toId, objNull];
    if (!isNull _a && {!isNull _b}) then {
        [_a, _b, _rel] call comspec_sse_fnc_linkEntities;
    };
} forEach (_ds getOrDefault ["links", []]);

if (!isNil "comspec_sse_fnc_addLogicalEntity") then {
    [_ds getOrDefault ["name", "Dataset"], "ORGANIZATION", createHashMapFromArray [
        ["label", _ds getOrDefault ["name", ""]],
        ["seed", _ds getOrDefault ["seed", ""]],
        ["datasetId", _ds getOrDefault ["id", ""]],
        ["level", _level],
        ["members", count _created]
    ]] call comspec_sse_fnc_addLogicalEntity;
};

private _lvlMeta = (_ds getOrDefault ["levels", createHashMap]) getOrDefault [str _level, createHashMap];
hint format [
    "%1\nGraine : %2\nNiveau : %3 — %4\n%5 rôle(s)",
    _ds getOrDefault ["name", "Dataset"],
    _ds getOrDefault ["seed", ""],
    _level,
    _lvlMeta getOrDefault ["label", ""],
    count _created
];

[format ["applyDataset %1 level=%2 n=%3", _ds getOrDefault ["id", "?"], _level, count _created], "WARN"] call comspec_sse_fnc_log;
_created
