/*
  Chevron du téléphone : ouvrir / fermer le menu d’applications
  sans demander à IceMan de réduire la largeur à zéro (arrêt brutal).
*/
if (!hasInterface) exitWith { false };

private _last = missionNamespace getVariable ["COMSPEC_ATAK_ToggleAt", -99];
if ((diag_tickTime - _last) < 0.25) exitWith { false };
missionNamespace setVariable ["COMSPEC_ATAK_ToggleAt", diag_tickTime, false];

private _open = missionNamespace getVariable ["COMSPEC_ATAK_DrawerWantOpen", false];
if !(_open isEqualType true) then { _open = false; };
private _next = !_open;
missionNamespace setVariable ["COMSPEC_ATAK_DrawerWantOpen", _next, false];
uiNamespace setVariable ["COMSPEC_ATAK_DrawerOpen", _next];

private _disp = displayNull;
if (!isNil "comspec_overwatch_atak_athena_fnc_athena_phoneDisplay") then {
    _disp = [] call comspec_overwatch_atak_athena_fnc_athena_phoneDisplay;
};
if (!isNull _disp && {!isNil "comspec_overwatch_atak_athena_fnc_athena_enforceDrawer"}) then {
    [_disp] call comspec_overwatch_atak_athena_fnc_athena_enforceDrawer;
};
if (!isNil "comspec_overwatch_connect_fnc_log") then {
    ["INFO", "Menu", ["fermé", "ouvert"] select _next] call comspec_overwatch_connect_fnc_log;
};
true
