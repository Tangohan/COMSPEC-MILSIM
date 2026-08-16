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

createHashMapFromArray [
    ["uid", format ["SSE-TECH-%1", _seed]],
    ["category", _cats select (_hCat mod 5)],
    ["serial", format ["TN-%1", _hSer]],
    ["lot", format ["LOT-%1", 100 + (_hLot mod 900))],
    ["manufacturer", _mans select (_hMan mod 4)],
    ["origin", _oris select (_hOri mod 4)],
    ["compatibility", "TECHINT — corrélation série / lot"],
    ["tags", _tags]
]
