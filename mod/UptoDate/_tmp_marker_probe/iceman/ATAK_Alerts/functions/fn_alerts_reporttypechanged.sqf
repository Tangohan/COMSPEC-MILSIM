#include "..\script_component.hpp"

params ["_combo", "_row"];

if (_combo getVariable ["IcemanReportsTypeUpdating", false]) exitWith {};
if (_row < 0) exitWith {};

private _form = _combo lbData _row;
if (_form == "") exitWith {};

Iceman_ATAK_Reports_form = _form;
call Iceman_fnc_alerts_updatePanel;
