/*
    Crée un cluster narratif partagé (bootstrap LÉGER).

    IMPORTANT: ne plus appeler generatePerson ici.
    Avant: generateData → generateCluster → generatePerson → generatePerson → generatePhone
    empilait 2–3 générations complètes dans la même frame → pic mémoire / STACK_OVERFLOW.
*/
params [
    ["_seed", 0, [0]],
    ["_profile", "INSURGENT", [""]],
    ["_complexity", "DETAILED", [""]],
    ["_region", "IRAQ", [""]]
];

_profile = [_profile] call comspec_sse_fnc_resolveProfile;
_complexity = toUpper _complexity;
_region = toUpper _region;

private _clusterId = format ["CLUS-%1", [_seed, "cluster", 9] call comspec_sse_fnc_idToken];
private _pools = [_region] call comspec_sse_fnc_getNarrativePools;

private _fn = [_seed, "fn", _pools getOrDefault ["firstNames", ["Ali"]]] call comspec_sse_fnc_pickFromSeed;
private _ln = [_seed, "ln", _pools getOrDefault ["lastNames", ["Hassan"]]] call comspec_sse_fnc_pickFromSeed;
private _name = format ["%1 %2", _fn, _ln];
private _alias = [_seed, "alias", _pools getOrDefault ["aliases", ["ABU X"]]] call comspec_sse_fnc_pickFromSeed;
private _prefix = [_seed, "pfx", _pools getOrDefault ["phonePrefixes", ["+964"]]] call comspec_sse_fnc_pickFromSeed;
private _phone = format ["%1 %2 %3",
    _prefix,
    ([_seed, "p1"] call comspec_sse_fnc_hash) mod 1000,
    ([_seed, "p2"] call comspec_sse_fnc_hash) mod 10000
];
private _theme = [_seed, "theme", _pools getOrDefault ["themes", ["fuel_delivery"]]] call comspec_sse_fnc_pickFromSeed;

private _aliases = _pools getOrDefault ["aliases", []];
private _contacts = [];
private _pool = +_aliases;
{ if !(_x in _pool) then { _pool pushBack _x; }; } forEach ["FARID", "MUSTAFA", "OMAR SALEH", "THE DRIVER"];
for "_i" from 0 to 3 do {
    private _c = [_seed, format ["c%1", _i], _pool] call comspec_sse_fnc_pickFromSeed;
    if !(_c in _contacts) then { _contacts pushBack _c; };
};
if !(_alias in _contacts) then { _contacts pushBack _alias; };

private _cluster = createHashMapFromArray [
    ["clusterId", _clusterId],
    ["profile", _profile],
    ["complexity", _complexity],
    ["region", _region],
    ["primaryName", _name],
    ["primaryAlias", _alias],
    ["primaryPhone", _phone],
    ["theme", _theme],
    ["networkContacts", _contacts],
    ["bootLight", true]
];

[format ["generateCluster %1 theme=%2 region=%3 (light)", _clusterId, _theme, _region]] call comspec_sse_fnc_log;
_cluster
