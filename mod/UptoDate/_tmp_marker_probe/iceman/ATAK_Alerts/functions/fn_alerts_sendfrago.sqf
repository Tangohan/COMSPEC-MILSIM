#include "..\script_component.hpp"

if (!hasInterface) exitWith {false};

private _group = uiNamespace getVariable ["Iceman_ATAK_Alerts_group", controlNull];
if (isNull _group) exitWith {
    ["REPORTS", "FRAGO form is not open.", 3] call cTab_fnc_addNotification;
    false
};

private _field = {
    params ["_idc"];
    private _ctrl = _group controlsGroupCtrl _idc;
    if (isNull _ctrl) exitWith {""};
    trim ctrlText _ctrl
};

private _safe = {
    params ["_text", ["_fallback", "N/A"]];
    _text = trim _text;
    if (_text == "") then {
        _text = _fallback;
    };
    _text = (_text splitString "&") joinString "&amp;";
    _text = (_text splitString "<") joinString "&lt;";
    _text = (_text splitString ">") joinString "&gt;";
    _text
};

private _reference = [9631] call _field;
private _situation = [9633] call _field;
private _mission = [9635] call _field;
private _execution = [9637] call _field;
private _support = [9639] call _field;
private _command = [9641] call _field;
private _ack = [9643] call _field;

if (([_reference, _situation, _mission, _execution, _support, _command, _ack] findIf {_x != ""}) < 0) exitWith {
    ["REPORTS", "Enter FRAGO information before sending.", 4] call cTab_fnc_addNotification;
    false
};

private _sender = missionNamespace getVariable ["cTab_player", player];
if (isNull _sender) then {
    _sender = player;
};

private _body = [
    "<t color='#ffd36a'>FRAGMENTARY ORDER</t>",
    format ["References: %1", [_reference] call _safe],
    "Time Zone Used Throughout the Order: Local",
    "",
    format ["1. SITUATION: %1", [_situation] call _safe],
    format ["2. MISSION: %1", [_mission] call _safe],
    format ["3. EXECUTION: %1", [_execution] call _safe],
    format ["4. SERVICE SUPPORT: %1", [_support] call _safe],
    format ["5. COMMAND AND SIGNAL: %1", [_command] call _safe],
    format ["ACKNOWLEDGE: %1", [_ack, name _sender] call _safe]
] joinString "<br/>";

private _sent = ["FRAGO", _body, getPosASL _sender] call Iceman_fnc_alerts_send;
if (!_sent) exitWith {false};

{
    private _ctrl = _group controlsGroupCtrl _x;
    if (!isNull _ctrl) then {
        _ctrl ctrlSetText "";
    };
} forEach [9631, 9633, 9635, 9637, 9639, 9641, 9643];

Iceman_ATAK_Reports_tab = "inbox";
call Iceman_fnc_alerts_updatePanel;
true
