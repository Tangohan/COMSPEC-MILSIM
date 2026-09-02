/*
    Filtre du journal Athena (liste déroulante).
    Sur l’écran Journal : filtre du journal de session.
    Ailleurs : filtre de l’inbox opérationnelle.
*/
params ["_ctrl", "_idx"];

if (_ctrl getVariable ["COMSPEC_AthenaFilterUpdating", false]) exitWith {};
if (_idx < 0) exitWith {};

private _tab = _ctrl lbData _idx;
if (_tab isEqualTo "") exitWith {};

private _home = missionNamespace getVariable ["COMSPEC_Athena_HomeSection", "fil"];
if (_home isEqualTo "fil") then {
    if !(_tab in ["all", "error", "tx", "medical", "photo"]) then { _tab = "all"; };
    missionNamespace setVariable ["COMSPEC_Athena_LogFilter", _tab, false];
    [] call comspec_overwatch_atak_athena_fnc_athena_updatePanel;
} else {
    missionNamespace setVariable ["COMSPEC_Athena_FilterFromCombo", true, false];
    [_tab] call comspec_overwatch_atak_athena_fnc_athena_selectTab;
    missionNamespace setVariable ["COMSPEC_Athena_FilterFromCombo", false, false];
};
