/*
    QR code d'une adresse de signalement (ICD-SSE-ALERTES-001 § 4), calculé sur le téléphone : le canal du jeu ne transporte aucune image
    et le site ne donne que l'adresse. Sortie : les rangées de « 0 » / « 1 » attendues par la ligne « qr » de fn_formRender (la même que la
    page « Sur mon téléphone »), à scanner avec un vrai téléphone.
    Norme ISO/IEC 18004, mode octet, correction L, versions 1 à 5 (21 à 37 modules, 106 caractères au plus), masque 0. Une adresse plus
    longue ou hors ASCII imprimable rend [] : l'écran garde l'adresse en clair.
    Calcul pur (aucun contrôle, aucune variable globale) : le résultat est gardé par l'appelant (fn_alertesAction « qr »).
    Params : [texte]. Retour : [rangée…] ou [].
*/
params [["_text", "", [""]]];
private _b = toArray _text;
private _n = count _b;
if (_n isEqualTo 0 || {(_b findIf { _x < 32 || {_x > 126} }) >= 0}) exitWith { [] };
// Mots de données et de correction par version (niveau L, un seul bloc jusqu'à la version 5).
private _DATA = [19, 34, 55, 80, 108];
private _EC = [7, 10, 15, 20, 26];
// Information de format, niveau L et masque 0 (bit 14 d'abord).
private _FMT = toArray "111011111000100";
private _v = _DATA findIf { (_n + 2) <= _x };
if (_v < 0) exitWith { [] };
private _nd = _DATA select _v;
private _ne = _EC select _v;
private _size = 21 + 4 * _v;

// Ou exclusif de deux octets, par demi-octets (SQF n'a pas d'opérateur de bits).
private _nib = [];
for "_i" from 0 to 15 do {
    private _line = [];
    for "_j" from 0 to 15 do {
        private _r = 0;
        { if (((floor (_i / _x)) mod 2) isNotEqualTo ((floor (_j / _x)) mod 2)) then { _r = _r + _x; }; } forEach [1, 2, 4, 8];
        _line pushBack _r;
    };
    _nib pushBack _line;
};
private _xor = {
    params ["_p", "_q"];
    (((_nib select (floor (_p / 16))) select (floor (_q / 16))) * 16) + ((_nib select (_p mod 16)) select (_q mod 16))
};

// Train de bits : mode octet (0100), longueur sur 8 bits, octets, fin (0000). Tout tombe sur des demi-octets.
private _cw = [64 + (floor (_n / 16))];
private _prev = _n mod 16;
{
    _cw pushBack (_prev * 16 + (floor (_x / 16)));
    _prev = _x mod 16;
} forEach _b;
_cw pushBack (_prev * 16);
private _padI = 0;
while { (count _cw) < _nd } do {
    _cw pushBack ([236, 17] select (_padI mod 2));
    _padI = _padI + 1;
};

// Corps de Galois GF(256), polynôme 285 : puissances et logarithmes.
private _exp = [];
_exp resize [512, 0];
private _log = [];
_log resize [256, 0];
private _g = 1;
for "_i" from 0 to 254 do {
    _exp set [_i, _g];
    _log set [_g, _i];
    _g = _g * 2;
    if (_g > 255) then { _g = [_g - 256, 29] call _xor; };
};
for "_i" from 255 to 511 do { _exp set [_i, _exp select (_i - 255)]; };

// Polynôme générateur de Reed-Solomon, puis reste de la division des mots de données.
private _gen = [1];
for "_i" from 0 to (_ne - 1) do {
    private _next = [];
    _next resize [(count _gen) + 1, 0];
    {
        _next set [_forEachIndex, [_next select _forEachIndex, _x] call _xor];
        if (_x > 0) then { _next set [_forEachIndex + 1, [_next select (_forEachIndex + 1), _exp select ((_log select _x) + _i)] call _xor]; };
    } forEach _gen;
    _gen = _next;
};
private _rem = [];
_rem resize [_ne, 0];
{
    private _f = [_x, _rem select 0] call _xor;
    _rem deleteAt 0;
    _rem pushBack 0;
    if (_f > 0) then {
        private _lf = _log select _f;
        for "_j" from 0 to (_ne - 1) do {
            _rem set [_j, [_rem select _j, _exp select ((_log select (_gen select (_j + 1))) + _lf)] call _xor];
        };
    };
} forEach _cw;
private _all = _cw + _rem;

// Matrice et modules de fonction (repères, synchronisation, alignement, format).
private _m = [];
private _fn = [];
for "_i" from 1 to _size do {
    private _r0 = [];
    _r0 resize [_size, 0];
    _m pushBack _r0;
    private _f0 = [];
    _f0 resize [_size, false];
    _fn pushBack _f0;
};
private _setF = {
    params ["_r", "_c", "_val"];
    if (_r >= 0 && {_r < _size} && {_c >= 0} && {_c < _size}) then {
        (_m select _r) set [_c, _val];
        (_fn select _r) set [_c, true];
    };
};
{
    _x params ["_r0", "_c0"];
    for "_dr" from -1 to 7 do {
        for "_dc" from -1 to 7 do {
            private _in = _dr >= 0 && {_dr <= 6} && {_dc >= 0} && {_dc <= 6};
            private _dark = _in && {_dr in [0, 6] || {_dc in [0, 6]} || {_dr >= 2 && {_dr <= 4} && {_dc >= 2} && {_dc <= 4}}};
            [_r0 + _dr, _c0 + _dc, [0, 1] select _dark] call _setF;
        };
    };
} forEach [[0, 0], [0, _size - 7], [_size - 7, 0]];
for "_i" from 8 to (_size - 9) do {
    [6, _i, 1 - (_i mod 2)] call _setF;
    [_i, 6, 1 - (_i mod 2)] call _setF;
};
if (_v > 0) then {
    private _a = _size - 7;
    for "_dr" from -2 to 2 do {
        for "_dc" from -2 to 2 do {
            [_a + _dr, _a + _dc, [1, 0] select (((abs _dr) max (abs _dc)) isEqualTo 1)] call _setF;
        };
    };
};
private _bit = { params ["_i"]; [0, 1] select ((_FMT select (14 - _i)) isEqualTo 49) };
for "_i" from 0 to 5 do { [_i, 8, [_i] call _bit] call _setF; };
[7, 8, [6] call _bit] call _setF;
[8, 8, [7] call _bit] call _setF;
[8, 7, [8] call _bit] call _setF;
for "_i" from 9 to 14 do { [8, 14 - _i, [_i] call _bit] call _setF; };
for "_i" from 0 to 7 do { [8, _size - 1 - _i, [_i] call _bit] call _setF; };
for "_i" from 8 to 14 do { [_size - 15 + _i, 8, [_i] call _bit] call _setF; };
[_size - 8, 8, 1] call _setF;

// Mots posés en zigzag par paires de colonnes depuis le coin bas droit ; masque 0 : (rangée + colonne) paire.
private _k = 0;
private _total = (count _all) * 8;
private _right = _size - 1;
while { _right >= 1 } do {
    if (_right isEqualTo 6) then { _right = 5; };
    private _up = ((floor ((_right + 1) / 2)) mod 2) isEqualTo 0;
    for "_vert" from 0 to (_size - 1) do {
        private _r = [_vert, _size - 1 - _vert] select _up;
        for "_j" from 0 to 1 do {
            private _c = _right - _j;
            if !((_fn select _r) select _c) then {
                private _val = 0;
                if (_k < _total) then {
                    _val = (floor ((_all select (floor (_k / 8))) / (2 ^ (7 - (_k mod 8))))) mod 2;
                    _k = _k + 1;
                };
                if (((_r + _c) mod 2) isEqualTo 0) then { _val = 1 - _val; };
                (_m select _r) set [_c, _val];
            };
        };
    };
    _right = _right - 2;
};
_m apply { (_x apply { str _x }) joinString "" }
