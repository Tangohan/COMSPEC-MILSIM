/*
    Chiffre de contrôle ICAO 9303 (même algorithme que SseMrzService côté site).
    Caractères : 0-9 -> 0-9, A-Z -> 10-35, « < » (et tout le reste) -> 0 ; poids 7, 3, 1 répétés ; somme modulo 10.
    [_text] call comspec_sse_fnc_idMrzCheck -> Number 0..9
    Spécimen ICAO (passeport UTO) : L898902C3 -> 6, 740812 -> 2, 120415 -> 9, ZE184226B -> 1, composite -> 0
    (tools/test_id_mrz.sqf en jeu, tools/check_id_mrz.py hors jeu).
*/
params [["_s", "", [""]]];
private _w = [7, 3, 1];
private _sum = 0;
{
    private _v = 0;
    if (_x >= 48 && {_x <= 57}) then { _v = _x - 48; } else { if (_x >= 65 && {_x <= 90}) then { _v = _x - 55; }; };
    _sum = _sum + _v * (_w select (_forEachIndex mod 3));
} forEach (toArray (toUpper _s));
_sum mod 10
