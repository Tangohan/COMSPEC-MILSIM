/*
  Accueil ATAK : afficher ou masquer la grille IceMan (4660 / 21660)
  au chevron. On ne laisse jamais IceMan écrire une largeur nulle
  (le calage d’origine est verrouillé et ferme le jeu).
*/
params [["_disp", displayNull]];

if (isNull _disp) then {
    if (!isNil "comspec_overwatch_atak_athena_fnc_athena_phoneDisplay") then {
        _disp = [] call comspec_overwatch_atak_athena_fnc_athena_phoneDisplay;
    };
};
if (isNull _disp) exitWith {};

private _fncHide = {
    params ["_c"];
    if (isNull _c) exitWith {};
    _c ctrlShow false;
    _c ctrlEnable false;
};

private _fncShow = {
    params ["_c"];
    if (isNull _c) exitWith {};
    _c ctrlShow true;
    _c ctrlEnable true;
};

private _dispName = "";
if (!isNil "cTabIfOpen" && {cTabIfOpen isEqualType []} && {(count cTabIfOpen) > 1}) then {
    private _nm = cTabIfOpen select 1;
    if (_nm isEqualType "") then { _dispName = _nm; };
};

// IceMan referme le menu en passant la largeur à 0 → arrêt brutal.
// On force le calage « ouvert » et on masque seulement à l’écran.
if (_dispName isNotEqualTo "" && {!isNil "cTab_fnc_getSettings"} && {!isNil "cTab_fnc_setSettings"}) then {
    private _sm = [_dispName, "showMenu"] call cTab_fnc_getSettings;
    if (_sm isEqualType [] && {(count _sm) > 1} && {!(_sm select 1)}) then {
        _sm set [1, true];
        [_dispName, [["showMenu", _sm]], false, false] call cTab_fnc_setSettings;
    };
};

{
    private _c = _disp displayCtrl _x;
    if (!isNull _c) then {
        if (_c getVariable ["Anim_SwitchTool", false]) then { _c setVariable ["Anim_SwitchTool", false]; };
        if (_c getVariable ["Anim_ToggleMenu", false]) then { _c setVariable ["Anim_ToggleMenu", false]; };
        if (_c getVariable ["Anim_fadeIgnore", false]) then { _c setVariable ["Anim_fadeIgnore", false]; };
    };
} forEach [4660, 17000 + 4660];

private _pageName = "main";
if (_dispName isNotEqualTo "" && {!isNil "cTab_fnc_getSettings"}) then {
    private _sm2 = [_dispName, "showMenu"] call cTab_fnc_getSettings;
    if (_sm2 isEqualType []) then {
        private _p = _sm2 param [0, "main"];
        if (_p isEqualType "" && {_p isNotEqualTo ""}) then { _pageName = toLower _p; };
    };
};

private _sessTok = _dispName;
private _prevTok = missionNamespace getVariable ["COMSPEC_ATAK_IfaceTok", ""];
if (_sessTok isNotEqualTo "" && {_prevTok isNotEqualTo _sessTok}) then {
    missionNamespace setVariable ["COMSPEC_ATAK_IfaceTok", _sessTok, false];
    if (_prevTok isEqualTo "") then {
        missionNamespace setVariable ["COMSPEC_ATAK_DrawerWantOpen", false, false];
    };
};

private _drawer = missionNamespace getVariable ["COMSPEC_ATAK_DrawerWantOpen", false];
_drawer = (_drawer isEqualType true) && {_drawer};
uiNamespace setVariable ["COMSPEC_ATAK_DrawerOpen", _drawer];

private _isApp = (_pageName isNotEqualTo "main") && {_pageName isNotEqualTo ""};

private _menu = _disp displayCtrl 4660;
private _grid = _disp displayCtrl (17000 + 4660);
private _dock = _disp displayCtrl 46600;

if (_isApp || {_drawer}) then {
    [_menu] call _fncShow;
    [_grid] call _fncShow;
    [_dock] call _fncShow;
} else {
    [_menu] call _fncHide;
    [_grid] call _fncHide;
    [_dock] call _fncHide;
};

{
    private _btn = _disp displayCtrl _x;
    if (isNull _btn) then { continue };
    if (_btn getVariable ["COMSPEC_ATAK_MenuHook", false]) then { continue };
    _btn setVariable ["COMSPEC_ATAK_MenuHook", true];
    _btn ctrlSetEventHandler ["ButtonClick", "[] call comspec_overwatch_atak_athena_fnc_athena_toggleDrawer; true"];
} forEach [1607, 17000 + 1607];
