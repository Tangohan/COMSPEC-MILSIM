/*
    Désérialise une structure de paires vers le modèle runtime.
    [_serialized] call comspec_sse_fnc_deserializeData
*/
params [
    ["_serialized", [], [[]]]
];

private _fncRestore = {
    params ["_v"];
    if (!(_v isEqualType [])) exitWith { _v };
    // Paire unique ["k", val] vs liste de paires
    if ((count _v) == 2 && {(_v select 0) isEqualType ""}) exitWith {
        // could be a pair OR a 2-element list — treat as value list if second is not nested oddly
        _v
    };
    // Si tous les éléments sont des paires [string, *], reconstruire un HashMap
    if (count _v > 0 && {(_v select 0) isEqualType []} && {count (_v select 0) == 2} && {((_v select 0) select 0) isEqualType ""}) then {
        private _hm = createHashMap;
        {
            _x params ["_k", "_val"];
            _hm set [_k, [_val] call _fncRestore];
        } forEach _v;
        _hm
    } else {
        _v apply { [_x] call _fncRestore }
    };
};

[_serialized] call _fncRestore
