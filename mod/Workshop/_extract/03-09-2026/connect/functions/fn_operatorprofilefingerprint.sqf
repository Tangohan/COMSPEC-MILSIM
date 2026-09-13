/*
    Empreinte compacte pour détecter un changement significatif (pas le rythme cardiaque).
    Params: [_identity, _face, _medical, _equipment, _versions]
    Retour: STRING
*/
params [
    ["_identity", createHashMap, [createHashMap]],
    ["_face", createHashMap, [createHashMap]],
    ["_medical", createHashMap, [createHashMap]],
    ["_equipment", createHashMap, [createHashMap]],
    ["_versions", createHashMap, [createHashMap]]
];

private _fncW = {
    params ["_slot"];
    if (!(_slot isEqualType createHashMap)) exitWith { "" };
    _slot getOrDefault ["class", ""]
};

format [
    "%1|%2|%3|%4|%5|%6|%7|%8|%9|%10|%11|%12|%13|%14|%15|%16|%17|%18",
    _identity getOrDefault ["steam_uid", ""],
    _identity getOrDefault ["callsign", ""],
    _face getOrDefault ["face_class", ""],
    _medical getOrDefault ["blood_type", ""],
    _identity getOrDefault ["sex_detected", ""],
    _identity getOrDefault ["display_name", ""],
    _equipment getOrDefault ["uniform_class", ""],
    _equipment getOrDefault ["vest_class", ""],
    _equipment getOrDefault ["backpack_class", ""],
    _equipment getOrDefault ["helmet_class", ""],
    [_equipment getOrDefault ["primary", createHashMap]] call _fncW,
    _equipment getOrDefault ["nvgs_class", ""],
    _versions getOrDefault ["overwatch", ""],
    _versions getOrDefault ["atak", ""],
    _versions getOrDefault ["ace", ""],
    _identity getOrDefault ["group_name", ""],
    _identity getOrDefault ["role", ""],
    _identity getOrDefault ["rank_game", ""]
]
