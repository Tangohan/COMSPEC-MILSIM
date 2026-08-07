/*
    Sélection déterministe dans une liste.
    [_seed, _salt, _list] call comspec_sse_fnc_pickFromSeed
*/
params [
    ["_seed", 0, [0]],
    ["_salt", "", [""]],
    ["_list", [], [[]]]
];

if (count _list == 0) exitWith { nil };

private _h = [_seed, _salt] call comspec_sse_fnc_hash;
private _idx = _h mod (count _list);
_list select _idx
