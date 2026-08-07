/*
    [_entity] call comspec_sse_fnc_getSeed
*/
params [
    ["_entity", objNull, [objNull]]
];

private _data = [_entity] call comspec_sse_fnc_getData;
if (isNil "_data") exitWith { -1 };

[_data, "seed", -1] call BIS_fnc_getFromPairs
