/*
    Ouverture de l’app Paramètres dans cTab.
    Peint tout de suite, puis réessaie : le groupe PAGE_CTRL n’est pas toujours
    prêt au premier Opened (page bleue vide).
*/
params ["_group", ["_interfaceInit", false], "_isDialog", "_settings"];

if (!isNull _group) then {
    uiNamespace setVariable ["COMSPEC_ATAK_Settings_group", _group];
};

["settings"] call comspec_overwatch_atak_athena_fnc_athena_hideForeignPages;

private _paint = {
    private _page = toLower ((["cTab_Android_dlg", "showMenu"] call cTab_fnc_getSettings) param [0, ""]);
    if (_page isNotEqualTo "" && {!(_page in ["ataksettings", "settings"])}) exitWith {};
    [] call comspec_overwatch_atak_athena_fnc_athena_updateSettings;
};

[] call _paint;

if (!isNil "comspec_overwatch_connect_fnc_getFireTeams") then {
    0 spawn {
        [] call comspec_overwatch_connect_fnc_getFireTeams;
        [] call comspec_overwatch_atak_athena_fnc_athena_updateSettings;
    };
};

{
    [_paint, [], _x] call CBA_fnc_waitAndExecute;
} forEach [0.08, 0.25, 0.7, 1.4];
