#include "..\script_component.hpp"

private _group = uiNamespace getVariable ["Iceman_ATAK_BDA_group", controlNull];
private _controls = [];

if (!isNull _group) then {
    _controls = allControls _group;
} else {
    private _display = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
    if (isNull _display) exitWith {};
    _controls = allControls _display;
};

private _statusCtrl = controlNull;
{
    if (ctrlIDC _x == 9726) exitWith {
        _statusCtrl = _x;
    };
} forEach _controls;

if (isNull _statusCtrl) exitWith {};

private _reports = missionNamespace getVariable ["Iceman_ATAK_Reports_reports", []];
private _bdaCount = {_x param [1, ""] == "BDA REPORT"} count _reports;

_statusCtrl ctrlSetStructuredText parseText ([
    "Fill the BDA fields, then Send BDA.",
    "Recipients with ATAK receive the full report in Reports.",
    format ["BDA reports in local inbox: %1", _bdaCount]
] joinString "<br/>");
