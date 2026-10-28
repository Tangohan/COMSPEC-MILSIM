/*
    Encode une valeur SQF en JSON (chaînes, nombres, booléens, tableaux, HashMap).
    Récursif : nécessaire pour getUnitLoadout (tableaux imbriqués).
    Params: [_value, _depth]
*/
params [
    ["_value", nil],
    ["_depth", 0, [0]]
];

if (_depth > 12) exitWith { "null" };
if (isNil "_value") exitWith { "null" };

private _dq = toString [34];
private _bs = toString [92];

private _fncEsc = {
    params ["_s"];
    if (!(_s isEqualType "")) then { _s = str _s; };
    // Caractère par caractère : SQF n'a pas d'échappement (« \\n » = 3 caractères), et
    // splitString écarte les segments vides (guillemets en tête / doublés perdus).
    private _out = [];
    {
        switch (true) do {
            case (_x == 34): { _out append [92, 34] };
            case (_x == 92): { _out append [92, 92] };
            case (_x == 10): { _out append [92, 110] };
            case (_x == 13): { _out append [92, 114] };
            case (_x == 9): { _out append [92, 116] };
            case (_x < 32): { _out pushBack 32 };
            default { _out pushBack _x };
        };
    } forEach (toArray _s);
    _dq + (toString _out) + _dq
};

switch (typeName _value) do {
    case "STRING": {
        [_value] call _fncEsc
    };
    case "SCALAR": {
        if (!finite _value) exitWith { "null" };
        if (_value == (floor _value) && {abs _value < 1e12}) then {
            str (round _value)
        } else {
            _value toFixed 4
        };
    };
    case "BOOL": {
        if (_value) then { "true" } else { "false" }
    };
    case "ARRAY": {
        private _els = _value apply {
            [_x, _depth + 1] call comspec_overwatch_connect_fnc_jsonValue
        };
        format ["[%1]", _els joinString ","]
    };
    case "HASHMAP": {
        private _pairs = [];
        {
            private _k = _x;
            if (!(_k isEqualType "")) then { _k = str _k; };
            private _v = _value get _x;
            _pairs pushBack format [
                "%1:%2",
                [_k] call _fncEsc,
                [_v, _depth + 1] call comspec_overwatch_connect_fnc_jsonValue
            ];
        } forEach (keys _value);
        format ["{%1}", _pairs joinString ","]
    };
    default { "null" };
};
