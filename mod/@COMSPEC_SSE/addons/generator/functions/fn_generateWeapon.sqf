/*
    Génère des données SSE pour une arme / lot d'armes.
    [_seed, _profile, _complexity, _cluster, _pools] call comspec_sse_fnc_generateWeapon
*/
params [
    ["_seed", 0, [0]],
    ["_profile", "INSURGENT", [""]],
    ["_complexity", "STANDARD", [""]],
    ["_cluster", createHashMap, [createHashMap]],
    ["_pools", createHashMap, [createHashMap]]
];

if (count _pools == 0) then {
    _pools = [_cluster getOrDefault ["region", "IRAQ"]] call comspec_sse_fnc_getNarrativePools;
};

private _theme = _cluster getOrDefault ["theme", "weapons_cache"];
private _pack = [_theme, _seed, _cluster, _pools] call comspec_sse_fnc_getThemePack;

private _types = ["fusil d'assaut", "mitrailleuse légère", "lance-roquettes", "pistolet", "fusil de précision"];
private _marks = ["numéro gratté", "marque atelier local", "aucune", "cachet cellule", "peinture récente"];
private _serial = format ["WPN-%1-%2", [_seed, "ws"] call comspec_sse_fnc_hash, 100 + (([_seed, "wn"] call comspec_sse_fnc_hash) mod 900)];

private _grid = _pack getOrDefault ["grid", ""];
if (_grid isEqualTo "") then { _grid = _cluster getOrDefault ["depotGrid", "?"]; };
private _ownerHint = _cluster getOrDefault ["primaryAlias", ""];
if (_ownerHint isEqualTo "") then { _ownerHint = _cluster getOrDefault ["primaryName", "?"]; };
private _mark = [_seed, "mark", _marks] call comspec_sse_fnc_pickFromSeed;
private _kind = [_seed, "wk", _types] call comspec_sse_fnc_pickFromSeed;
private _cond = [_seed, "cond", ["bon", "usé", "oxydé", "récent"]] call comspec_sse_fnc_pickFromSeed;
private _themeLabel = _pack getOrDefault ["themeLabel", _theme];
private _codeword = _pack getOrDefault ["codeword", ""];
private _docs = [_seed + 2, 1, _cluster, _pools] call comspec_sse_fnc_generateDocument;

private _notes = [
    format ["Série apparente : %1", _serial],
    _mark,
    format ["Lien possible : cache %1", _grid]
];
if (_complexity in ["DETAILED", "HIGH_VALUE"]) then {
    _notes pushBack format ["Codage interne : %1", _codeword];
    _notes pushBack format ["Propriétaire présumé : %1", _ownerHint];
};

private _intelItem = createHashMapFromArray [
    ["text", format ["Armement cohérent avec thème « %1 »", _themeLabel]],
    ["confidence", 0.62]
];

createHashMapFromArray [
    ["uid", format ["SSE-WPN-%1", _seed]],
    ["weaponKind", _kind],
    ["serial", _serial],
    ["markings", _mark],
    ["condition", _cond],
    ["theme", _theme],
    ["notes", _notes],
    ["documents", _docs],
    ["intel", [_intelItem]],
    ["cluster", _cluster],
    ["summary", format ["%1 — %2", _kind, _serial]]
]
