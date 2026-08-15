#include "..\script_component.hpp"

private _group = uiNamespace getVariable ["Iceman_ATAK_Alerts_group", controlNull];
private _controls = [];

if (!isNull _group) then {
    _controls = allControls _group;
} else {
    private _display = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
    if (isNull _display) exitWith {};
    _controls = allControls _display;
};

private _tab = missionNamespace getVariable ["Iceman_ATAK_Reports_tab", "inbox"];
private _form = missionNamespace getVariable ["Iceman_ATAK_Reports_form", "TIC"];
if !(_tab in ["inbox", "new"]) then {
    _tab = "inbox";
    Iceman_ATAK_Reports_tab = _tab;
};
if !(_form in ["TIC", "EAGLE_DOWN", "BDA", "FRAGO", "SALUTE"]) then {
    _form = "TIC";
    Iceman_ATAK_Reports_form = _form;
};
{
    private _section = _x getVariable ["IcemanReportsSection", ""];
    if (_section != "") then {
        private _show = _section == "common" || {_section == _tab};
        private _formTag = _x getVariable ["IcemanReportsForm", ""];
        if (_show && {_section == "new"} && {_formTag != ""}) then {
            _show = _formTag == "commonForm" || {_formTag == _form};
        };
        _x ctrlShow _show;
        _x ctrlEnable _show;
    };
} forEach _controls;

private _listCtrl = controlNull;
private _detailCtrl = controlNull;
private _detailGroupCtrl = controlNull;
private _formTitleCtrl = controlNull;
private _typeComboCtrl = controlNull;
{
    switch (ctrlIDC _x) do {
        case 9610: {_listCtrl = _x};
        case 9613: {_detailGroupCtrl = _x};
        case 9615: {_detailCtrl = _x};
        case 9620: {_typeComboCtrl = _x};
        case 9623: {_formTitleCtrl = _x};
    };
} forEach _controls;

if (isNull _detailCtrl && {!isNull _detailGroupCtrl}) then {
    _detailCtrl = _detailGroupCtrl controlsGroupCtrl 9615;
};

if (!isNull _typeComboCtrl) then {
    private _wanted = _form;
    _typeComboCtrl setVariable ["IcemanReportsTypeUpdating", true];
    for "_i" from 0 to ((lbSize _typeComboCtrl) - 1) do {
        if ((_typeComboCtrl lbData _i) == _wanted) exitWith {
            if ((lbCurSel _typeComboCtrl) != _i) then {
                _typeComboCtrl lbSetCurSel _i;
            };
        };
    };
    _typeComboCtrl setVariable ["IcemanReportsTypeUpdating", false];
};

if (!isNull _formTitleCtrl) then {
    private _formTitle = switch (_form) do {
        case "EAGLE_DOWN": {"Eagle Down"};
        case "BDA": {"BDA Report"};
        case "FRAGO": {"FRAGO"};
        case "SALUTE": {"SALUTE"};
        default {"TIC"};
    };
    _formTitleCtrl ctrlSetStructuredText parseText format ["<t align='center' color='#ffd36a'>%1</t>", _formTitle];
};

if (isNull _listCtrl && {isNull _detailCtrl}) exitWith {};

private _reports = missionNamespace getVariable ["Iceman_ATAK_Reports_reports", []];
Iceman_ATAK_Alerts_reports = _reports;

private _selected = missionNamespace getVariable ["Iceman_ATAK_Reports_selected", -1];
if (_reports isEqualTo []) then {
    _selected = -1;
} else {
    if (_selected < 0 || {_selected >= count _reports}) then {
        _selected = (count _reports) - 1;
    };
};
Iceman_ATAK_Reports_selected = _selected;

if (!isNull _listCtrl) then {
    private _wantedData = str _selected;
    _listCtrl setVariable ["IcemanReportsUpdating", true];
    lbClear _listCtrl;
    for "_i" from ((count _reports) - 1) to 0 step -1 do {
        (_reports # _i) params ["_time", "_kind", "_sender", "_grid"];
        private _row = _listCtrl lbAdd format ["%1  %2  %3", _time, _kind, _grid];
        _listCtrl lbSetData [_row, str _i];
        if ((str _i) == _wantedData) then {
            _listCtrl lbSetCurSel _row;
        };
    };
    _listCtrl setVariable ["IcemanReportsUpdating", false];
};

if (!isNull _detailCtrl) then {
    if (_selected < 0) exitWith {
        _detailCtrl ctrlSetStructuredText parseText "No reports received.";
    };

    (_reports # _selected) params ["_time", "_kind", "_sender", "_grid", "_body", "_pos"];
    private _text = [
        format ["<t color='#ffd36a'>%1</t> <t color='#ffffff'>%2</t>", _time, _kind],
        format ["From: %1", _sender],
        format ["Grid: %1", _grid],
        "",
        _body
    ] joinString "<br/>";

    _detailCtrl ctrlSetStructuredText parseText _text;
    private _detailPos = ctrlPosition _detailCtrl;
    private _neededH = ((ctrlTextHeight _detailCtrl) + 0.02) max 0.5;
    _detailCtrl ctrlSetPosition [_detailPos # 0, _detailPos # 1, _detailPos # 2, _neededH];
    _detailCtrl ctrlCommit 0;
};
