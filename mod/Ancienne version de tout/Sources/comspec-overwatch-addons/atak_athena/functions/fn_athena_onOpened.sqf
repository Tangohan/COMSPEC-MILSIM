/*
    Ouverture de l’app Athena dans cTab (pattern ATAK_APPs Opened).
*/
params ["_group", ["_interfaceInit", false], "_isDialog", "_settings"];

if (isNull _group) exitWith {};

uiNamespace setVariable ["COMSPEC_ATAK_Athena_group", _group];
private _token = diag_tickTime;
uiNamespace setVariable ["COMSPEC_ATAK_Athena_token", _token];

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
