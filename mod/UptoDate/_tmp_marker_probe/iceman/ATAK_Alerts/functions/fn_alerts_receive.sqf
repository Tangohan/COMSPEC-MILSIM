#include "..\script_component.hpp"

params ["_kind", "_sender", "_pos", "_body", ["_time", ""], ["_kindText", ""]];

if (!hasInterface) exitWith {};

private _receiver = missionNamespace getVariable ["cTab_player", player];
if (isNull _receiver) then {
    _receiver = player;
};
if !([_receiver, ctab_core_leaderDevices] call cTab_fnc_checkGear) exitWith {};

private _kindKey = toUpper _kind;
if (_kindText == "") then {
    _kindText = switch (_kindKey) do {
        case "TIC": {"TIC"};
        case "TIC_CLEAR": {"TIC CLEAR"};
        case "CLEAR": {"TIC CLEAR"};
        case "PANIC": {"EAGLE DOWN"};
        case "EAGLE_DOWN": {"EAGLE DOWN"};
        case "FRAGO": {"FRAGO"};
        case "SALUTE": {"SALUTE"};
        default {_kindKey};
    };
};
if (_time == "") then {
    _time = call cTab_fnc_currentTime;
};

private _senderName = if (isNull _sender) then {"Unknown"} else {name _sender};
private _grid = mapGridPosition _pos;

private _reports = +(missionNamespace getVariable ["Iceman_ATAK_Reports_reports", []]);
_reports pushBack [_time, _kindText, _senderName, _grid, _body, _pos];
while {(count _reports) > 50} do {
    _reports deleteAt 0;
};
Iceman_ATAK_Reports_reports = _reports;
Iceman_ATAK_Alerts_reports = _reports;
Iceman_ATAK_Reports_selected = (count _reports) - 1;

if (_kindKey in ["PANIC", "EAGLE_DOWN"]) then {
    private _panics = +(missionNamespace getVariable ["Iceman_ATAK_Panic_reports", []]);
    _panics pushBack [_time, _kindText, _senderName, _grid, _body, _pos];
    while {(count _panics) > 50} do {
        _panics deleteAt 0;
    };
    Iceman_ATAK_Panic_reports = _panics;
    Iceman_ATAK_Panic_selected = (count _panics) - 1;
    call Iceman_fnc_panic_updatePanel;
};

private _key = call cTab_fnc_getPlayerEncryptionKey;
private _msgArray = _receiver getVariable ["cTab_messages_" + _key, []];
private _msgTitle = format ["%1 - ATAK Reports (%2)", _time, _senderName];
private _msgState = [0, 2] select (!isNull _sender && {_sender isEqualTo _receiver});
_msgArray pushBack [_msgTitle, _body, _msgState];
_receiver setVariable ["cTab_messages_" + _key, _msgArray];

["ctab_messagesUpdated"] call CBA_fnc_localEvent;
["ctab_core_messagesUpdated"] call CBA_fnc_localEvent;
call Iceman_fnc_alerts_updatePanel;

if (!isNull _sender && {_sender isEqualTo _receiver}) then {
    ["REPORTS", format ["%1 logged at %2.", _kindText, _grid], 5] call cTab_fnc_addNotification;
} else {
    ["REPORTS", format ["%1 from %2 at %3", _kindText, _senderName, _grid], 10] call cTab_fnc_addNotification;
    playSound "cTab_phoneVibrate";
};

true
