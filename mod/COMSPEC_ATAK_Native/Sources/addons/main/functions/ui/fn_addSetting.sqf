/*
    CBA_fnc_addSetting dans la langue du mod (fn_tr) : titre, aide, catégorie et libellés d'une liste.
    La langue est lue au chargement (le réglage de langue est déclaré avant) ; chaque traduction garde son texte
    français (COMSPEC_ATAK_MenuFr) pour que fn_menuRelang retraduise le menu si la langue change en cours de partie.
    Mêmes paramètres que CBA_fnc_addSetting.
*/
private _a = +_this;
private _fr = uiNamespace getVariable ["COMSPEC_ATAK_MenuFr", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_MenuFr", _fr];
private _tr = {
    if (_this isEqualType []) exitWith { _this apply { _x call _tr } };
    private _r = _this call comspec_atak_native_fnc_tr;
    if (_r isEqualType "" && {_r isNotEqualTo _this}) then { _fr set [_r, _this]; };
    _r
};
_a set [2, (_a select 2) call _tr];
_a set [3, (_a select 3) call _tr];
if ((_a select 1) isEqualTo "LIST") then {
    private _v = +(_a select 4);
    _v set [1, (_v select 1) call _tr];
    _a set [4, _v];
};
_a call CBA_fnc_addSetting
