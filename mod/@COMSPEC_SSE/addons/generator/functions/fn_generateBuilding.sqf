/*
    Génère des données SSE pour un bâtiment / conteneur / site.
    [_seed, _profile, _complexity, _cluster, _pools, _kind] call comspec_sse_fnc_generateBuilding
    _kind: BUILDING | CONTAINER | OBJECT
*/
params [
    ["_seed", 0, [0]],
    ["_profile", "INSURGENT", [""]],
    ["_complexity", "STANDARD", [""]],
    ["_cluster", createHashMap, [createHashMap]],
    ["_pools", createHashMap, [createHashMap]],
    ["_kind", "BUILDING", [""]]
];

if (count _pools == 0) then {
    _pools = [_cluster getOrDefault ["region", "IRAQ"]] call comspec_sse_fnc_getNarrativePools;
};

private _theme = _cluster getOrDefault ["theme", "safehouse"];
private _pack = [_theme, _seed, _cluster, _pools] call comspec_sse_fnc_getThemePack;

private _rooms = ["entrée", "pièce principale", "arrière-cour", "cave", "étage", "annexe"];
private _traces = [];
private _n = switch (_complexity) do {
    case "LIGHT": { 2 };
    case "HIGH_VALUE": { 5 };
    case "DETAILED": { 4 };
    default { 3 };
};
for "_i" from 0 to (_n - 1) do {
    _traces pushBack (createHashMapFromArray [
        ["area", [_seed, format ["rm%1", _i], _rooms] call comspec_sse_fnc_pickFromSeed],
        ["note", [_seed, format ["tr%1", _i], [
            "Traces de passage récentes",
            "Cartographie improvisée au mur",
            format ["Mention de %1", _pack getOrDefault ["codeword", "ORAGE"]],
            "Déchets alimentaires — occupation courte",
            "Câblage / chargeur téléphone",
            format ["Repère grid : %1", _pack getOrDefault ["grid", ""]]
        ]] call comspec_sse_fnc_pickFromSeed]
    ]);
};

private _docCount = if (_kind == "CONTAINER") then {2} else { if (_complexity == "LIGHT") then {1} else {3}; };
private _docs = [_seed, _docCount, _cluster, _pools] call comspec_sse_fnc_generateDocument;

private _hasPhone = (([_seed, "bphone"] call comspec_sse_fnc_hash) mod 100) < (if (_complexity == "HIGH_VALUE") then {70} else {35});
private _devices = [];
if (_hasPhone) then {
    _devices pushBack ([_seed + 11, _profile, _complexity, _cluster] call comspec_sse_fnc_generatePhone);
};

private _grid = _pack getOrDefault ["grid", ""];
if (_grid isEqualTo "") then { _grid = _cluster getOrDefault ["depotGrid", ""]; };
private _themeLabel = _pack getOrDefault ["themeLabel", _theme];
private _primaryName = _cluster getOrDefault ["primaryName", "?"];
private _locItem = createHashMapFromArray [
    ["label", "Point d'intérêt lié"],
    ["grid", _grid],
    ["confidence", 0.55]
];
private _intelItem = createHashMapFromArray [
    ["text", format ["Site cohérent avec %1 — %2", _primaryName, _themeLabel]],
    ["confidence", 0.57]
];

createHashMapFromArray [
    ["uid", format ["SSE-%1-%2", _kind, _seed]],
    ["siteKind", _kind],
    ["theme", _theme],
    ["occupancyHint", [_seed, "occ", ["abandonné récemment", "occupé sporadiquement", "actif", "couverture civile"]] call comspec_sse_fnc_pickFromSeed],
    ["traces", _traces],
    ["documents", _docs],
    ["digitalDevices", _devices],
    ["locations", [_locItem]],
    ["intel", [_intelItem]],
    ["cluster", _cluster],
    ["summary", format ["%1 — %2", _kind, _themeLabel]]
]
