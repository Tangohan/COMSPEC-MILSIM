/*
    Génère des données SSE pour un véhicule.
    [_seed, _profile, _complexity, _cluster, _pools] call comspec_sse_fnc_generateVehicle
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

private _theme = _cluster getOrDefault ["theme", "fuel_delivery"];
private _pack = [_theme, _seed, _cluster, _pools] call comspec_sse_fnc_getThemePack;

private _colors = ["blanc", "beige", "vert olive", "noir", "gris", "marron"];
private _makes = ["Toyota", "Nissan", "Kia", "Hyundai", "Mitsubishi", "UAZ", "camion local"];
private _plate = format [
    "%1-%2%3",
    [_seed, "plA", ["12", "22", "33", "41", "55", "67"]] call comspec_sse_fnc_pickFromSeed,
    [_seed, "plB", ["A", "B", "D", "H", "K", "M", "R"]] call comspec_sse_fnc_pickFromSeed,
    str (1000 + (([_seed, "plN"] call comspec_sse_fnc_hash) mod 9000))
];
private _vin = format ["SSEVIN%1", [_seed, "vin"] call comspec_sse_fnc_hash];

private _cargoHints = [
    "Traces de carburant au plancher",
    "Caisse ouverte — reste de bandes",
    "Plan sous le siège conducteur",
    "Outils et gants usés",
    "Rien d’évident au premier regard",
    format ["Note manuscrite : %1", _pack getOrDefault ["deliveryNote", "RDV reporté"]]
];

private _cargo = [];
private _nCargo = switch (_complexity) do {
    case "LIGHT": { 1 };
    case "HIGH_VALUE": { 4 };
    case "DETAILED": { 3 };
    default { 2 };
};
for "_i" from 0 to (_nCargo - 1) do {
    _cargo pushBack ([_seed, format ["cargo%1", _i], _cargoHints] call comspec_sse_fnc_pickFromSeed);
};

private _docs = [_seed + 3, 1, _cluster, _pools] call comspec_sse_fnc_generateDocument;

private _grid = _pack getOrDefault ["grid", ""];
if (_grid isEqualTo "") then { _grid = _cluster getOrDefault ["depotGrid", ""]; };
private _themeLabel = _pack getOrDefault ["themeLabel", _theme];
private _primaryName = _cluster getOrDefault ["primaryName", "?"];
private _make = [_seed, "make", _makes] call comspec_sse_fnc_pickFromSeed;
private _color = [_seed, "color", _colors] call comspec_sse_fnc_pickFromSeed;
private _locItem = createHashMapFromArray [
    ["label", "Dernier point noté"],
    ["grid", _grid],
    ["confidence", 0.6]
];
private _intelItem = createHashMapFromArray [
    ["text", format ["Véhicule potentiellement lié à %1 (%2)", _primaryName, _themeLabel]],
    ["confidence", 0.58]
];

createHashMapFromArray [
    ["uid", format ["SSE-VEH-%1", _seed]],
    ["make", _make],
    ["color", _color],
    ["plate", _plate],
    ["vin", _vin],
    ["ownerHint", _cluster getOrDefault ["primaryName", "inconnu"]],
    ["linkedAlias", _cluster getOrDefault ["primaryAlias", ""]],
    ["theme", _theme],
    ["cargoNotes", _cargo],
    ["documents", _docs],
    ["locations", [_locItem]],
    ["intel", [_intelItem]],
    ["cluster", _cluster],
    ["summary", format ["%1 %2 — plaque %3", _make, _color, _plate]]
]
