params [["_seed", 0, [0]]];
createHashMapFromArray [
    ["uid", format ["SSE-TECH-%1", _seed]],
    ["category", ["WEAPON", "MUNITION", "DRONE", "ELECTRONIC", "SPECIAL"] select (([_seed, "tc"] call comspec_sse_fnc_hash) mod 5)],
    ["serial", format ["TN-%1", [_seed, "ts"] call comspec_sse_fnc_hash]],
    ["lot", format ["LOT-%1", 100 + (([_seed, "tl"] call comspec_sse_fnc_hash) mod 900))],
    ["manufacturer", ["Local", "Import", "Inconnu", "Atelier cellule"] select (([_seed, "tm"] call comspec_sse_fnc_hash) mod 4)],
    ["origin", ["cache", "contrebande", "prise de guerre", "inconnu"] select (([_seed, "to"] call comspec_sse_fnc_hash) mod 4)],
    ["compatibility", "TECHINT — corrélation série / lot"],
    ["tags", ["TECH", "WEAPONS"]]
]
