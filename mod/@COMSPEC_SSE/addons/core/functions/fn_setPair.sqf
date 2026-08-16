/*
    Écriture sûre d'une paire dans le modèle SSE (ARRAY de paires).
    [_pairs, _key, _value] call comspec_sse_fnc_setPair -> ARRAY

    N'utilise pas BIS_fnc_setToPairs avec [key,value] en 2e argument :
    Inc_setToPairs attend une STRING en index 1 (sinon « type ARRAY, expected STRING »).
*/
params [
    ["_pairs", [], [[]]],
    ["_key", "", [""]],
    "_value"
];

if (isNil "_pairs" || {!(_pairs isEqualType [])}) then { _pairs = []; };
if (_key isEqualTo "" || {!(_key isEqualType "")}) exitWith { _pairs };

// Copie superficielle pour ne pas laisser un appelant avec un état partiel si erreur.
private _out = +_pairs;

private _found = false;
{
    if (_x isEqualType [] && {count _x >= 2} && {(_x select 0) isEqualTo _key}) then {
        _out set [_forEachIndex, [_key, _value]];
        _found = true;
    };
} forEach _out;

if (!_found) then {
    _out pushBack [_key, _value];
};

_out
