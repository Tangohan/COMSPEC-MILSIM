// Display fire solution to player (notification / hint). Param: [jsonString or parsed hash]
params [["_data", "", ["",""]]];
if (_data isEqualTo "") exitWith {};

private _str = if (_data isEqualType "") then { _data } else { str _data };
["COMSPEC Fire Support", "Solution de tir reçue — voir détail dans le journal.", 10] call BIS_fnc_showNotification;
if (isNil "BIS_fnc_showNotification") then {
    hint ("Fire solution: " + (_str select [0, 80]));
};
