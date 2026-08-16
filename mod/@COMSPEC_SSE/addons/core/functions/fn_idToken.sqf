/*
    Jeton numérique stable pour UID / références (évite la notation scientifique Arma).
    [_seed, _salt, _digits] call comspec_sse_fnc_idToken → "084219337"
*/
params [
    ["_seed", 0, [0]],
    ["_salt", 0, [0, ""]],
    ["_digits", 9, [0]]
];

_digits = (_digits max 4) min 12;
private _mod = 1;
for "_i" from 1 to _digits do {
    _mod = _mod * 10;
};

private _n = ([_seed, _salt] call comspec_sse_fnc_hash) mod _mod;
if (_n < 0) then { _n = _n + _mod; };

// toFixed 0 : jamais de forme 1.11e+09 (contrairement à format "%1" / str sur grands nombres).
private _s = _n toFixed 0;
while { count _s < _digits } do {
    _s = "0" + _s;
};
_s
