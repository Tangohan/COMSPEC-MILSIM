/*
    Filtre du journal Athena (liste déroulante).
*/
params ["_ctrl", "_idx"];

if (_ctrl getVariable ["COMSPEC_AthenaFilterUpdating", false]) exitWith {};
if (_idx < 0) exitWith {};

private _tab = _ctrl lbData _idx;
if (_tab isEqualTo "") exitWith {};

missionNamespace setVariable ["COMSPEC_Athena_FilterFromCombo", true, false];
[_tab] call comspec_overwatch_atak_athena_fnc_athena_selectTab;
missionNamespace setVariable ["COMSPEC_Athena_FilterFromCombo", false, false];
