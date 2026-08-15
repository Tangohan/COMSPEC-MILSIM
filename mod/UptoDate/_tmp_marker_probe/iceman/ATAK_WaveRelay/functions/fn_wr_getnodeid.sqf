params [["_unit", player]];

if (isNull _unit) exitWith {"MPU5-UNKNOWN"};

private _existing = _unit getVariable ["Iceman_WR_nodeId", ""];
if (_existing != "") exitWith {_existing};

private _name = name _unit;
private _uid = getPlayerUID _unit;
private _suffix = if (_uid == "") then {
    str (floor random 9999)
} else {
    if ((count _uid) > 4) then {_uid select [(count _uid) - 4, 4]} else {_uid}
};

private _nodeId = format ["MPU5-%1-%2", _name, _suffix];
_unit setVariable ["Iceman_WR_nodeId", _nodeId, true];
_nodeId
