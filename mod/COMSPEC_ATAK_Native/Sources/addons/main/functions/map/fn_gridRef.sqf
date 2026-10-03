/*
    Référence de grille à 6, 8 ou 10 chiffres (outil carte « Précision grille »).
    La carte Arma donne 6 chiffres (cases de 100 m) ; on retrouve la distance au bord de la case
    par dichotomie sur mapGridPosition, ce qui marche quel que soit le décalage de grille de la carte.
    Params : [position, chiffres (profil COMSPEC_ATAK_GridDigits, 6 par défaut)]
*/
params ["_pos", ["_digits", profileNamespace getVariable ["COMSPEC_ATAK_GridDigits", 6]]];
// Accepte aussi un objet (pièce, unité) : sinon « _pos params » plantait et la page entière ne se dessinait plus.
if (_pos isEqualType objNull) then { _pos = getPosASL _pos; };
if !(_pos isEqualType [] && {(count _pos) >= 2}) exitWith { "" };
private _g = mapGridPosition _pos;
if (_digits <= 6 || {(count _g) isNotEqualTo 6}) exitWith { _g };
_pos params ["_x", "_y"];
private _e = _g select [0, 3];
private _n = _g select [3, 3];
// La northing augmente-t-elle vers le nord ? (certaines cartes comptent vers le bas)
private _north = (parseNumber ((mapGridPosition [_x, _y + 100]) select [3, 3])) > (parseNumber _n);
// Distance (0..99 m) depuis le bord « bas » de la case, le long d'un axe.
private _offset = {
    params ["_axis", "_dir", "_ref"];
    private _lo = 0;
    private _hi = 100;
    while { (_hi - _lo) > 1 } do {
        private _mid = floor ((_lo + _hi) / 2);
        private _p = [[_x - _mid, _y], [_x, _y - _dir * _mid]] select _axis;
        private _part = (mapGridPosition _p) select [[0, 3] select _axis, 3];
        if (_part isEqualTo _ref) then { _lo = _mid; } else { _hi = _mid; };
    };
    _lo
};
private _oe = [0, 1, _e] call _offset;
private _on = [1, [-1, 1] select _north, _n] call _offset;
if (_digits < 10) exitWith { format ["%1%2 %3%4", _e, floor (_oe / 10), _n, floor (_on / 10)] };
format ["%1%2 %3%4", _e, [_oe, 2] call CBA_fnc_formatNumber, _n, [_on, 2] call CBA_fnc_formatNumber]
