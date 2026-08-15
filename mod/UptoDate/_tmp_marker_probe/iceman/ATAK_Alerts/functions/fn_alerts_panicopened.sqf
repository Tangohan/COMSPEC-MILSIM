#include "..\script_component.hpp"

params ["_group", ["_interfaceInit", false], "_isDialog", "_settings"];

if (isNull _group) exitWith {};
uiNamespace setVariable ["Iceman_ATAK_Alert_group", _group];

{
    if (_x getVariable ["IcemanAlertCtrl", false]) then {
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
private _fullW = _w - (2 * _gap);

private _makeCtrl = {
    params ["_class", "_idc", "_rect"];
    private _ctrl = (ctrlParent _group) ctrlCreate [_class, _idc, _group];
    _ctrl setVariable ["IcemanAlertCtrl", true];
    _ctrl ctrlSetPosition _rect;
    _ctrl ctrlCommit 0;
    _ctrl
};

private _title = ["RscStructuredText", 9800, [0, 0, _w, _row]] call _makeCtrl;
_title ctrlSetStructuredText parseText "<t align='center' size='1.05'>Alert</t>";

private _panic = ["BCE_RscButtonMenu", 9802, [_gap, _row + _gap, _fullW, _row * 1.35]] call _makeCtrl;
_panic ctrlSetText "PANIC";
_panic ctrlAddEventHandler ["ButtonClick", {call Iceman_fnc_alerts_sendPanic}];

private _list = ["RscListbox", 9810, [_gap, (_row * 2.55) + _gap, _fullW, _row * 2.35]] call _makeCtrl;
_list ctrlAddEventHandler ["LBSelChanged", {_this call Iceman_fnc_panic_selectReport}];

private _detailGroup = ["Iceman_ReportsScrollGroup", 9811, [_gap, (_row * 5.05) + _gap, _fullW, _row * 5.15]] call _makeCtrl;
private _detail = (ctrlParent _group) ctrlCreate ["Iceman_ReportsDetailText", 9812, _detailGroup];
_detail setVariable ["IcemanAlertCtrl", true];
_detail ctrlSetPosition [0, 0, _fullW - (_gap * 3), _row * 6];
_detail ctrlCommit 0;

private _locate = ["BCE_RscButtonMenu", 9813, [_gap, (_row * 10.45) + _gap, _fullW, _row * 1.1]] call _makeCtrl;
_locate ctrlSetText "LOCATE SELECTED";
_locate ctrlAddEventHandler ["ButtonClick", {call Iceman_fnc_panic_locateSelected}];

call Iceman_fnc_panic_updatePanel;
