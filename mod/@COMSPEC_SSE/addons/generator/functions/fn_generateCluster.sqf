/*
    Crée un cluster narratif partagé.
    [_seed, _profile, _complexity, _region] call comspec_sse_fnc_generateCluster
*/
params [
    ["_seed", 0, [0]],
    ["_profile", "INSURGENT", [""]],
    ["_complexity", "DETAILED", [""]],
    ["_region", "IRAQ", [""]]
];

private _clusterId = format ["CLUS-%1", [_seed, "cluster"] call comspec_sse_fnc_hash];
private _cluster = createHashMapFromArray [
    ["clusterId", _clusterId],
    ["profile", [_profile] call comspec_sse_fnc_resolveProfile],
    ["complexity", toUpper _complexity],
    ["region", toUpper _region]
];

private _boot = [_seed, _profile, _complexity, _cluster] call comspec_sse_fnc_generatePerson;
_cluster = _boot getOrDefault ["cluster", _cluster];
_cluster set ["bootPerson", _boot];

[format ["generateCluster %1 theme=%2 region=%3", _clusterId, _cluster getOrDefault ["theme", "?"], _region]] call comspec_sse_fnc_log;
_cluster
