params [["_seed", 0, [0]]];
private _photos = [];
private _captions = ["Vue site", "Véhicule", "Visage flou", "Document photographié", "Point GPS"];
private _devices = ["Appareil photo", "Caméra", "Drone", "Jumelles numériques"];

for "_i" from 0 to 2 do {
    private _opKey = format ["op%1", _i];
    private _ogKey = format ["og%1", _i];
    private _og2Key = format ["og2%1", _i];
    private _capIdx = ([_seed, _opKey] call comspec_sse_fnc_hash) mod 5;
    private _g1 = 100 + (([_seed, _ogKey] call comspec_sse_fnc_hash) mod 80);
    private _g2 = 200 + (([_seed, _og2Key] call comspec_sse_fnc_hash) mod 80);
    private _id = format ["OPT-%1-%2", _seed, _i];
    private _grid = format ["%1%2", _g1, _g2];
    private _timestamp = format ["J-%1 %2:00", _i + 1, 8 + _i];
    private _caption = _captions select _capIdx;

    _photos pushBack (createHashMapFromArray [
        ["id", _id],
        ["caption", _caption],
        ["grid", _grid],
        ["timestamp", _timestamp],
        ["meta", "EXIF simulé"]
    ]);
};

private _uid = format ["SSE-OPT-%1", _seed];
private _devIdx = ([_seed, "odev"] call comspec_sse_fnc_hash) mod 4;
private _device = _devices select _devIdx;

createHashMapFromArray [
    ["uid", _uid],
    ["photos", _photos],
    ["device", _device]
]
