/*
    comspec_fb_fnc_chanValue : valeur à l'instant _now d'un canal d'effet [pic, t0, durée, maintien, courbure, avant].
    Avant t0 : valeur « avant » (effet retardé, ex. ouïe étouffée après l'attaque du bang). Puis plateau au pic pendant
    la fraction « maintien » de la durée, puis récupération (1 - x)^courbure : rapide au début, lente à la fin.
    [_canal, _now] call comspec_fb_fnc_chanValue -> 0..1
*/
params ["_c", "_now"];
_c params [["_peak", 0], ["_t0", 0], ["_dur", 0], ["_hold", 0], ["_exp", 1], ["_pre", 0]];
if (_dur <= 0) exitWith { 0 };
private _k = (_now - _t0) / _dur;
if (_k < 0) exitWith { _pre };
if (_k >= 1) exitWith { 0 };
if (_k < _hold) exitWith { _peak };
_peak * ((1 - (_k - _hold) / (1 - _hold)) ^ _exp)
