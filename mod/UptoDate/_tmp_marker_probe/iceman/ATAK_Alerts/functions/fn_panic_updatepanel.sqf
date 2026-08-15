#include "..\script_component.hpp"

private _group = uiNamespace getVariable ["Iceman_ATAK_Alert_group", controlNull];
if (isNull _group) exitWith {};

private _listCtrl = controlNull;
private _detailCtrl = controlNull;
private _detailGroupCtrl = controlNull;
{
    switch (ctrlIDC _x) do {
        case 9810: {_listCtrl = _x};
        case 9811: {_detailGroupCtrl = _x};
        case 9812: {_detailCtrl = _x};
    };
} forEach allControls _group;

if (isNull _detailCtrl && {!isNull _detailGroupCtrl}) then {
    _detailCtrl = _detailGroupCtrl controlsGroupCtrl 9812;
};

private _panics = missionNamespace getVariable ["Iceman_ATAK_Panic_reports", []];
private _selected = missionNamespace getVariable ["Iceman_ATAK_Panic_selected", -1];
if (_panics isEqualTo []) then {
    _selected = -1;
} else {
    if (_selected < 0 || {_selected >= count _panics}) then {
        _selected = (count _panics) - 1;
    };
};
Iceman_ATAK_Panic_selected = _selected;

if (!isNull _listCtrl) then {
    _listCtrl setVariable ["IcemanPanicUpdating", true];
    lbClear _listCtrl;
    for "_i" from ((count _panics) - 1) to 0 step -1 do {
        (_panics # _i) params ["_time", "_kind", "_sender", "_grid"];
        private _row = _listCtrl lbAdd format ["%1  %2  %3", _time, _sender, _grid];
        _listCtrl lbSetData [_row, str _i];
        if (_i == _selected) then {
            _listCtrl lbSetCurSel _row;
        };
    };
    _listCtrl setVariable ["IcemanPanicUpdating", false];
};

if (!isNull _detailCtrl) then {
    if (_selected < 0) exitWith {
        _detailCtrl ctrlSetStructuredText parseText "No panic alerts received.";
    };

    (_panics # _selected) params ["_time", "_kind", "_sender", "_grid", "_body"];
    private _text = [
        format ["<t color='#ff7777'>%1</t> <t color='#ffffff'>%2</t>", _time, _kind],
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
