#include "..\script_component.hpp"

params ["_group"];

if (isNull _group) exitWith {};

{
    if (_x getVariable ["IcemanRouteCtrl", false]) then {
        ctrlDelete _x;
    } else {
        _x ctrlShow false;
        _x ctrlEnable false;
    };
} forEach allControls _group;

private _pos = ctrlPosition _group;
private _w = _pos # 2;
private _h = _pos # 3;
private _row = _h / 10;
private _gap = _row * 0.08;
private _buttonH = _row * 0.82;
private _halfW = (_w - (3 * _gap)) / 2;
private _fullW = _w - (2 * _gap);

private _makeCtrl = {
    params ["_class", "_idc", "_section", "_rect"];
    private _ctrl = (ctrlParent _group) ctrlCreate [_class, _idc, _group];
    _ctrl setVariable ["IcemanRouteCtrl", true];
    _ctrl setVariable ["IcemanRouteSection", _section];
    _ctrl ctrlSetPosition _rect;
    _ctrl ctrlCommit 0;
    _ctrl
};

private _title = ["RscStructuredText", 9300, "common", [0, 0, _w, _row]] call _makeCtrl;
_title ctrlSetStructuredText parseText "<t align='center' size='1.05'>Route</t>";

private _tabRoute = ["BCE_RscButtonMenu", 9301, "common", [_gap, _row + _gap, _halfW, _buttonH]] call _makeCtrl;
_tabRoute ctrlSetText "Route";
_tabRoute ctrlAddEventHandler ["ButtonClick", {["route"] call Iceman_fnc_route_selectTab}];

private _tabWp = ["BCE_RscButtonMenu", 9302, "common", [(_gap * 2) + _halfW, _row + _gap, _halfW, _buttonH]] call _makeCtrl;
_tabWp ctrlSetText "Waypoints";
_tabWp ctrlAddEventHandler ["ButtonClick", {["waypoints"] call Iceman_fnc_route_selectTab}];

private _setStart = ["BCE_RscButtonMenu", 9310, "route", [_gap, (_row * 2) + _gap, _halfW, _buttonH]] call _makeCtrl;
_setStart ctrlSetText "Set Start";
_setStart ctrlAddEventHandler ["ButtonClick", {["start"] call Iceman_fnc_route_selectMode}];

private _setEnd = ["BCE_RscButtonMenu", 9311, "route", [(_gap * 2) + _halfW, (_row * 2) + _gap, _halfW, _buttonH]] call _makeCtrl;
_setEnd ctrlSetText "Set End";
_setEnd ctrlAddEventHandler ["ButtonClick", {["end"] call Iceman_fnc_route_selectMode}];

private _fromMe = ["BCE_RscButtonMenu", 9312, "route", [_gap, (_row * 3) + _gap, _halfW, _buttonH]] call _makeCtrl;
_fromMe ctrlSetText "From Me";
_fromMe ctrlAddEventHandler ["ButtonClick", {["start", getPosATL vehicle player] call Iceman_fnc_route_setPoint}];

private _plan = ["BCE_RscButtonMenu", 9313, "route", [(_gap * 2) + _halfW, (_row * 3) + _gap, _halfW, _buttonH]] call _makeCtrl;
_plan ctrlSetText "Plan";
_plan ctrlAddEventHandler ["ButtonClick", {call Iceman_fnc_route_startNavigation}];

private _clear = ["BCE_RscButtonMenu", 9314, "route", [_gap, (_row * 4) + _gap, _fullW, _buttonH]] call _makeCtrl;
_clear ctrlSetText "Clear Route";
_clear ctrlAddEventHandler ["ButtonClick", {call Iceman_fnc_route_clear}];

private _addWp = ["BCE_RscButtonMenu", 9320, "waypoints", [_gap, (_row * 2) + _gap, _fullW, _buttonH]] call _makeCtrl;
_addWp ctrlSetText "Add Waypoint";
_addWp ctrlAddEventHandler ["ButtonClick", {["waypoint"] call Iceman_fnc_route_selectMode}];

private _removeWp = ["BCE_RscButtonMenu", 9321, "waypoints", [_gap, (_row * 3) + _gap, _halfW, _buttonH]] call _makeCtrl;
_removeWp ctrlSetText "Undo";
_removeWp ctrlAddEventHandler ["ButtonClick", {call Iceman_fnc_route_removeWaypoint}];

private _clearWp = ["BCE_RscButtonMenu", 9322, "waypoints", [(_gap * 2) + _halfW, (_row * 3) + _gap, _halfW, _buttonH]] call _makeCtrl;
_clearWp ctrlSetText "Clear";
_clearWp ctrlAddEventHandler ["ButtonClick", {call Iceman_fnc_route_clearWaypoints}];

private _planWp = ["BCE_RscButtonMenu", 9323, "waypoints", [_gap, (_row * 4) + _gap, _fullW, _buttonH]] call _makeCtrl;
_planWp ctrlSetText "Plan Via Waypoints";
_planWp ctrlAddEventHandler ["ButtonClick", {call Iceman_fnc_route_startNavigation}];

private _mode = ["RscStructuredText", 9330, "common", [_gap, (_row * 5) + _gap, _fullW, _row]] call _makeCtrl;
_mode ctrlSetStructuredText parseText "";

private _info = ["RscStructuredText", 9331, "common", [_gap, (_row * 6) + _gap, _fullW, _h - ((_row * 6) + (2 * _gap))]] call _makeCtrl;
_info ctrlSetStructuredText parseText "";

call Iceman_fnc_route_updatePanel;
