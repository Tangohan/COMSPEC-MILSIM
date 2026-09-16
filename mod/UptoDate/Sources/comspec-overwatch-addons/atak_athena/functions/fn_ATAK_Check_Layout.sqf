/*
  COMSPEC : remplace le calage BCE (ressort + largeur nulle = arrêt brutal).
  On coupe seulement l’animation et on masque la grille d’accueil.
  Aucun recadrage de carte ni de menu.
*/

private _disp = displayNull;
if (!isNil "_display" && {_display isEqualType displayNull}) then { _disp = _display; };
if (isNull _disp && {!isNil "comspec_overwatch_atak_athena_fnc_athena_phoneDisplay"}) then {
    _disp = [] call comspec_overwatch_atak_athena_fnc_athena_phoneDisplay;
};
if (isNull _disp) exitWith {};
if (isNil "cTabIfOpen") exitWith {};

private _fsAlert = missionNamespace getVariable ["COMSPEC_Athena_FsAlert", []];
if ((_fsAlert isEqualType []) && {(count _fsAlert) >= 3} && {diag_tickTime <= (_fsAlert select 2)}) exitWith {
    if (!isNil "comspec_overwatch_atak_athena_fnc_athena_paintFullscreenAlert") then {
        [] call comspec_overwatch_atak_athena_fnc_athena_paintFullscreenAlert;
    };
};

private _bgGroup = controlNull;
if (!isNil "_backgroundGroup" && {_backgroundGroup isEqualType controlNull}) then {
    _bgGroup = _backgroundGroup;
};
if (isNull _bgGroup) then { _bgGroup = _disp displayCtrl 4660; };
if (!isNull _bgGroup) then {
    if (_bgGroup getVariable ["Anim_SwitchTool", false]) then { _bgGroup setVariable ["Anim_SwitchTool", false]; };
    if (_bgGroup getVariable ["Anim_ToggleMenu", false]) then { _bgGroup setVariable ["Anim_ToggleMenu", false]; };
    if (_bgGroup getVariable ["Anim_fadeIgnore", false]) then { _bgGroup setVariable ["Anim_fadeIgnore", false]; };
};

if (!isNil "comspec_overwatch_atak_athena_fnc_athena_enforceDrawer") then {
    [_disp] call comspec_overwatch_atak_athena_fnc_athena_enforceDrawer;
};
// HUD carte : athena_updateMapHud (PFH), pas de recadrage depuis le calage.
