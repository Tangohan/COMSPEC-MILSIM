#include "..\script_component.hpp"

private _reports = missionNamespace getVariable ["Iceman_ATAK_Reports_reports", []];
private _selected = missionNamespace getVariable ["Iceman_ATAK_Reports_selected", -1];

if (_selected < 0 || {_selected >= count _reports}) exitWith {
    ["REPORTS", "No report selected.", 3] call cTab_fnc_addNotification;
    false
};

(_reports # _selected) params ["_time", "_kind", "_sender", "_grid", "_body", "_pos"];

private _display = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
if (isNull _display) exitWith {
    ["REPORTS", "ATAK display is not open.", 3] call cTab_fnc_addNotification;
    false
};

private _map = controlNull;
{
    private _candidate = _display displayCtrl _x;
    if (!isNull _candidate) exitWith {
        _map = _candidate;
    };
} forEach [1201, 1202];

if (isNull _map) exitWith {
    ["REPORTS", "ATAK map is not available.", 3] call cTab_fnc_addNotification;
    false
};

private _target = +_pos;
_target set [2, 0];

_map ctrlMapAnimAdd [0.5, ctrlMapScale _map, _target];
ctrlMapAnimCommit _map;

["REPORTS", format ["Located %1 at %2.", _kind, _grid], 3] call cTab_fnc_addNotification;
true
