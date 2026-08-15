#include "..\script_component.hpp"

params ["_sender", "_senderGroupId", "_text", "_pos", ["_time", ""]];

if (!hasInterface) exitWith {};

private _receiver = missionNamespace getVariable ["cTab_player", player];
if (isNull _receiver) then {
    _receiver = player;
};
if !([_receiver, ctab_core_leaderDevices] call cTab_fnc_checkGear) exitWith {};
if ((groupId group _receiver) != _senderGroupId) exitWith {};

if (_time == "") then {
    _time = call cTab_fnc_currentTime;
};

private _senderName = if (isNull _sender) then {"Unknown"} else {name _sender};
private _grid = mapGridPosition _pos;
private _isMine = !isNull _sender && {_sender isEqualTo _receiver};
private _messages = +(missionNamespace getVariable ["Iceman_ATAK_Group_messages", []]);
_messages pushBack [_time, _senderName, _senderGroupId, _grid, _text, _pos, _isMine];
while {(count _messages) > 50} do {
    _messages deleteAt 0;
};
Iceman_ATAK_Group_messages = _messages;
Iceman_ATAK_Group_selected = (count _messages) - 1;

call Iceman_fnc_group_updatePanel;

if (isNull _sender || {!(_sender isEqualTo _receiver)}) then {
    ["GROUP", format ["Message from %1", _senderName], 6] call cTab_fnc_addNotification;
    playSound "cTab_phoneVibrate";
};

true
