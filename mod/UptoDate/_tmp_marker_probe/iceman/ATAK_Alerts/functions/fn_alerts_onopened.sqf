#include "..\script_component.hpp"

params ["_group", ["_interfaceInit", false], "_isDialog", "_settings"];

if (isNull _group) exitWith {};
uiNamespace setVariable ["Iceman_ATAK_Alerts_group", _group];

{
    if (_x getVariable ["IcemanReportsCtrl", false]) then {
        ctrlDelete _x;
    } else {
        _x ctrlShow false;
        _x ctrlEnable false;
    };
} forEach allControls _group;

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
private _sendTo = "All ATAK users";
private _vehicle = vehicle _sender;
private _platformDefault = "No player-operated vehicles";
private _platformItems = [["No player-operated vehicles", "No player-operated vehicles"]];
if (_vehicle != _sender) then {
    private _vehicleName = getText (configOf _vehicle >> "displayName");
    if (_vehicleName != "") then {
        _platformDefault = _vehicleName;
        _platformItems pushBack [_vehicleName, _vehicleName];
    };
};

private _pos = ctrlPosition _group;
private _w = _pos # 2;
private _h = _pos # 3;
private _row = _h / 21;
private _gap = _row * 0.08;
private _buttonH = _row * 0.78;
private _halfW = (_w - (3 * _gap)) / 2;
private _labelW = _w * 0.31;
private _inputW = _w - _labelW - (3 * _gap);
private _fullW = _w - (2 * _gap);

private _makeCtrl = {
    params ["_class", "_idc", "_section", "_rect", ["_form", ""]];
    private _ctrl = (ctrlParent _group) ctrlCreate [_class, _idc, _group];
    _ctrl setVariable ["IcemanReportsCtrl", true];
    _ctrl setVariable ["IcemanReportsSection", _section];
    if (_form != "") then {
        _ctrl setVariable ["IcemanReportsForm", _form];
    };
    _ctrl ctrlSetPosition _rect;
    _ctrl ctrlCommit 0;
    _ctrl
};

private _fillCombo = {
    params ["_ctrl", "_items", "_default"];
    lbClear _ctrl;
    private _selected = 0;
    {
        _x params ["_label", "_data"];
        private _idx = _ctrl lbAdd _label;
        _ctrl lbSetData [_idx, _data];
        if (_data == _default) then {
            _selected = _idx;
        };
    } forEach _items;
    _ctrl lbSetCurSel _selected;
};

private _makeField = {
    params ["_labelIDC", "_editIDC", "_form", "_rowIndex", "_label", "_default"];
    private _y = (_row * _rowIndex) + _gap;
    private _labelCtrl = ["RscStructuredText", _labelIDC, "new", [_gap, _y, _labelW, _buttonH], _form] call _makeCtrl;
    _labelCtrl ctrlSetStructuredText parseText format ["<t size='0.66'>%1</t>", _label];
    private _editCtrl = ["RscEdit", _editIDC, "new", [(_gap * 2) + _labelW, _y, _inputW, _buttonH], _form] call _makeCtrl;
    _editCtrl ctrlSetText _default;
    _editCtrl
};

private _makeComboField = {
    params ["_labelIDC", "_comboIDC", "_form", "_rowIndex", "_label", "_items", "_default"];
    private _y = (_row * _rowIndex) + _gap;
    private _labelCtrl = ["RscStructuredText", _labelIDC, "new", [_gap, _y, _labelW, _buttonH], _form] call _makeCtrl;
    _labelCtrl ctrlSetStructuredText parseText format ["<t size='0.66'>%1</t>", _label];
    private _combo = ["RscCombo", _comboIDC, "new", [(_gap * 2) + _labelW, _y, _inputW, _buttonH], _form] call _makeCtrl;
    [_combo, _items, _default] call _fillCombo;
    _combo
};
private _makeSection = {
    params ["_idc", "_form", "_rowIndex", "_label"];
    private _y = (_row * _rowIndex) + _gap;
    private _ctrl = ["RscStructuredText", _idc, "new", [_gap, _y, _fullW, _buttonH], _form] call _makeCtrl;
    _ctrl ctrlSetStructuredText parseText format ["<t align='center' size='0.72' color='#ffffff'>%1</t>", _label];
    _ctrl
};

private _title = ["RscStructuredText", 9600, "common", [0, 0, _w, _row]] call _makeCtrl;
_title ctrlSetStructuredText parseText "<t align='center' size='1.05'>Reports</t>";

private _tabInbox = ["BCE_RscButtonMenu", 9601, "common", [_gap, _row + _gap, _halfW, _buttonH]] call _makeCtrl;
_tabInbox ctrlSetText "Inbox";
_tabInbox ctrlAddEventHandler ["ButtonClick", {["inbox"] call Iceman_fnc_alerts_selectTab}];

private _tabNew = ["BCE_RscButtonMenu", 9602, "common", [(_gap * 2) + _halfW, _row + _gap, _halfW, _buttonH]] call _makeCtrl;
_tabNew ctrlSetText "New";
_tabNew ctrlAddEventHandler ["ButtonClick", {["new"] call Iceman_fnc_alerts_selectTab}];

private _list = ["RscListbox", 9610, "inbox", [_gap, (_row * 2) + _gap, _fullW, _row * 4.6]] call _makeCtrl;
_list ctrlAddEventHandler ["LBSelChanged", {_this call Iceman_fnc_alerts_selectReport}];

private _locate = ["BCE_RscButtonMenu", 9611, "inbox", [_gap, (_row * 6.8) + _gap, _halfW, _buttonH]] call _makeCtrl;
_locate ctrlSetText "Locate";
_locate ctrlAddEventHandler ["ButtonClick", {call Iceman_fnc_alerts_locateSelected}];

private _clear = ["BCE_RscButtonMenu", 9612, "inbox", [(_gap * 2) + _halfW, (_row * 6.8) + _gap, _halfW, _buttonH]] call _makeCtrl;
_clear ctrlSetText "Clear Local";
_clear ctrlAddEventHandler ["ButtonClick", {call Iceman_fnc_alerts_clearReports}];

private _detailGroup = ["Iceman_ReportsScrollGroup", 9613, "inbox", [_gap, (_row * 7.8) + _gap, _fullW, _h - ((_row * 7.8) + (2 * _gap))]] call _makeCtrl;
private _detail = (ctrlParent _group) ctrlCreate ["Iceman_ReportsDetailText", 9615, _detailGroup];
_detail setVariable ["IcemanReportsCtrl", true];
_detail ctrlSetPosition [0, 0, _fullW * 0.96, _h];
_detail ctrlCommit 0;
_detail ctrlSetStructuredText parseText "";

private _typeLabel = ["RscStructuredText", 9614, "new", [_gap, (_row * 2) + _gap, _labelW, _buttonH]] call _makeCtrl;
_typeLabel ctrlSetStructuredText parseText "<t size='0.66'>Report</t>";

private _typeCombo = ["RscCombo", 9620, "new", [(_gap * 2) + _labelW, (_row * 2) + _gap, _inputW, _buttonH]] call _makeCtrl;
[_typeCombo, [
    ["TIC", "TIC"],
    ["Eagle Down", "EAGLE_DOWN"],
    ["BDA", "BDA"],
    ["FRAGO", "FRAGO"],
    ["SALUTE", "SALUTE"]
], missionNamespace getVariable ["Iceman_ATAK_Reports_form", "TIC"]] call _fillCombo;
_typeCombo ctrlAddEventHandler ["LBSelChanged", {_this call Iceman_fnc_alerts_reportTypeChanged}];

private _formTitle = ["RscStructuredText", 9623, "new", [_gap, (_row * 3) + _gap, _fullW, _buttonH], "commonForm"] call _makeCtrl;
_formTitle ctrlSetStructuredText parseText "";

[9650, 9651, "TIC", 4, "Unit", _unit] call _makeField;
[9652, 9653, "TIC", 5, "Grid", _grid] call _makeField;
[9654, 9655, "TIC", 6, "Desc", ""] call _makeField;
[9656, 9657, "TIC", 7, "Send To", _sendTo] call _makeField;

[9540, 9541, "SALUTE", 4, "Size", ""] call _makeField;
[9542, 9543, "SALUTE", 5, "Activity", ""] call _makeField;
[9544, 9545, "SALUTE", 6, "Location", _grid] call _makeField;
[9546, 9547, "SALUTE", 7, "Unit/Uniform", ""] call _makeField;
[9548, 9549, "SALUTE", 8, "Time Observed", _dtg] call _makeField;
[9550, 9551, "SALUTE", 9, "Equipment", ""] call _makeField;

[9660, 9661, "EAGLE_DOWN", 4, "Category", [
    ["EAGLE DOWN", "EAGLE DOWN"]
], "EAGLE DOWN"] call _makeComboField;
[9662, 9663, "EAGLE_DOWN", 5, "DTG", _dtg] call _makeField;
[9664, 9665, "EAGLE_DOWN", 6, "Callsign", _unit] call _makeField;
[9666, 9667, "EAGLE_DOWN", 7, "Grid", _grid] call _makeField;
[9668, "EAGLE_DOWN", 8, "CASUALTY INFORMATION"] call _makeSection;
[9669, 9670, "EAGLE_DOWN", 9, "Casualty", name _sender] call _makeField;
[9671, 9672, "EAGLE_DOWN", 10, "Status", [
    ["Critical", "Critical"],
    ["Urgent", "Urgent"],
    ["Stable", "Stable"],
    ["KIA", "KIA"],
    ["Unknown", "Unknown"]
], "Critical"] call _makeComboField;
[9673, 9674, "EAGLE_DOWN", 11, "Mechanism", [
    ["GSW", "GSW"],
    ["Blast", "Blast"],
    ["Frag", "Frag"],
    ["Vehicle", "Vehicle"],
    ["Fall", "Fall"],
    ["Unknown", "Unknown"]
], "GSW"] call _makeComboField;
[9675, 9676, "EAGLE_DOWN", 12, "Situation", [
    ["Contact Ongoing", "Contact Ongoing"],
    ["Contact Broken", "Contact Broken"],
    ["Area Secure", "Area Secure"],
    ["Unknown", "Unknown"]
], "Contact Ongoing"] call _makeComboField;
[9677, "EAGLE_DOWN", 13, "LZ STATUS"] call _makeSection;
[9678, 9679, "EAGLE_DOWN", 14, "Medevac", [
    ["Priority", "Priority"],
    ["Urgent Surgical", "Urgent Surgical"],
    ["Routine", "Routine"],
    ["Convenience", "Convenience"]
], "Priority"] call _makeComboField;
[9680, 9681, "EAGLE_DOWN", 15, "LZ", [
    ["Secure", "Secure"],
    ["Hot", "Hot"],
    ["Marked", "Marked"],
    ["Unmarked", "Unmarked"],
    ["Unknown", "Unknown"]
], "Secure"] call _makeComboField;
[9682, "EAGLE_DOWN", 16, "REMARKS"] call _makeSection;
[9683, 9684, "EAGLE_DOWN", 17, "Treatment", ""] call _makeField;
[9685, 9686, "EAGLE_DOWN", 18, "Remarks", ""] call _makeField;

[9630, 9631, "FRAGO", 4, "Reference", ""] call _makeField;
[9632, 9633, "FRAGO", 5, "Situation", ""] call _makeField;
[9634, 9635, "FRAGO", 6, "Mission", ""] call _makeField;
[9636, 9637, "FRAGO", 7, "Execution", ""] call _makeField;
[9638, 9639, "FRAGO", 8, "Service Support", ""] call _makeField;
[9640, 9641, "FRAGO", 9, "Command/Signal", ""] call _makeField;
[9642, 9643, "FRAGO", 10, "Acknowledge", name _sender] call _makeField;

[9700, 9701, "BDA", 3, "DTG", _dtg] call _makeField;
[9702, 9703, "BDA", 4, "Unit", _unit] call _makeField;
[9704, 9705, "BDA", 5, "TRN", _trn] call _makeField;
[9706, 9707, "BDA", 6, "Grid", _grid] call _makeField;
[9708, 9709, "BDA", 7, "Type", [
    ["Infantry", "Infantry"],
    ["Vehicle", "Vehicle"],
    ["Armor", "Armor"],
    ["Artillery", "Artillery"],
    ["Structure", "Structure"],
    ["Air Defense", "Air Defense"],
    ["Other", "Other"]
], "Infantry"] call _makeComboField;
[9710, 9711, "BDA", 8, "Desc", ""] call _makeField;
[9712, 9713, "BDA", 9, "Ordnance", "No vehicle ordnance"] call _makeField;
[9714, 9715, "BDA", 10, "Munitions Count", ""] call _makeField;
[9716, 9717, "BDA", 11, "Platform", _platformItems, _platformDefault] call _makeComboField;
[9718, 9719, "BDA", 12, "EKIA", ""] call _makeField;
[9720, 9721, "BDA", 13, "Equip", ""] call _makeField;
[9722, 9723, "BDA", 14, "Rating", [
    ["Destroyed (D)", "Destroyed (D)"],
    ["Damaged", "Damaged"],
    ["Neutralized", "Neutralized"],
    ["Unknown", "Unknown"]
], "Destroyed (D)"] call _makeComboField;
[9724, 9725, "BDA", 15, "Reattack", [
    ["No Reattack Required", "No Reattack Required"],
    ["Reattack Required", "Reattack Required"],
    ["Reattack Recommended", "Reattack Recommended"]
], "No Reattack Required"] call _makeComboField;
[9726, 9727, "BDA", 16, "Send To", _sendTo] call _makeField;
[9728, 9729, "BDA", 17, "Reports", ""] call _makeField;

private _send = ["BCE_RscButtonMenu", 9621, "new", [_gap, (_row * 19) + _gap, _halfW, _buttonH]] call _makeCtrl;
_send ctrlSetText "Submit";
_send ctrlAddEventHandler ["ButtonClick", {call Iceman_fnc_alerts_submitReport}];

private _clearForm = ["BCE_RscButtonMenu", 9622, "new", [(_gap * 2) + _halfW, (_row * 19) + _gap, _halfW, _buttonH]] call _makeCtrl;
_clearForm ctrlSetText "Clear";
_clearForm ctrlAddEventHandler ["ButtonClick", {call Iceman_fnc_alerts_clearForm}];

call Iceman_fnc_alerts_updatePanel;
