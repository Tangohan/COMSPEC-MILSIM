#include "..\script_component.hpp"

params ["_group"];

if (isNull _group) exitWith {};

{
    if (_x getVariable ["IcemanJumpCtrl", false]) then {
        ctrlDelete _x;
    } else {
        _x ctrlShow false;
        _x ctrlEnable false;
    };
} forEach allControls _group;

private _pos = ctrlPosition _group;
private _w = _pos # 2;
private _h = _pos # 3;
private _row = _h / 11;
private _gap = _row * 0.08;
private _buttonH = _row * 0.82;
private _halfW = (_w - (3 * _gap)) / 2;
private _fullW = _w - (2 * _gap);

private _makeCtrl = {
    params ["_class", "_idc", "_section", "_rect"];
    private _ctrl = (ctrlParent _group) ctrlCreate [_class, _idc, _group];
    _ctrl setVariable ["IcemanJumpCtrl", true];
    _ctrl setVariable ["IcemanJumpSection", _section];
    _ctrl ctrlSetPosition _rect;
    _ctrl ctrlCommit 0;
    _ctrl
};

private _title = ["RscStructuredText", 9400, "common", [0, 0, _w, _row]] call _makeCtrl;
_title ctrlSetStructuredText parseText "<t align='center' size='1.05'>Jump Planner</t>";

private _tabPlan = ["BCE_RscButtonMenu", 9401, "common", [_gap, _row + _gap, _halfW, _buttonH]] call _makeCtrl;
_tabPlan ctrlSetText "Plan";
_tabPlan ctrlAddEventHandler ["ButtonClick", {["plan"] call Iceman_fnc_jump_selectTab}];

private _tabWp = ["BCE_RscButtonMenu", 9402, "common", [(_gap * 2) + _halfW, _row + _gap, _halfW, _buttonH]] call _makeCtrl;
_tabWp ctrlSetText "Waypoints";
_tabWp ctrlAddEventHandler ["ButtonClick", {["waypoints"] call Iceman_fnc_jump_selectTab}];

private _haho = ["BCE_RscButtonMenu", 9410, "plan", [_gap, (_row * 2) + _gap, _halfW, _buttonH]] call _makeCtrl;
_haho ctrlSetText "HAHO";
_haho ctrlAddEventHandler ["ButtonClick", {["HAHO"] call Iceman_fnc_jump_setMode}];

private _halo = ["BCE_RscButtonMenu", 9411, "plan", [(_gap * 2) + _halfW, (_row * 2) + _gap, _halfW, _buttonH]] call _makeCtrl;
_halo ctrlSetText "HALO";
_halo ctrlAddEventHandler ["ButtonClick", {["HALO"] call Iceman_fnc_jump_setMode}];

private _setJump = ["BCE_RscButtonMenu", 9412, "plan", [_gap, (_row * 3) + _gap, _halfW, _buttonH]] call _makeCtrl;
_setJump ctrlSetText "Set Jump Point";
_setJump ctrlAddEventHandler ["ButtonClick", {["jumpPoint"] call Iceman_fnc_jump_selectMode}];

private _setDz = ["BCE_RscButtonMenu", 9413, "plan", [(_gap * 2) + _halfW, (_row * 3) + _gap, _halfW, _buttonH]] call _makeCtrl;
_setDz ctrlSetText "Set Drop Zone";
_setDz ctrlAddEventHandler ["ButtonClick", {["dropZone"] call Iceman_fnc_jump_selectMode}];

private _fromMe = ["BCE_RscButtonMenu", 9414, "plan", [_gap, (_row * 4) + _gap, _halfW, _buttonH]] call _makeCtrl;
_fromMe ctrlSetText "JP Here";
_fromMe ctrlAddEventHandler ["ButtonClick", {["jumpPoint", getPosATL vehicle player] call Iceman_fnc_jump_setPoint}];

private _plan = ["BCE_RscButtonMenu", 9415, "plan", [(_gap * 2) + _halfW, (_row * 4) + _gap, _halfW, _buttonH]] call _makeCtrl;
_plan ctrlSetText "Plan Jump";
_plan ctrlAddEventHandler ["ButtonClick", {call Iceman_fnc_jump_plan}];

private _clear = ["BCE_RscButtonMenu", 9416, "plan", [_gap, (_row * 5) + _gap, _fullW, _buttonH]] call _makeCtrl;
_clear ctrlSetText "Clear Plan";
_clear ctrlAddEventHandler ["ButtonClick", {call Iceman_fnc_jump_clear}];

private _addWp = ["BCE_RscButtonMenu", 9420, "waypoints", [_gap, (_row * 2) + _gap, _fullW, _buttonH]] call _makeCtrl;
_addWp ctrlSetText "Add Via Point";
_addWp ctrlAddEventHandler ["ButtonClick", {["waypoint"] call Iceman_fnc_jump_selectMode}];

private _removeWp = ["BCE_RscButtonMenu", 9421, "waypoints", [_gap, (_row * 3) + _gap, _halfW, _buttonH]] call _makeCtrl;
_removeWp ctrlSetText "Undo";
_removeWp ctrlAddEventHandler ["ButtonClick", {call Iceman_fnc_jump_removeWaypoint}];

private _clearWp = ["BCE_RscButtonMenu", 9422, "waypoints", [(_gap * 2) + _halfW, (_row * 3) + _gap, _halfW, _buttonH]] call _makeCtrl;
_clearWp ctrlSetText "Clear";
_clearWp ctrlAddEventHandler ["ButtonClick", {call Iceman_fnc_jump_clearWaypoints}];

private _planWp = ["BCE_RscButtonMenu", 9423, "waypoints", [_gap, (_row * 4) + _gap, _fullW, _buttonH]] call _makeCtrl;
_planWp ctrlSetText "Plan Via Points";
_planWp ctrlAddEventHandler ["ButtonClick", {call Iceman_fnc_jump_plan}];

private _mode = ["RscStructuredText", 9430, "common", [_gap, (_row * 6) + _gap, _fullW, _row]] call _makeCtrl;
_mode ctrlSetStructuredText parseText "";

private _info = ["RscStructuredText", 9431, "common", [_gap, (_row * 7) + _gap, _fullW, _h - ((_row * 7) + (2 * _gap))]] call _makeCtrl;
_info ctrlSetStructuredText parseText "";

call Iceman_fnc_jump_updatePanel;
