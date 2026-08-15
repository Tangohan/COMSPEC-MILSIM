#include "..\script_component.hpp"

if (!hasInterface) exitWith {false};

private _sender = missionNamespace getVariable ["cTab_player", player];
if (isNull _sender) then {
    _sender = player;
};

if !([_sender, ctab_core_leaderDevices] call cTab_fnc_checkGear) exitWith {
    ["BDA", "ATAK device required to send BDA.", 4] call cTab_fnc_addNotification;
    false
};

private _group = uiNamespace getVariable ["Iceman_ATAK_BDA_group", controlNull];
if (isNull _group) exitWith {
    ["BDA", "BDA form is not open.", 3] call cTab_fnc_addNotification;
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

private _target = [9711] call _field;
private _gridInput = [9713] call _field;
private _damage = [9715] call _field;
private _enemy = [9717] call _field;
private _friendly = [9719] call _field;
private _munitions = [9721] call _field;
private _remarks = [9723] call _field;

if (([_target, _damage, _enemy, _friendly, _munitions, _remarks] findIf {_x != ""}) < 0) exitWith {
    ["BDA", "Enter BDA information before sending.", 4] call cTab_fnc_addNotification;
    false
};

private _pos = getPosASL _sender;
private _time = call cTab_fnc_currentTime;
private _grid = if (_gridInput == "") then {mapGridPosition _pos} else {_gridInput};

private _body = [
    "<t color='#ffd36a'>BDA REPORT</t>",
    format ["Observer: %1", name _sender],
    format ["Grid: %1", [_grid] call _safe],
    format ["Time: %1", _time],
    "",
    format ["1. Target/Objective: %1", [_target] call _safe],
    format ["2. Damage Observed: %1", [_damage] call _safe],
    format ["3. Enemy BDA: %1", [_enemy] call _safe],
    format ["4. Friendly/Civilian Effects: %1", [_friendly] call _safe],
    format ["5. Munitions/Method: %1", [_munitions] call _safe],
    format ["6. Remarks: %1", [_remarks] call _safe]
] joinString "<br/>";

private _recipients = ((allPlayers + playableUnits) arrayIntersect (allPlayers + playableUnits)) select {
    isPlayer _x &&
    {[_x, ctab_core_leaderDevices] call cTab_fnc_checkGear}
};

["Iceman_ATAK_BDA", [_sender, _pos, _body, _time]] call CBA_fnc_globalEvent;
["BDA", format ["BDA sent to %1 ATAK user(s).", count _recipients], 4] call cTab_fnc_addNotification;
playSound "cTab_mailSent";

call Iceman_fnc_bda_clearForm;
true
