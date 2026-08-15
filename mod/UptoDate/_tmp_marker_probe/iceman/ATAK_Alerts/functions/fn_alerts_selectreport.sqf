#include "..\script_component.hpp"

params ["_listCtrl", "_row"];

if (_listCtrl getVariable ["IcemanReportsUpdating", false]) exitWith {};
if (_row < 0) exitWith {};

private _data = _listCtrl lbData _row;
private _index = parseNumber _data;
private _reports = missionNamespace getVariable ["Iceman_ATAK_Reports_reports", []];
if (_index < 0 || {_index >= count _reports}) exitWith {};

Iceman_ATAK_Reports_selected = _index;
call Iceman_fnc_alerts_updatePanel;
