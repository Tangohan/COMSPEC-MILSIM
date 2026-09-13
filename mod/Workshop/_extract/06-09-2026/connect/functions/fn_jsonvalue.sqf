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
    _s = (_s splitString _bs) joinString (_bs + _bs);
    _s = (_s splitString _dq) joinString (_bs + _dq);
    _s = (_s splitString toString [10]) joinString "\\n";
    _s = (_s splitString toString [13]) joinString "";
    _dq + _s + _dq
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
