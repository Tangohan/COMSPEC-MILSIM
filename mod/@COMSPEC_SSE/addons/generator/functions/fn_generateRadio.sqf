/*
    Génère des données SSE pour une radio / téléphone sat.
    [_seed, _profile, _complexity, _cluster, _pools] call comspec_sse_fnc_generateRadio
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

private _theme = _cluster getOrDefault ["theme", "courier_run"];
private _pack = [_theme, _seed, _cluster, _pools] call comspec_sse_fnc_getThemePack;

private _models = ["PRC-152", "PRC-148", "AN/PRC-343", "Satcom portable", "Talkie civil"];
private _nets = ["NET-ALPHA", "NET-BRAVO", "CELLULE-NORD", "LOG-SUD", "CMD-LOCAL"];
private _freqBase = 30 + (([_seed, "freq"] call comspec_sse_fnc_hash) mod 50);
private _freqs = [
    format ["%1.%2 MHz", _freqBase, 25 + (([_seed, "f1"] call comspec_sse_fnc_hash) mod 70)],
    format ["%1.%2 MHz", _freqBase + 3, 10 + (([_seed, "f2"] call comspec_sse_fnc_hash) mod 80)]
];
if (_complexity in ["DETAILED", "HIGH_VALUE"]) then {
    _freqs pushBack format ["%1.%2 MHz", _freqBase + 7, (([_seed, "f3"] call comspec_sse_fnc_hash) mod 90)];
};

private _log = [];
private _nLog = if (_complexity == "LIGHT") then {2} else {4};
for "_i" from 0 to (_nLog - 1) do {
    _log pushBack (createHashMapFromArray [
        ["when", format ["J-%1 %2h", 1 + _i, 8 + (([_seed, format ["t%1", _i]] call comspec_sse_fnc_hash) mod 12)]],
        ["text", [_seed, format ["rl%1", _i], [
            "Appel court — confirmé",
            format ["Mot de passe : %1", _pack getOrDefault ["codeword", "ORAGE"]],
            "Changement de fréquence demandé",
            format ["RDV évoqué — %1", _pack getOrDefault ["grid", ""]],
            "Silence radio imposé 24 h"
        ]] call comspec_sse_fnc_pickFromSeed]
    ]);
};

createHashMapFromArray [
    ["uid", format ["SSE-RAD-%1", _seed]],
    ["deviceType", "RADIO"],
    ["model", [_seed, "rmod", _models] call comspec_sse_fnc_pickFromSeed],
    ["netName", [_seed, "net", _nets] call comspec_sse_fnc_pickFromSeed],
    ["callsign", _cluster getOrDefault ["primaryAlias", format ["CS-%1", (_seed mod 99)]]],
    ["frequencies", _freqs],
    ["encryptionHint", if ((([_seed, "enc"] call comspec_sse_fnc_hash) mod 100) < 35) then {"Chiffrement basique détecté"} else {"En clair"}],
    ["ownerHint", _cluster getOrDefault ["primaryName", ""]],
    ["theme", _theme],
    ["codeword", _pack getOrDefault ["codeword", ""]],
    ["trafficLog", _log],
    ["locations", [
        createHashMapFromArray [
            ["label", "Point radio fréquent"],
            ["grid", _pack getOrDefault ["grid", ""]],
            ["confidence", 0.5]
        ]
    ]],
    ["cluster", _cluster],
    ["summary", format ["Radio %1 — réseau %2", [_seed, "rmod", _models] call comspec_sse_fnc_pickFromSeed, [_seed, "net", _nets] call comspec_sse_fnc_pickFromSeed]]
]
