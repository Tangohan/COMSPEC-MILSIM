#include "..\script_component.hpp"

private _group = uiNamespace getVariable ["Iceman_ATAK_Alerts_group", controlNull];
if (isNull _group || {!ctrlShown _group}) then {
    private _display = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
    if (isNull _display) then {
        _display = uiNamespace getVariable ["cTab_Android_dsp", displayNull];
    };
    if (!isNull _display) then {
        private _apps = _display displayCtrl (17000 + 4650);
        if (!isNull _apps) then {
            {
                if ((ctrlClassName _x) isEqualTo "Iceman_ATAK_Reports") exitWith {
                    _group = _x;
                    uiNamespace setVariable ["Iceman_ATAK_Alerts_group", _group];
                };
            } forEach (allControls _apps);
        };
    };
};

private _controls = [];
if (!isNull _group) then {
    _controls = allControls _group;
    // Inclure les enfants des groupes scroll (détail Inbox).
    {
        if (ctrlType _x == 15) then {
            _controls append (allControls _x);
        };
    } forEach +_controls;
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
    } else {
        // Contrôles hérités (Title ATAK, etc.) : masquer hors coque Reports.
        private _idc = ctrlIDC _x;
        if (_idc in [5, 6, 10, 11]) then {
            _x ctrlShow false;
            _x ctrlEnable false;
        };
    };
} forEach _controls;

// Filet IDC : Inbox vs New ne doivent jamais cohabiter.
private _showInbox = _tab isEqualTo "inbox";
{
    private _idc = ctrlIDC _x;
    if (_idc in [9610, 9611, 9612, 9613, 9615]) then {
        _x ctrlShow _showInbox;
        _x ctrlEnable _showInbox;
    };
    if (_idc == 9614 || {_idc >= 9620 && {_idc <= 9729}} || {_idc >= 9540 && {_idc <= 9551}}) then {
        private _formTag = _x getVariable ["IcemanReportsForm", ""];
        private _showNew = !_showInbox;
        if (_idc in [9614, 9620, 9621, 9622, 9623]) then {
            _showNew = !_showInbox;
        } else {
            if (_showNew && {_formTag != ""} && {_formTag != "commonForm"}) then {
                _showNew = _formTag isEqualTo _form;
            };
        };
        _x ctrlShow _showNew;
        _x ctrlEnable _showNew;
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
