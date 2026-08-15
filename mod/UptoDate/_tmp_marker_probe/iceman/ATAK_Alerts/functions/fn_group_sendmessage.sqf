#include "..\script_component.hpp"

if (!hasInterface) exitWith {false};

private _sender = missionNamespace getVariable ["cTab_player", player];
if (isNull _sender) then {
    _sender = player;
};

if !([_sender, ctab_core_leaderDevices] call cTab_fnc_checkGear) exitWith {
    ["GROUP", "ATAK device required to send message.", 4] call cTab_fnc_addNotification;
    false
};

private _group = uiNamespace getVariable ["Iceman_ATAK_Group_group", controlNull];
private _edit = controlNull;
if (!isNull _group) then {
    {
        if ((ctrlIDC _x) == 9904) exitWith {
            _edit = _x;
        };
    } forEach allControls _group;
};

private _text = if (isNull _edit) then {""} else {ctrlText _edit};
if (_text == "") exitWith {
    ["GROUP", "Enter a group message first.", 3] call cTab_fnc_addNotification;
    false
};

private _time = call cTab_fnc_currentTime;
private _pos = getPosASL _sender;
private _groupId = groupId group _sender;
private _recipients = units group _sender select {
    isPlayer _x && {[_x, ctab_core_leaderDevices] call cTab_fnc_checkGear}
};

["Iceman_ATAK_GroupMessage", [_sender, _groupId, _text, _pos, _time]] call CBA_fnc_globalEvent;
if (!isNull _edit) then {
    _edit ctrlSetText "";
};

["GROUP", format ["Message sent to %1 group ATAK user(s).", count _recipients], 4] call cTab_fnc_addNotification;
playSound "cTab_mailSent";
true
