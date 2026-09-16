/*
  Accueil ATAK : masquer la grille IceMan (4660 / 17000+4660) tant que
  le chevron n’a pas demandé l’ouverture. Aucun recadrage : IceMan pose
  les cadres. Recadrer ici provoque l’arrêt brutal (AutoArray).
*/
params [["_disp", displayNull]];

if (isNull _disp) then {
    if (!isNil "comspec_overwatch_atak_athena_fnc_athena_phoneDisplay") then {
        _disp = [] call comspec_overwatch_atak_athena_fnc_athena_phoneDisplay;
    };
};
if (isNull _disp) then {
    _disp = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
};
if (isNull _disp) exitWith {};

private _fncHide = {
    params ["_c"];
    if (isNull _c) exitWith {};
    _c ctrlShow false;
    _c ctrlEnable false;
};

private _pageName = "main";
if (!isNil "cTab_fnc_getSettings") then {
    private _sm = ["cTab_Android_dlg", "showMenu"] call cTab_fnc_getSettings;
    if (_sm isEqualType []) then {
        private _p = _sm param [0, "main"];
        if (_p isEqualType "" && {_p isNotEqualTo ""}) then { _pageName = toLower _p; };
    };
};

private _sessTok = "";
if (!isNil "cTabIfOpen" && {cTabIfOpen isEqualType []} && {(count cTabIfOpen) > 1}) then {
    private _nm = cTabIfOpen select 1;
    if (_nm isEqualType "") then { _sessTok = _nm; };
};
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

if (!_isApp && {!_drawer}) then {
    [_menu] call _fncHide;
    [_grid] call _fncHide;
    [_dock] call _fncHide;
};
