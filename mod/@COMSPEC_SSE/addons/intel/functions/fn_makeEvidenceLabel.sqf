params [
    ["_entity", objNull, [objNull]]
];
private _data = [_entity] call comspec_sse_fnc_getData;
private _uid = if (isNil "_data") then { "SSE-XXXX" } else { [_data, "uid", "SSE-XXXX"] call BIS_fnc_getFromPairs };
private _type = if (isNil "_data") then { "OBJ" } else { [_data, "type", "OBJ"] call BIS_fnc_getFromPairs };
private _mission = missionNamespace getVariable ["comspec_sse_missionId", "OP"];
private _room = "SITE";
format ["%1-%2-%3-%4", _mission, _room, _type, _uid]
