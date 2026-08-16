/*
    Écriture sûre d'une paire dans le modèle SSE (ARRAY de paires).
    [_pairs, _key, _value] call comspec_sse_fnc_setPair -> ARRAY
*/
params [
    ["_pairs", [], [[]]],
    ["_key", "", [""]],
    "_value"
];

if !(_pairs isEqualType []) then { _pairs = []; };

private _result = [_pairs, [_key, _value]] call BIS_fnc_setToPairs;
if (isNil "_result" || {!(_result isEqualType [])}) then {
    // Repli manuel si setToPairs échoue (valeurs HashMap / ANY).
    private _found = false;
    {
        if (_x isEqualType [] && {count _x >= 2} && {(_x select 0) isEqualTo _key}) then {
            _pairs set [_forEachIndex, [_key, _value]];
            _found = true;
        };
    } forEach _pairs;
    if (!_found) then { _pairs pushBack [_key, _value]; };
    _pairs
} else {
    _result
}
