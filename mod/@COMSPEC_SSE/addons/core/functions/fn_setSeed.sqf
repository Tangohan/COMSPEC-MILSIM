/*
    [_entity, _seed, _public] call comspec_sse_fnc_setSeed
*/
params [
    ["_entity", objNull, [objNull]],
    ["_seed", 0, [0]],
    ["_public", true, [true]]
];

private _data = [_entity] call comspec_sse_fnc_getData;
if (isNil "_data") then {
    _data = ["PERSON", "SCRIPT", "RANDOM", "STANDARD", _seed] call comspec_sse_fnc_createDataModel;
};

_data = [_data, ["seed", round _seed]] call BIS_fnc_setToPairs;
[_entity, _data, _public] call comspec_sse_fnc_setData;
true
