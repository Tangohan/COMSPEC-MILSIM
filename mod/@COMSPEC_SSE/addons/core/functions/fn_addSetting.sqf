/*
    CBA_fnc_addSetting avec titre, aide, catégorie et libellés d'une liste dans la langue du joueur, si l'ATAK natif
    est chargé (comspec_atak_native_fnc_trMenu). Mêmes paramètres ; posé par mod/COMSPEC_ATAK_Native/tools/i18n.py wrap.
*/
if (isNil "comspec_atak_native_fnc_trMenu") exitWith { _this call CBA_fnc_addSetting };
private _a = +_this;
private _tr = {
    if (_this isEqualType []) exitWith { _this apply { _x call comspec_atak_native_fnc_trMenu } };
    _this call comspec_atak_native_fnc_trMenu
};
_a set [2, (_a select 2) call _tr];
_a set [3, (_a select 3) call _tr];
if ((_a select 1) isEqualTo "LIST") then {
    private _v = +(_a select 4);
    _v set [1, (_v select 1) call _tr];
    _a set [4, _v];
};
_a call CBA_fnc_addSetting
