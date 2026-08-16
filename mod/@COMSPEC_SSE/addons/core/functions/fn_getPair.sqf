/*
    Lecture sûre d'une paire clé/valeur SSE.
    [_pairs, _key, _default] call comspec_sse_fnc_getPair
    Ne déclenche jamais BIS_fnc_getFromPairs sur un non-ARRAY (évite type ANY).
*/
params [
    ["_pairs", nil],
    ["_key", "", [""]],
    "_default"
];

if (isNil "_pairs" || {!(_pairs isEqualType [])}) exitWith {
    if (isNil "_default") then { nil } else { _default }
};

[_pairs, _key, if (isNil "_default") then { nil } else { _default }] call BIS_fnc_getFromPairs
