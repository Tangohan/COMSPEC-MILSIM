params [
    ["_entity", objNull, [objNull]]
];
private _data = [_entity] call comspec_sse_fnc_getData;
private _uid = [_data, "uid", "SSE-XXXX"] call comspec_sse_fnc_getPair;
private _type = [_data, "type", "OBJ"] call comspec_sse_fnc_getPair;
if (_uid isEqualType []) then { _uid = "SSE-XXXX"; };
if (_type isEqualType []) then { _type = "OBJ"; };
private _mission = missionNamespace getVariable ["comspec_sse_missionId", "OP"];
private _room = "SITE";
format ["%1-%2-%3-%4", _mission, _room, _type, _uid]
