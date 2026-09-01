/*
    Ouverture de l’app Athena dans cTab (pattern ATAK_APPs Opened).
    Le groupe PAGE_CTRL n’est pas toujours prêt au premier Opened (listes vides).
*/
params ["_group", ["_interfaceInit", false], "_isDialog", "_settings"];

if (!isNull _group && {((ctrlClassName _group) find "COMSPEC_ATAK_Athena") >= 0}) then {
    uiNamespace setVariable ["COMSPEC_ATAK_Athena_group", _group];
};

["athena"] call comspec_overwatch_atak_athena_fnc_athena_hideForeignPages;

private _token = diag_tickTime;
uiNamespace setVariable ["COMSPEC_ATAK_Athena_token", _token];

private _pending = missionNamespace getVariable ["COMSPEC_Athena_PendingTab", ""];
if (_pending isEqualType "" && {_pending isNotEqualTo ""}) then {
    missionNamespace setVariable ["COMSPEC_Athena_PanelTab", toLower _pending, false];
    missionNamespace setVariable ["COMSPEC_Athena_PendingTab", "", false];
};

private _paint = {
    private _g = [] call comspec_overwatch_atak_athena_fnc_athena_resolveAthenaGroup;
    if (isNull _g || {!ctrlShown _g}) exitWith {};
    private _page = toLower ((["cTab_Android_dlg", "showMenu"] call cTab_fnc_getSettings) param [0, ""]);
    if (_page isNotEqualTo "" && {_page isNotEqualTo "athena"}) exitWith {};
    [] call comspec_overwatch_atak_athena_fnc_athena_updatePanel;
};
[] call _paint;
{
    [_paint, [], _x] call CBA_fnc_waitAndExecute;
} forEach [0.08, 0.25, 0.7, 1.4];

[_token] spawn {
    params ["_token"];
    while { (uiNamespace getVariable ["COMSPEC_ATAK_Athena_token", -1]) isEqualTo _token } do {
        uiSleep 6;
        private _group = [] call comspec_overwatch_atak_athena_fnc_athena_resolveAthenaGroup;
        if (isNull _group || {!ctrlShown _group}) exitWith {};
        [] call comspec_overwatch_atak_athena_fnc_athena_updatePanel;
    };
};
