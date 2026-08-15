#include "..\script_component.hpp"

params ["_group", ["_interfaceInit", false], "_isDialog", "_settings"];

if (isNull _group) exitWith {};
uiNamespace setVariable ["Iceman_ATAK_BDA_group", _group];

{
    if (_x getVariable ["IcemanBDACtrl", false]) then {
        ctrlDelete _x;
    } else {
        _x ctrlShow false;
        _x ctrlEnable false;
    };
} forEach allControls _group;

private _pos = ctrlPosition _group;
private _w = _pos # 2;
private _h = _pos # 3;
private _row = _h / 12;
private _gap = _row * 0.08;
private _buttonH = _row * 0.82;
private _thirdW = (_w - (4 * _gap)) / 3;
private _fullW = _w - (2 * _gap);

private _makeCtrl = {
    params ["_class", "_idc", "_rect"];
    private _ctrl = (ctrlParent _group) ctrlCreate [_class, _idc, _group];
    _ctrl setVariable ["IcemanBDACtrl", true];
    _ctrl ctrlSetPosition _rect;
    _ctrl ctrlCommit 0;
    _ctrl
};

private _title = ["RscStructuredText", 9700, [0, 0, _w, _row]] call _makeCtrl;
_title ctrlSetStructuredText parseText "<t align='center' size='1.05'>BDA Report</t>";

private _makeField = {
    params ["_labelIDC", "_editIDC", "_rowIndex", "_label", "_default"];
    private _y = (_row * _rowIndex) + _gap;
    private _labelCtrl = ["RscStructuredText", _labelIDC, [_gap, _y, _thirdW, _buttonH]] call _makeCtrl;
    _labelCtrl ctrlSetStructuredText parseText format ["<t size='0.82'>%1</t>", _label];
    private _editCtrl = ["RscEdit", _editIDC, [(_gap * 2) + _thirdW, _y, (_thirdW * 2) + _gap, _buttonH]] call _makeCtrl;
    _editCtrl ctrlSetText _default;
    _editCtrl
};

[9710, 9711, 1, "Target", ""] call _makeField;
[9712, 9713, 2, "Grid", mapGridPosition (getPosASL player)] call _makeField;
[9714, 9715, 3, "Damage", ""] call _makeField;
[9716, 9717, 4, "Enemy BDA", ""] call _makeField;
[9718, 9719, 5, "Friendly/CIV", ""] call _makeField;
[9720, 9721, 6, "Munitions", ""] call _makeField;
[9722, 9723, 7, "Remarks", ""] call _makeField;

private _send = ["BCE_RscButtonMenu", 9724, [_gap, (_row * 8) + _gap, (_w - (3 * _gap)) / 2, _buttonH]] call _makeCtrl;
_send ctrlSetText "Send BDA";
_send ctrlAddEventHandler ["ButtonClick", {call Iceman_fnc_bda_send}];

private _clear = ["BCE_RscButtonMenu", 9725, [(_gap * 2) + ((_w - (3 * _gap)) / 2), (_row * 8) + _gap, (_w - (3 * _gap)) / 2, _buttonH]] call _makeCtrl;
_clear ctrlSetText "Clear Form";
_clear ctrlAddEventHandler ["ButtonClick", {call Iceman_fnc_bda_clearForm}];

private _status = ["RscStructuredText", 9726, [_gap, (_row * 9) + _gap, _fullW, _h - ((_row * 9) + (2 * _gap))]] call _makeCtrl;
_status ctrlSetStructuredText parseText "";

call Iceman_fnc_bda_updatePanel;
