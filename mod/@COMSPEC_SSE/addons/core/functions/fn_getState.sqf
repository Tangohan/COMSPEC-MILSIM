/*
    [_entity] call comspec_sse_fnc_getState
*/
params [
    ["_entity", objNull, [objNull]]
];

private _data = [_entity] call comspec_sse_fnc_getData;
if (isNil "_data") exitWith { "NONE" };

[_data, "state", "UNTOUCHED"] call BIS_fnc_getFromPairs
