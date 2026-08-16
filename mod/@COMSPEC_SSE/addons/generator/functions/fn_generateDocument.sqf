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
private _grid = _pack getOrDefault ["grid", ""];
if (_grid isEqualTo "") then { _grid = _cluster getOrDefault ["depotGrid", ""]; };
private _docs = [];
private _docTypes = _pools getOrDefault ["documentTypes", ["Document"]];
private _packSummary = _pack getOrDefault ["summary", ""];
private _packCodeword = _pack getOrDefault ["codeword", ""];
private _packTitle = _pack getOrDefault ["documentTitle", "Document"];

for "_i" from 0 to (_count - 1) do {
    private _title = if (_i == 0) then {
        if (count _titlesOverride > 0) then { _titlesOverride select 0 } else { _packTitle }
    } else {
        if (_i < count _titlesOverride) then {
            _titlesOverride select _i
        } else {
            private _docKey = format ["doc%1", _i];
            [_seed, _docKey, _docTypes] call comspec_sse_fnc_pickFromSeed
        }
    };

    private _uid = format ["SSE-DOC-%1-%2", _seed, _i];
    private _summary = if (_i == 0) then {
        _packSummary
    } else {
        // Varier les résumés secondaires (évite « Document secondaire » × N identiques).
        private _alts = [
            "Annotation manuscrite — lieu et horaire partiels.",
            "Photographie de contexte — arrière-plan exploitable.",
            "Reçu / bordereau — montants partiellement lisibles.",
            "Liste de contacts — plusieurs numéros barrés.",
            "Carte annotée — itinéraire approximatif.",
            "Note rapide — rappel d’un rendez-vous.",
            "Document usé — en-tête illisible, corps partiel."
        ];
        private _sk = format ["sum%1", _i];
        [_seed, _sk, _alts] call comspec_sse_fnc_pickFromSeed
    };
    private _docGrid = _grid;
    private _codeword = if (_i == 0) then { _packCodeword } else { "" };
    private _dnKey = format ["dn%1", _i];
    private _noise = _i > 0 && {(([_seed, _dnKey] call comspec_sse_fnc_hash) mod 100) < 30};

    _docs pushBack (createHashMapFromArray [
        ["uid", _uid],
        ["title", _title],
        ["summary", _summary],
        ["grid", _docGrid],
        ["theme", _theme],
        ["codeword", _codeword],
        ["noise", _noise]
    ]);
};

_docs
