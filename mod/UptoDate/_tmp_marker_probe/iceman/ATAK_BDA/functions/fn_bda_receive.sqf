#include "..\script_component.hpp"

params ["_sender", "_pos", "_body", ["_time", ""]];

if (!hasInterface) exitWith {};

private _receiver = missionNamespace getVariable ["cTab_player", player];
if (isNull _receiver) then {
    _receiver = player;
};
if !([_receiver, ctab_core_leaderDevices] call cTab_fnc_checkGear) exitWith {};

if (_time == "") then {
    _time = call cTab_fnc_currentTime;
};

private _senderName = if (isNull _sender) then {"Unknown"} else {name _sender};
private _grid = mapGridPosition _pos;

private _reports = +(missionNamespace getVariable ["Iceman_ATAK_BDA_reports", []]);
_reports pushBack [_time, "BDA REPORT", _senderName, _grid, _body, _pos];
while {(count _reports) > 50} do {
    _reports deleteAt 0;
};
Iceman_ATAK_BDA_reports = _reports;

private _allReports = +(missionNamespace getVariable ["Iceman_ATAK_Reports_reports", []]);
_allReports pushBack [_time, "BDA REPORT", _senderName, _grid, _body, _pos];
while {(count _allReports) > 50} do {
    _allReports deleteAt 0;
};
Iceman_ATAK_Reports_reports = _allReports;
Iceman_ATAK_Alerts_reports = _allReports;
Iceman_ATAK_Reports_selected = (count _allReports) - 1;

private _key = call cTab_fnc_getPlayerEncryptionKey;
private _msgArray = _receiver getVariable ["cTab_messages_" + _key, []];
private _msgTitle = format ["%1 - ATAK BDA (%2)", _time, _senderName];
private _msgState = [0, 2] select (!isNull _sender && {_sender isEqualTo _receiver});
_msgArray pushBack [_msgTitle, _body, _msgState];
_receiver setVariable ["cTab_messages_" + _key, _msgArray];

["ctab_messagesUpdated"] call CBA_fnc_localEvent;
["ctab_core_messagesUpdated"] call CBA_fnc_localEvent;
call Iceman_fnc_bda_updatePanel;
if (!(isNil "Iceman_fnc_alerts_updatePanel")) then {
    call Iceman_fnc_alerts_updatePanel;
};

if (!isNull _sender && {_sender isEqualTo _receiver}) then {
    ["BDA", format ["BDA logged at %1.", _grid], 5] call cTab_fnc_addNotification;
} else {
    ["BDA", format ["BDA from %1 at %2", _senderName, _grid], 10] call cTab_fnc_addNotification;
    playSound "cTab_phoneVibrate";
};

true
