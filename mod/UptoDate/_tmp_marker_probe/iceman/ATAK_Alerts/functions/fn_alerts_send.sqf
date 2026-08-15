#include "..\script_component.hpp"

params ["_kind", ["_body", ""], ["_pos", getPosASL player]];

if (!hasInterface) exitWith {false};

private _sender = missionNamespace getVariable ["cTab_player", player];
if (isNull _sender) then {
    _sender = player;
};

if !([_sender, ctab_core_leaderDevices] call cTab_fnc_checkGear) exitWith {
    ["REPORTS", "ATAK device required to send report.", 4] call cTab_fnc_addNotification;
    false
};

private _time = call cTab_fnc_currentTime;
private _grid = mapGridPosition _pos;
private _kindKey = toUpper _kind;
private _kindText = switch (_kindKey) do {
    case "TIC": {"TIC"};
    case "TIC_CLEAR": {"TIC CLEAR"};
    case "CLEAR": {"TIC CLEAR"};
    case "PANIC": {"EAGLE DOWN"};
    case "EAGLE_DOWN": {"EAGLE DOWN"};
    case "FRAGO": {"FRAGO"};
    case "SALUTE": {"SALUTE"};
    default {_kindKey};
};

private _msgBody = if (_body == "") then {
    format ["%1<br/>From: %2<br/>Grid: %3<br/>Time: %4", _kindText, name _sender, _grid, _time]
} else {
    format ["%1<br/>From: %2<br/>Grid: %3<br/>Time: %4<br/><br/>%5", _kindText, name _sender, _grid, _time, _body]
};

private _recipients = ((allPlayers + playableUnits) arrayIntersect (allPlayers + playableUnits)) select {
    isPlayer _x &&
    {[_x, ctab_core_leaderDevices] call cTab_fnc_checkGear}
};

["Iceman_ATAK_Alerts", [_kindKey, _sender, _pos, _msgBody, _time, _kindText]] call CBA_fnc_globalEvent;
["REPORTS", format ["%1 sent to %2 ATAK user(s).", _kindText, count _recipients], 4] call cTab_fnc_addNotification;
playSound "cTab_mailSent";

true
