#include "..\script_component.hpp"

params ["_group", ["_interfaceInit", false], "_isDialog", "_settings"];

if (isNull _group) exitWith {};
uiNamespace setVariable ["Iceman_ATAK_Group_group", _group];

{
    if (_x getVariable ["IcemanGroupCtrl", false]) then {
        ctrlDelete _x;
    } else {
        _x ctrlShow false;
        _x ctrlEnable false;
    };
} forEach allControls _group;

private _pos = ctrlPosition _group;
private _w = _pos # 2;
private _h = _pos # 3;
private _row = _h / 14;
private _gap = _row * 0.08;
private _fullW = _w - (2 * _gap);

private _makeCtrl = {
    params ["_class", "_idc", "_rect"];
    private _ctrl = (ctrlParent _group) ctrlCreate [_class, _idc, _group];
    _ctrl setVariable ["IcemanGroupCtrl", true];
    _ctrl ctrlSetPosition _rect;
    _ctrl ctrlCommit 0;
    _ctrl
};

private _title = ["RscStructuredText", 9900, [0, 0, _w, _row]] call _makeCtrl;
_title ctrlSetStructuredText parseText "<t align='center' size='1.02'>Group Messages</t>";

private _historyH = _h - (_row * 3.2) - (_gap * 4);
private _history = ["Iceman_ReportsScrollGroup", 9902, [_gap, _row + _gap, _fullW, _historyH]] call _makeCtrl;
_history ctrlSetBackgroundColor [0.035, 0.045, 0.05, 0.92];

private _composeY = _row + _gap + _historyH + (_gap * 2);
private _sendW = _fullW * 0.25;
private _editW = _fullW - _sendW - _gap;

private _edit = ["RscEdit", 9904, [_gap, _composeY, _editW, _row * 1.4]] call _makeCtrl;
_edit ctrlSetText "";

private _send = ["BCE_RscButtonMenu", 9905, [_gap + _editW + _gap, _composeY, _sendW, _row * 1.4]] call _makeCtrl;
_send ctrlSetText "SEND";
_send ctrlAddEventHandler ["ButtonClick", {call Iceman_fnc_group_sendMessage}];

call Iceman_fnc_group_updatePanel;
