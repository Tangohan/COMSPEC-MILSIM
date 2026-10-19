/*
    Hash déterministe (deux voies djb2-like) pour seeds et picks.
    [_seed, _salt] call comspec_sse_fnc_hash -> NUMBER entier dans [0, 16777215]

    SQF calcule en simple précision (24 bits de mantisse) : tous les calculs
    intermédiaires restent sous 2^24 pour être exacts et identiques sur toutes
    les machines (l'ancien mod 2147483647 perdait les derniers caractères).
*/
params [
    ["_seed", 0, [0]],
    ["_salt", 0, [0, ""]]
];

// Voie 1 : ×31 mod 524287 ; voie 2 : ×33 mod 508361 (caractères ramenés sous 1024).
private _p1 = 524287;
private _p2 = 508361;
private _s = abs (round _seed);
private _a = _s mod _p1;
private _b = _s mod _p2;

if (_salt isEqualType "") then {
    {
        private _c = _x mod 1024;
        _a = ((_a * 31) + _c) mod _p1;
        _b = ((_b * 33) + _c) mod _p2;
    } forEach (toArray _salt);
} else {
    private _n = abs (round _salt);
    _a = ((_a * 31) + (_n mod _p1)) mod _p1;
    _b = ((_b * 33) + (_n mod _p2)) mod _p2;
};

// 524287 × 32 + 31 = 16777215 : résultat exact sur 24 bits.
(_a * 32) + (_b mod 32)
