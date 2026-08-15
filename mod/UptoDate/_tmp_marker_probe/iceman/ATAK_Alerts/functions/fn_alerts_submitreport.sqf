#include "..\script_component.hpp"

if (!hasInterface) exitWith {false};

private _group = uiNamespace getVariable ["Iceman_ATAK_Alerts_group", controlNull];
if (isNull _group) exitWith {
    ["REPORTS", "Report form is not open.", 3] call cTab_fnc_addNotification;
    false
};

private _sender = missionNamespace getVariable ["cTab_player", player];
if (isNull _sender) then {
    _sender = player;
};

private _field = {
    params ["_idc"];
    private _ctrl = _group controlsGroupCtrl _idc;
    if (isNull _ctrl) exitWith {""};
    trim ctrlText _ctrl
};
private _combo = {
    params ["_idc"];
    private _ctrl = _group controlsGroupCtrl _idc;
    if (isNull _ctrl) exitWith {""};
    private _row = lbCurSel _ctrl;
    if (_row < 0) exitWith {""};
    private _data = _ctrl lbData _row;
    if (_data == "") then {_ctrl lbText _row} else {_data}
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

private _form = missionNamespace getVariable ["Iceman_ATAK_Reports_form", "TIC"];

if (_form == "FRAGO") exitWith {
    call Iceman_fnc_alerts_sendFrago
};

if (_form == "TIC") exitWith {
    private _unit = [9651] call _field;
    private _grid = [9653] call _field;
    private _desc = [9655] call _field;
    private _sendTo = [9657] call _field;
    private _body = [
        format ["Unit: %1", [_unit] call _safe],
        format ["Reported Grid: %1", [_grid] call _safe],
        format ["Desc: %1", [_desc, "No additional details."] call _safe],
        format ["Send To: %1", [_sendTo, "All ATAK users"] call _safe]
    ] joinString "<br/>";

    private _sent = [_form, _body, getPosASL _sender] call Iceman_fnc_alerts_send;
    if (_sent) then {
        [false] call Iceman_fnc_alerts_clearForm;
        Iceman_ATAK_Reports_tab = "inbox";
        call Iceman_fnc_alerts_updatePanel;
    };
    _sent
};

if (_form == "EAGLE_DOWN") exitWith {
    private _category = [9661] call _combo;
    private _dtg = [9663] call _field;
    private _callsign = [9665] call _field;
    private _grid = [9667] call _field;
    private _casualty = [9670] call _field;
    private _status = [9672] call _combo;
    private _mechanism = [9674] call _combo;
    private _situation = [9676] call _combo;
    private _medevac = [9679] call _combo;
    private _lz = [9681] call _combo;
    private _treatment = [9684] call _field;
    private _remarks = [9686] call _field;

    private _body = [
        format ["Category: %1", [_category, "EAGLE DOWN"] call _safe],
        format ["DTG: %1", [_dtg] call _safe],
        format ["Callsign: %1", [_callsign] call _safe],
        format ["Grid: %1", [_grid] call _safe],
        "",
        "<t color='#ffd36a'>CASUALTY INFORMATION</t>",
        format ["Casualty: %1", [_casualty] call _safe],
        format ["Status: %1", [_status] call _safe],
        format ["Mechanism: %1", [_mechanism] call _safe],
        format ["Situation: %1", [_situation] call _safe],
        "",
        "<t color='#ffd36a'>LZ STATUS</t>",
        format ["Medevac: %1", [_medevac] call _safe],
        format ["LZ: %1", [_lz] call _safe],
        "",
        "<t color='#ffd36a'>REMARKS</t>",
        format ["Current Treatment: %1", [_treatment] call _safe],
        format ["Remarks: %1", [_remarks] call _safe]
    ] joinString "<br/>";

    private _sent = ["EAGLE_DOWN", _body, getPosASL _sender] call Iceman_fnc_alerts_send;
    if (_sent) then {
        [false] call Iceman_fnc_alerts_clearForm;
        Iceman_ATAK_Reports_tab = "inbox";
        call Iceman_fnc_alerts_updatePanel;
    };
    _sent
};

if (_form == "SALUTE") exitWith {
    private _size = [9541] call _field;
    private _activity = [9543] call _field;
    private _location = [9545] call _field;
    private _unitUniform = [9547] call _field;
    private _timeObserved = [9549] call _field;
    private _equipment = [9551] call _field;

    if (([_size, _activity, _unitUniform, _equipment] findIf {_x != ""}) < 0) exitWith {
        ["REPORTS", "Enter SALUTE information before submitting.", 4] call cTab_fnc_addNotification;
        false
    };

    private _body = [
        "<t color='#ffd36a'>SPOT REPORT / SALUTE</t>",
        format ["1. Size: %1", [_size] call _safe],
        format ["2. Activity: %1", [_activity] call _safe],
        format ["3. Location: %1", [_location] call _safe],
        format ["4. Unit/Uniform: %1", [_unitUniform] call _safe],
        format ["5. Time Observed: %1", [_timeObserved] call _safe],
        format ["6. Equipment: %1", [_equipment] call _safe]
    ] joinString "<br/>";

    private _sent = ["SALUTE", _body, getPosASL _sender] call Iceman_fnc_alerts_send;
    if (_sent) then {
        [false] call Iceman_fnc_alerts_clearForm;
        Iceman_ATAK_Reports_tab = "inbox";
        call Iceman_fnc_alerts_updatePanel;
    };
    _sent
};

if (_form != "BDA") exitWith {
    ["REPORTS", "Select a report type before submitting.", 3] call cTab_fnc_addNotification;
    false
};

if !([_sender, ctab_core_leaderDevices] call cTab_fnc_checkGear) exitWith {
    ["BDA", "ATAK device required to send BDA.", 4] call cTab_fnc_addNotification;
    false
};

private _dtg = [9701] call _field;
private _unit = [9703] call _field;
private _trn = [9705] call _field;
private _grid = [9707] call _field;
private _type = [9709] call _combo;
private _desc = [9711] call _field;
private _ordnance = [9713] call _field;
private _munitions = [9715] call _field;
private _platform = [9717] call _combo;
private _ekia = [9719] call _field;
private _equip = [9721] call _field;
private _rating = [9723] call _combo;
private _reattack = [9725] call _combo;
private _sendTo = [9727] call _field;
private _reports = [9729] call _field;

if (([_desc, _munitions, _ekia, _equip, _reports] findIf {_x != ""}) < 0) exitWith {
    ["BDA", "Enter BDA details before submitting.", 4] call cTab_fnc_addNotification;
    false
};

private _pos = getPosASL _sender;
private _time = call cTab_fnc_currentTime;
private _body = [
    "<t color='#ffd36a'>BDA REPORT</t>",
    format ["DTG: %1", [_dtg] call _safe],
    format ["Unit: %1", [_unit] call _safe],
    format ["TRN: %1", [_trn] call _safe],
    format ["Grid: %1", [_grid] call _safe],
    format ["Type: %1", [_type] call _safe],
    format ["Desc: %1", [_desc] call _safe],
    format ["Ordnance: %1", [_ordnance] call _safe],
    format ["Munition(s) Count: %1", [_munitions] call _safe],
    format ["Platform: %1", [_platform] call _safe],
    format ["EKIA: %1", [_ekia] call _safe],
    format ["Equip: %1", [_equip] call _safe],
    format ["Rating: %1", [_rating] call _safe],
    format ["Reattack: %1", [_reattack] call _safe],
    format ["Send To: %1", [_sendTo, "All ATAK users"] call _safe],
    format ["Reports: %1", [_reports] call _safe]
] joinString "<br/>";

private _recipients = ((allPlayers + playableUnits) arrayIntersect (allPlayers + playableUnits)) select {
    isPlayer _x &&
    {[_x, ctab_core_leaderDevices] call cTab_fnc_checkGear}
};

["Iceman_ATAK_BDA", [_sender, _pos, _body, _time]] call CBA_fnc_globalEvent;
["BDA", format ["BDA sent to %1 ATAK user(s).", count _recipients], 4] call cTab_fnc_addNotification;
playSound "cTab_mailSent";

Iceman_ATAK_Reports_trnCounter = (missionNamespace getVariable ["Iceman_ATAK_Reports_trnCounter", 1]) + 1;
[false] call Iceman_fnc_alerts_clearForm;
Iceman_ATAK_Reports_tab = "inbox";
call Iceman_fnc_alerts_updatePanel;

true
