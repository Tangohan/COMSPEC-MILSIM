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

private _notes = [
    format ["Série apparente : %1", _serial],
    [_seed, "mark", _marks] call comspec_sse_fnc_pickFromSeed,
    format ["Lien possible : cache %1", _pack getOrDefault ["grid", _cluster getOrDefault ["depotGrid", "?"])]
];
if (_complexity in ["DETAILED", "HIGH_VALUE"]) then {
    _notes pushBack format ["Codage interne : %1", _pack getOrDefault ["codeword", ""]];
    _notes pushBack format ["Propriétaire présumé : %1", _cluster getOrDefault ["primaryAlias", _cluster getOrDefault ["primaryName", "?"]]];
};

createHashMapFromArray [
    ["uid", format ["SSE-WPN-%1", _seed]],
    ["weaponKind", [_seed, "wk", _types] call comspec_sse_fnc_pickFromSeed],
    ["serial", _serial],
    ["markings", [_seed, "mark", _marks] call comspec_sse_fnc_pickFromSeed],
    ["condition", [_seed, "cond", ["bon", "usé", "oxydé", "récent"]] call comspec_sse_fnc_pickFromSeed],
    ["theme", _theme],
    ["notes", _notes],
    ["documents", [_seed + 2, 1, _cluster, _pools] call comspec_sse_fnc_generateDocument],
    ["intel", [
        createHashMapFromArray [
            ["text", format ["Armement cohérent avec thème « %1 »", _pack getOrDefault ["themeLabel", _theme]]],
            ["confidence", 0.62]
        ]
    ]],
    ["cluster", _cluster],
    ["summary", format ["%1 — %2", [_seed, "wk", _types] call comspec_sse_fnc_pickFromSeed, _serial]]
]
