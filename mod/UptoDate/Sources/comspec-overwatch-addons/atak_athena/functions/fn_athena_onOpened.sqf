/*
    Ouverture de l’app Athena dans cTab (pattern ATAK_APPs Opened).
*/
params ["_group", ["_interfaceInit", false], "_isDialog", "_settings"];

if (isNull _group) exitWith {};

uiNamespace setVariable ["COMSPEC_ATAK_Athena_group", _group];
private _token = diag_tickTime;
uiNamespace setVariable ["COMSPEC_ATAK_Athena_token", _token];

private _pending = missionNamespace getVariable ["COMSPEC_Athena_PendingTab", ""];
if (_pending isEqualType "" && {_pending isNotEqualTo ""}) then {
    missionNamespace setVariable ["COMSPEC_Athena_PanelTab", toLower _pending, false];
    missionNamespace setVariable ["COMSPEC_Athena_PendingTab", "", false];
};

[] call comspec_overwatch_atak_athena_fnc_athena_updatePanel;

[_token] spawn {
    params ["_token"];
    while { (uiNamespace getVariable ["COMSPEC_ATAK_Athena_token", -1]) isEqualTo _token } do {
        uiSleep 6;
        private _group = uiNamespace getVariable ["COMSPEC_ATAK_Athena_group", controlNull];
        if (isNull _group || {!ctrlShown _group}) exitWith {};
        [] call comspec_overwatch_atak_athena_fnc_athena_updatePanel;
    };
};
