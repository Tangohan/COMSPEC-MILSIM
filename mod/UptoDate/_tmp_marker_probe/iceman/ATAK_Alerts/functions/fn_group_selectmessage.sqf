#include "..\script_component.hpp"

params ["_listCtrl", "_row"];

if (_listCtrl getVariable ["IcemanGroupUpdating", false]) exitWith {};
if (_row < 0) exitWith {};

private _index = parseNumber (_listCtrl lbData _row);
private _messages = missionNamespace getVariable ["Iceman_ATAK_Group_messages", []];
if (_index < 0 || {_index >= count _messages}) exitWith {};

Iceman_ATAK_Group_selected = _index;
call Iceman_fnc_group_updatePanel;
