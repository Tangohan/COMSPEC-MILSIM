/*
    Génère un ou plusieurs documents liés au thème.
    [_seed, _count, _cluster, _pools, _titlesOverride] call comspec_sse_fnc_generateDocument
*/
params [
    ["_seed", 0, [0]],
    ["_count", 2, [0]],
    ["_cluster", createHashMap, [createHashMap]],
    ["_pools", createHashMap, [createHashMap]],
    ["_titlesOverride", [], [[]]]
];

if (count _pools == 0) then {
    _pools = [_cluster getOrDefault ["region", "IRAQ"]] call comspec_sse_fnc_getNarrativePools;
};

private _theme = _cluster getOrDefault ["theme", "fuel_delivery"];
private _pack = [_theme, _seed, _cluster, _pools] call comspec_sse_fnc_getThemePack;
private _grid = _pack getOrDefault ["grid", _cluster getOrDefault ["depotGrid", ""]];
private _docs = [];

for "_i" from 0 to (_count - 1) do {
    private _title = if (_i == 0) then {
        if (count _titlesOverride > 0) then { _titlesOverride select 0 } else { _pack getOrDefault ["documentTitle", "Document"] }
    } else {
        if (_i < count _titlesOverride) then {
            _titlesOverride select _i
        } else {
            [_seed, format ["doc%1", _i], _pools getOrDefault ["documentTypes", ["Document"]]] call comspec_sse_fnc_pickFromSeed
        }
    };

    _docs pushBack (createHashMapFromArray [
        ["uid", format ["SSE-DOC-%1-%2", _seed, _i]],
        ["title", _title],
        ["summary", if (_i == 0) then { _pack getOrDefault ["summary", ""] } else { "Document secondaire / contexte." }],
        ["grid", if (_i == 0) then { _grid } else { "" }],
        ["theme", _theme],
        ["codeword", if (_i == 0) then { _pack getOrDefault ["codeword", ""] } else { "" }],
        ["noise", _i > 0 && {(([_seed, format ["dn%1", _i]] call comspec_sse_fnc_hash) mod 100) < 30}]
    ]);
};

_docs
