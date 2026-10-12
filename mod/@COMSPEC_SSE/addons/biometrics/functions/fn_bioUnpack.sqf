/*
    Inverse de comspec_sse_fnc_bioPack.
*/
params ["_v"];
if (isNil "_v") exitWith { nil };
if (_v isEqualType [] && {(count _v) == 2} && {(_v select 0) isEqualTo "#M"} && {(_v select 1) isEqualType []}) exitWith {
    private _m = createHashMap;
    {
        if ((_x isEqualType []) && {(count _x) == 2}) then {
            _m set [_x select 0, [_x select 1] call comspec_sse_fnc_bioUnpack];
        };
    } forEach (_v select 1);
    _m
};
if (_v isEqualType []) exitWith { _v apply { [_x] call comspec_sse_fnc_bioUnpack } };
_v
