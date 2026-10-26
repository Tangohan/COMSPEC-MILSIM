/*
    Lit une grille tapée à la main, comme on la dicte : avec ou sans espaces, tirets, points ou virgules, 4 à 10 chiffres,
    préfixe de zone MGRS ignoré (« 31U DQ 012 345 »). Deux blocs de longueurs différentes (« 012 3456 ») : le plus long
    est ramené à la précision du plus court.
    Params : [texte]. Renvoie [] si illisible ou hors de la carte, sinon [centre de la case [x, y, 0], taille de la case (m),
    grille normalisée « 012 345 »].
*/
params [["_txt", ""]];
if !(_txt isEqualType "") exitWith { [] };
// Préfixe MGRS (zone et carré de 100 km) : on ne garde que ce qui suit la dernière lettre.
private _t = toUpper (trim _txt);
private _letters = _t regexFind ["[A-Z]", 0];
if ((count _letters) > 0) then {
    private _last = (_letters select ((count _letters) - 1)) select 0;
    _t = _t select [(_last select 1) + 1];
};
private _groups = (_t regexFind ["[0-9]+", 0]) apply { (_x select 0) select 0 };
private _g = switch (count _groups) do {
    case 0: { "" };
    case 2: {
        _groups params ["_e", "_n"];
        private _k = ((count _e) min (count _n)) min 5;
        (_e select [0, _k]) + (_n select [0, _k])
    };
    default { _groups joinString "" };
};
private _n = count _g;
if (_n < 4 || {_n > 10} || {(_n mod 2) isEqualTo 1}) exitWith { [] };
([_g] call BIS_fnc_gridToPos) params [["_p", []], ["_sz", [0, 0]]];
if !(_p isEqualType [] && {(count _p) >= 2}) exitWith { [] };
private _c = [(_p select 0) + (_sz select 0) / 2, (_p select 1) + (_sz select 1) / 2, 0];
if ((_c select 0) < 0 || {(_c select 1) < 0} || {(_c select 0) > worldSize} || {(_c select 1) > worldSize}) exitWith { [] };
[_c, (_sz select 0) max 1, format ["%1 %2", _g select [0, _n / 2], _g select [_n / 2]]]
