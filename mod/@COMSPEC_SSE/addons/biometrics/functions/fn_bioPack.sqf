/*
    HashMap imbriquée -> tableaux transportables (CBA / remoteExec).
    [_value] call comspec_sse_fnc_bioPack ; inverse : comspec_sse_fnc_bioUnpack
*/
params ["_v"];
if (isNil "_v") exitWith { nil };
if (_v isEqualType createHashMap) exitWith {
    ["#M", (keys _v) apply { [_x, [_v get _x] call comspec_sse_fnc_bioPack] }]
};
if (_v isEqualType []) exitWith { _v apply { [_x] call comspec_sse_fnc_bioPack } };
if (_v isEqualType {}) exitWith { "" };
_v
