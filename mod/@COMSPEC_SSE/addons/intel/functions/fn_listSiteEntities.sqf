params [
    ["_center", objNull, [objNull, []]],
    ["_radius", 50, [0]]
];
private _pos = if (_center isEqualType []) then { _center } else { getPosATL _center };
private _out = [];
{
    if (!isNull _x && {!isNil {[_x] call comspec_sse_fnc_getData}}) then {
        private _data = [_x] call comspec_sse_fnc_getData;
        _out pushBack (createHashMapFromArray [
            ["entity", _x],
            ["uid", [_data, "uid", ""] call BIS_fnc_getFromPairs],
            ["type", [_data, "type", ""] call BIS_fnc_getFromPairs],
            ["level", [_x] call comspec_sse_fnc_getExploitationLevel],
            ["state", [_data, "state", ""] call BIS_fnc_getFromPairs],
            ["label", [_x] call comspec_sse_fnc_makeEvidenceLabel]
        ]);
    };
} forEach (nearestObjects [_pos, [], _radius]);
_out
