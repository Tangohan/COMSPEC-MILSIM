/*
    Génère un support USB/SD lié au cluster.
    [_seed, _kind, _cluster] call comspec_sse_fnc_generateUSB
    _kind: USB | SDCARD | HARDDRIVE
*/
params [
    ["_seed", 0, [0]],
    ["_kind", "USB", [""]],
    ["_cluster", createHashMap, [createHashMap]]
];

private _theme = _cluster getOrDefault ["theme", "fuel_delivery"];
private _region = _cluster getOrDefault ["region", "IRAQ"];
private _pools = [_region] call comspec_sse_fnc_getNarrativePools;
private _pack = [_theme, _seed, _cluster, _pools] call comspec_sse_fnc_getThemePack;

private _files = (_pack getOrDefault ["computerFiles", ["notes.txt"]]) apply {
    createHashMapFromArray [["name", _x], ["relevant", true]]
};
_files pushBack (createHashMapFromArray [["name", "musique.mp3"], ["relevant", false]]);

createHashMapFromArray [
    ["uid", format ["SSE-MED-%1", [_seed, "med", 9] call comspec_sse_fnc_idToken]],
    ["deviceType", toUpper _kind],
    ["label", format ["%1-%2", toUpper _kind, ([_seed, "lbl"] call comspec_sse_fnc_hash) mod 9999]],
    ["owner", _cluster getOrDefault ["primaryName", "UNKNOWN"]],
    ["files", _files],
    ["theme", _theme],
    ["codeword", _pack getOrDefault ["codeword", ""]],
    ["summary", _pack getOrDefault ["summary", ""]],
    ["cluster", _cluster]
]
