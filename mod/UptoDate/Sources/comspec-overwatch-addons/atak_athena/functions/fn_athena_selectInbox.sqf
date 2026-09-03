/*
    Sélection d’une ligne de l’inbox Athena (cTab).
*/
params ["_control", "_lbCurSel"];

if (_control getVariable ["COMSPEC_AthenaInboxUpdating", false]) exitWith {};
if (_lbCurSel < 0) exitWith {};

private _entries = _control getVariable ["COMSPEC_Athena_Entries", []];
if (_lbCurSel >= count _entries) exitWith {};

(_entries select _lbCurSel) params ["", "", ["_detail", "", [""]]];

private _group = uiNamespace getVariable ["COMSPEC_ATAK_Athena_group", controlNull];
if (isNull _group) exitWith {};
private _detailCtrl = [_group, 9711] call comspec_overwatch_atak_athena_fnc_athena_pageCtrl;
if (!isNull _detailCtrl) then {
    _detailCtrl ctrlSetStructuredText parseText _detail;
};
