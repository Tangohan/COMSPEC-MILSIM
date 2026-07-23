// Display fire solution to player (notification / hint). Param: [jsonString or parsed hash]
params [["_data", "", ["",""]]];
if (_data isEqualTo "") exitWith {};

private _str = if (_data isEqualType "") then { _data } else { str _data };
["COMSPEC_Info", ["Solution de tir reçue — voir détail dans le journal."]] call comspec_overwatch_connect_fnc_showNotification;
if ([] call comspec_overwatch_connect_fnc_shouldShowScreenNotification) then {
    hintSilent ("Fire solution: " + (_str select [0, 80]));
};
