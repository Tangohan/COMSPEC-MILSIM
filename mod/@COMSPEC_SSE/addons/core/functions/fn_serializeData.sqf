/*
    Sérialise les données SSE en structure JSON-friendly (ARRAY/STRING/NUMBER).
    [_data] call comspec_sse_fnc_serializeData
*/
params [
    ["_data", [], [[]]]
];

private _fncConvert = {
    params ["_v"];
    if (_v isEqualType createHashMap) exitWith {
        private _out = [];
        {
            _out pushBack [_x, [_y] call _fncConvert];
        } forEach _v;
        _out
    };
    if (_v isEqualType []) exitWith {
        _v apply { [_x] call _fncConvert }
    };
    _v
};

[_data] call _fncConvert
