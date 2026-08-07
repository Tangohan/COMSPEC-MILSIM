params [["_seed", 0, [0]]];
private _photos = [];
for "_i" from 0 to 2 do {
    _photos pushBack (createHashMapFromArray [
        ["id", format ["OPT-%1-%2", _seed, _i]],
        ["caption", ["Vue site", "Véhicule", "Visage flou", "Document photographié", "Point GPS"] select (([_seed, format ["op%1", _i]] call comspec_sse_fnc_hash) mod 5)],
        ["grid", format ["%1%2", 100 + (([_seed, format ["og%1", _i]] call comspec_sse_fnc_hash) mod 80), 200 + (([_seed, format ["og2%1", _i]] call comspec_sse_fnc_hash) mod 80)]],
        ["timestamp", format ["J-%1 %2:00", _i + 1, 8 + _i]],
        ["meta", "EXIF simulé"]
    ]);
};
createHashMapFromArray [
    ["uid", format ["SSE-OPT-%1", _seed]],
    ["photos", _photos],
    ["device", ["Appareil photo", "Caméra", "Drone", "Jumelles numériques"] select (([_seed, "odev"] call comspec_sse_fnc_hash) mod 4)]
]
