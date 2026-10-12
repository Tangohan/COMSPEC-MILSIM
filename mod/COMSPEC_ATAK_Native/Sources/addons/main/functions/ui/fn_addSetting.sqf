/*
    CBA_fnc_addSetting dans la langue du téléphone (fn_tr) : titre, aide, catégorie et libellés d'une liste.
    La langue est lue au chargement (le réglage de langue est déclaré avant) ; en changer demande de relancer la
    mission pour que le menu CBA suive. Mêmes paramètres que CBA_fnc_addSetting.
*/
private _a = +_this;
_a set [2, (_a select 2) call comspec_atak_native_fnc_tr];
_a set [3, (_a select 3) call comspec_atak_native_fnc_tr];
if ((_a select 1) isEqualTo "LIST") then {
    private _v = +(_a select 4);
    _v set [1, (_v select 1) call comspec_atak_native_fnc_tr];
    _a set [4, _v];
};
_a call CBA_fnc_addSetting
