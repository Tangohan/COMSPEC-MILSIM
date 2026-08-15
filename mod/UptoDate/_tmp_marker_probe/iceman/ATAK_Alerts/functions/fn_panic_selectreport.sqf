#include "..\script_component.hpp"

params ["_listCtrl", "_row"];

if (_listCtrl getVariable ["IcemanPanicUpdating", false]) exitWith {};
if (_row < 0) exitWith {};

private _index = parseNumber (_listCtrl lbData _row);
private _panics = missionNamespace getVariable ["Iceman_ATAK_Panic_reports", []];
if (_index < 0 || {_index >= count _panics}) exitWith {};

Iceman_ATAK_Panic_selected = _index;
call Iceman_fnc_panic_updatePanel;
