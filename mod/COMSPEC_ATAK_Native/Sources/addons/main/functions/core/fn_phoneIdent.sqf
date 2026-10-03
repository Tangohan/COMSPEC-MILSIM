/*
    Identité du téléphone d'une unité (roleplay) : [numéro, IMEI, adresse MAC].
    Calculée de façon déterministe sur chaque machine (aucune donnée réseau) à partir de l'UID du joueur, ou du netId
    pour une IA. Le numéro suit la carte SIM et ne change jamais ; IMEI et MAC changent avec l'appareil
    (« Changer de téléphone » incrémente COMSPEC_ATAK_PhoneGen).
    Surcharge possible par le créateur de mission : this setVariable ["COMSPEC_ATAK_Ident", ["06 12 34 56 78", "", ""], true]
    (un champ vide garde la valeur calculée).
    Params : [unité]   Avec "norm" en 2e paramètre : renvoie les trois valeurs normalisées (chiffres et lettres seuls, majuscules).
*/
params [["_u", player], ["_mode", ""]];
private _key = getPlayerUID _u;
if (_key in ["", "_SP_PLAYER_", "_SP_AI_"]) then { _key = netId _u; };
if (_key in ["", "0:0"]) then { _key = str _u; };
// Graine 16 bits (calcul exact en flottants SQF), puis générateur congruentiel pour les chiffres.
private _seed = {
    params ["_s"];
    private _h = 7;
    { _h = (_h * 31 + _x) mod 65521; } forEach (toArray _s);
    _h
};
private _digits = {
    params ["_s", "_n", "_base"];
    private _h = [_s] call _seed;
    private _out = [];
    for "_i" from 1 to _n do {
        _h = (_h * 75 + 74) mod 65537;
        _out pushBack (floor ((_h / 65537) * _base));
    };
    _out
};
private _gen = _u getVariable ["COMSPEC_ATAK_PhoneGen", 0];
private _n = [_key + "|sim", 8, 10] call _digits;
private _num = format ["0%1 %2%3 %4%5 %6%7 %8%9", [6, 7] select ((_n select 0) >= 7), _n select 1, _n select 2, _n select 3, _n select 4, _n select 5, _n select 6, _n select 7, (_n select 0) mod 10];
// IMEI : TAC 35 + 12 chiffres + clé de Luhn.
private _i = [3, 5] + ([format ["%1|imei|%2", _key, _gen], 12, 10] call _digits);
private _sum = 0;
{
    private _d = _x;
    if ((_forEachIndex mod 2) isEqualTo 1) then { _d = _d * 2; if (_d > 9) then { _d = _d - 9; }; };
    _sum = _sum + _d;
} forEach _i;
_i pushBack ((10 - (_sum mod 10)) mod 10);
private _is = (_i apply { str _x }) joinString "";
private _imei = format ["%1-%2-%3-%4", _is select [0, 2], _is select [2, 6], _is select [8, 6], _is select [14, 1]];
private _hex = "0123456789ABCDEF";
private _m = [format ["%1|mac|%2", _key, _gen], 12, 16] call _digits;
// Premier octet pair : adresse unicast.
_m set [1, 2 * floor ((_m select 1) / 2)];
private _mac = ([0, 2, 4, 6, 8, 10] apply { format ["%1%2", _hex select [_m select _x, 1], _hex select [_m select (_x + 1), 1]] }) joinString ":";
private _ret = [_num, _imei, _mac];
private _o = _u getVariable ["COMSPEC_ATAK_Ident", []];
if (_o isEqualType [] && {(count _o) > 0}) then {
    { if (_x isEqualType "" && {_x isNotEqualTo ""}) then { _ret set [_forEachIndex, _x]; }; } forEach (_o select [0, 3]);
};
if (_mode isEqualTo "norm") exitWith {
    _ret apply { private _a = toArray toUpper _x; toString (_a select { (_x >= 48 && _x <= 57) || {_x >= 65 && _x <= 90} }) }
};
_ret
