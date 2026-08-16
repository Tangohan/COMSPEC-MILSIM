params [["_seed", 0, [0]]];

private _hCat = [_seed, "tc"] call comspec_sse_fnc_hash;
private _hSer = [_seed, "ts"] call comspec_sse_fnc_hash;
private _hLot = [_seed, "tl"] call comspec_sse_fnc_hash;
private _hMan = [_seed, "tm"] call comspec_sse_fnc_hash;
private _hOri = [_seed, "to"] call comspec_sse_fnc_hash;

private _cats = ["WEAPON", "MUNITION", "DRONE", "ELECTRONIC", "SPECIAL"];
private _mans = ["Local", "Import", "Inconnu", "Atelier cellule"];
private _oris = ["cache", "contrebande", "prise de guerre", "inconnu"];
private _tags = ["TECH", "WEAPONS"];

private _uid = format ["SSE-TECH-%1", _seed];
private _category = _cats select (_hCat mod 5);
private _serial = format ["TN-%1", _hSer];
private _lot = format ["LOT-%1", 100 + (_hLot mod 900)];
private _manufacturer = _mans select (_hMan mod 4);
private _origin = _oris select (_hOri mod 4);
private _compatibility = "TECHINT - correlation serie / lot";

createHashMapFromArray [
    ["uid", _uid],
    ["category", _category],
    ["serial", _serial],
    ["lot", _lot],
    ["manufacturer", _manufacturer],
    ["origin", _origin],
    ["compatibility", _compatibility],
    ["tags", _tags]
]
