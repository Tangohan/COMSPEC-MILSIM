#include "..\script_component.hpp"

params [["_notify", true]];

if !(_notify isEqualType true) then {
    _notify = true;
};

private _group = uiNamespace getVariable ["Iceman_ATAK_Alerts_group", controlNull];
if (isNull _group) exitWith {false};

private _sender = missionNamespace getVariable ["cTab_player", player];
if (isNull _sender) then {
    _sender = player;
};

private _pad2 = {
    params ["_value"];
    private _text = str _value;
    if (_value < 10) then {_text = "0" + _text};
    _text
};
private _pad3 = {
    params ["_value"];
    private _text = str _value;
    while {count _text < 3} do {
        _text = "0" + _text;
    };
    _text
};
private _dtg = format [
    "%1%2%3 %4%5",
    date # 0,
    [date # 1] call _pad2,
    [date # 2] call _pad2,
    [date # 3] call _pad2,
    [date # 4] call _pad2
];
private _unit = groupId group _sender;
private _grid = mapGridPosition (getPosASL _sender);
private _trnCounter = missionNamespace getVariable ["Iceman_ATAK_Reports_trnCounter", 1];
private _trn = format ["A%1", [_trnCounter] call _pad3];

private _setText = {
    params ["_idc", "_text"];
    private _ctrl = _group controlsGroupCtrl _idc;
    if (!isNull _ctrl) then {
        _ctrl ctrlSetText _text;
    };
};
private _setCombo = {
    params ["_idc", ["_index", 0]];
    private _ctrl = _group controlsGroupCtrl _idc;
    if (!isNull _ctrl && {lbSize _ctrl > _index}) then {
        _ctrl lbSetCurSel _index;
    };
};

private _form = missionNamespace getVariable ["Iceman_ATAK_Reports_form", "TIC"];

switch (_form) do {
    case "BDA": {
        [9701, _dtg] call _setText;
        [9703, _unit] call _setText;
        [9705, _trn] call _setText;
        [9707, _grid] call _setText;
        [9711, ""] call _setText;
        [9713, "No vehicle ordnance"] call _setText;
        [9715, ""] call _setText;
        [9719, ""] call _setText;
        [9721, ""] call _setText;
        [9727, "All ATAK users"] call _setText;
        [9729, ""] call _setText;
        [9709, 0] call _setCombo;
        [9717, 0] call _setCombo;
        [9723, 0] call _setCombo;
        [9725, 0] call _setCombo;
    };
    case "FRAGO": {
        {
            [_x, ""] call _setText;
        } forEach [9631, 9633, 9635, 9637, 9639, 9641];
        [9643, name _sender] call _setText;
    };
    case "SALUTE": {
        {
            [_x, ""] call _setText;
        } forEach [9541, 9543, 9547, 9551];
        [9545, _grid] call _setText;
        [9549, _dtg] call _setText;
    };
    case "EAGLE_DOWN": {
        [9663, _dtg] call _setText;
        [9665, _unit] call _setText;
        [9667, _grid] call _setText;
        [9670, name _sender] call _setText;
        [9684, ""] call _setText;
        [9686, ""] call _setText;
        {
            [_x, 0] call _setCombo;
        } forEach [9661, 9672, 9674, 9676, 9679, 9681];
    };
    default {
        [9651, _unit] call _setText;
        [9653, _grid] call _setText;
        [9655, ""] call _setText;
        [9657, "All ATAK users"] call _setText;
    };
};

call Iceman_fnc_alerts_updatePanel;

if (_notify) then {
    ["REPORTS", "Report form cleared.", 3] call cTab_fnc_addNotification;
};

true
