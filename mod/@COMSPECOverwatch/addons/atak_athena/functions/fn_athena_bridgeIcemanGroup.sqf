/*
    Message de groupe Iceman → messagerie Athena.
    Payload : [_sender, _groupId, _text, _pos, _time]
*/
params ["_sender", ["_groupId", ""], ["_text", ""], ["_pos", []], ["_time", ""]];

if (!hasInterface) exitWith {};
if (missionNamespace getVariable ["COMSPEC_AthenaBridge_SuppressMirror", false]) exitWith {};
if (isNull _sender || {!(_sender isEqualTo player)}) exitWith {};
if ((trim _text) isEqualTo "") exitWith {};
if (isNil "comspec_overwatch_connect_fnc_sendIntel") exitWith {};

private _cs = "";
if (!isNil "comspec_overwatch_connect_fnc_getCallsign") then {
    _cs = [] call comspec_overwatch_connect_fnc_getCallsign;
};
if (_cs isEqualTo "") then { _cs = name player; };

private _grid = if ((count _pos) >= 2) then { mapGridPosition _pos } else { mapGridPosition player };
private _msg = format ["GROUPE|%1|%2|%3|%4", _groupId, _cs, _grid, trim _text];

missionNamespace setVariable ["COMSPEC_AthenaBridge_SuppressMirror", true, false];
[player, "CHAT", _msg, "", "INFANTRY", 0.9] call comspec_overwatch_connect_fnc_sendIntel;
missionNamespace setVariable ["COMSPEC_AthenaBridge_SuppressMirror", false, false];

private _inbox = missionNamespace getVariable ["COMSPEC_Athena_AlertInbox", []];
if (!(_inbox isEqualType [])) then { _inbox = []; };
_inbox pushBack [
    "GROUP",
    "Message de groupe",
    _text,
    _grid,
    if (_time isEqualTo "") then { [daytime, "HH:MM"] call BIS_fnc_timeToString } else { _time },
    _cs
];
while { (count _inbox) > 40 } do { _inbox deleteAt 0; };
missionNamespace setVariable ["COMSPEC_Athena_AlertInbox", _inbox, false];
["COMSPEC_AthenaInboxUpdated", []] call CBA_fnc_localEvent;
