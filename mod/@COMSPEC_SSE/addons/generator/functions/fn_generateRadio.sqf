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
private _f1 = 25 + (([_seed, "f1"] call comspec_sse_fnc_hash) mod 70);
private _f2 = 10 + (([_seed, "f2"] call comspec_sse_fnc_hash) mod 80);
private _freqs = [
    format ["%1.%2 MHz", _freqBase, _f1],
    format ["%1.%2 MHz", _freqBase + 3, _f2]
];
if (_complexity in ["DETAILED", "HIGH_VALUE"]) then {
    private _f3 = ([_seed, "f3"] call comspec_sse_fnc_hash) mod 90;
    _freqs pushBack format ["%1.%2 MHz", _freqBase + 7, _f3];
};

private _codeword = _pack getOrDefault ["codeword", "ORAGE"];
private _packGrid = _pack getOrDefault ["grid", ""];
private _logTexts = [
    "Appel court - confirmé",
    format ["Mot de passe : %1", _codeword],
    "Changement de fréquence demandé",
    format ["RDV évoqué - %1", _packGrid],
    "Silence radio imposé 24 h"
];

private _log = [];
private _nLog = if (_complexity == "LIGHT") then {2} else {4};
for "_i" from 0 to (_nLog - 1) do {
    private _tKey = format ["t%1", _i];
    private _rlKey = format ["rl%1", _i];
    private _hour = 8 + (([_seed, _tKey] call comspec_sse_fnc_hash) mod 12);
    private _when = format ["J-%1 %2h", 1 + _i, _hour];
    private _text = [_seed, _rlKey, _logTexts] call comspec_sse_fnc_pickFromSeed;
    _log pushBack (createHashMapFromArray [
        ["when", _when],
        ["text", _text]
    ]);
};

private _model = [_seed, "rmod", _models] call comspec_sse_fnc_pickFromSeed;
private _netName = [_seed, "net", _nets] call comspec_sse_fnc_pickFromSeed;
private _defaultCs = format ["CS-%1", (_seed mod 99)];
private _callsign = _cluster getOrDefault ["primaryAlias", _defaultCs];
private _encHint = if ((([_seed, "enc"] call comspec_sse_fnc_hash) mod 100) < 35) then {"Chiffrement basique détecté"} else {"En clair"};
private _ownerHint = _cluster getOrDefault ["primaryName", ""];
private _locItem = createHashMapFromArray [
    ["label", "Point radio fréquent"],
    ["grid", _packGrid],
    ["confidence", 0.5]
];
private _uid = format ["SSE-RAD-%1", _seed];
private _summary = format ["Radio %1 - réseau %2", _model, _netName];

createHashMapFromArray [
    ["uid", _uid],
    ["deviceType", "RADIO"],
    ["model", _model],
    ["netName", _netName],
    ["callsign", _callsign],
    ["frequencies", _freqs],
    ["encryptionHint", _encHint],
    ["ownerHint", _ownerHint],
    ["theme", _theme],
    ["codeword", _codeword],
    ["trafficLog", _log],
    ["locations", [_locItem]],
    ["cluster", _cluster],
    ["summary", _summary]
]
