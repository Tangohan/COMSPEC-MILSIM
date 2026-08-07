params [
    ["_entity", objNull, [objNull]],
    ["_seed", 0, [0]]
];

private _bio = [_entity, "biometrics"] call comspec_sse_fnc_getSection;
if (isNil "_bio" || {!(_bio isEqualType createHashMap)}) then { _bio = createHashMap; };

private _fpId = format ["BIO-%1", 1000 + (([_seed, "fp"] call comspec_sse_fnc_hash) mod 9000)];
_bio set ["fingerprintId", _bio getOrDefault ["fingerprintId", _fpId]];
_bio set ["fingerprintLeft", format ["%1-L", _fpId]];
_bio set ["fingerprintRight", format ["%1-R", _fpId]];
_bio set ["irisLeft", format ["IR-L-%1", _seed]];
_bio set ["irisRight", format ["IR-R-%1", _seed]];
_bio set ["dnaId", _bio getOrDefault ["dnaId", format ["DNA-%1", _seed]]];
_bio set ["scars", [["avant-bras", "visage", "aucune", "main droite"] select (([_seed, "scar"] call comspec_sse_fnc_hash) mod 4)]];
_bio set ["tattoos", [["aucune", "symbole local", "texte brachial"] select (([_seed, "tat"] call comspec_sse_fnc_hash) mod 3)]];
_bio set ["physical", createHashMapFromArray [
    ["height", 165 + (([_seed, "h"] call comspec_sse_fnc_hash) mod 30)],
    ["build", ["svelte", "moyen", "robuste"] select (([_seed, "b"] call comspec_sse_fnc_hash) mod 3)]
]];

if (isNil "comspec_sse_biometricIndex") then { comspec_sse_biometricIndex = createHashMap; };
comspec_sse_biometricIndex set [_bio get "fingerprintId", createHashMapFromArray [
    ["entity", netId _entity],
    ["status", "UNMATCHED"],
    ["identity", ""]
]];

_bio
