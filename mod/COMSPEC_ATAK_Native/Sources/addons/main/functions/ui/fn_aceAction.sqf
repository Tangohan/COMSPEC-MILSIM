/*
    ace_interact_menu_fnc_createAction avec le nom affiché dans la langue du téléphone (comspec_atak_native_fnc_tr).
    Mêmes paramètres (la fonction de modification, index 10, est enrobée) ; posé par mod/COMSPEC_ATAK_Native/tools/i18n.py wrap.
*/
private _a = +_this;
// Texte source gardé (nom d'action + icône) : la fonction de modification d'ACE, appelée à chaque affichage du menu,
// retraduit le nom dans la langue du moment (changement de langue en cours de partie). Celle passée en paramètre
// (index 10) est appelée ensuite et garde la main. La table porte cette fonction (du code) : localNamespace, propre à
// la machine et jamais synchronisé (même table pour le natif et connect).
private _src = localNamespace getVariable ["COMSPEC_ATAK_AceSrc", createHashMap];
localNamespace setVariable ["COMSPEC_ATAK_AceSrc", _src];
_src set [(_a select 0) + "|" + str (_a param [2, ""]), [_a select 1, _a param [10, {}]]];
_a set [1, (_a select 1) call comspec_atak_native_fnc_tr];
_a set [10, {
    params ["", "", "", "_ad"];
    ((localNamespace getVariable ["COMSPEC_ATAK_AceSrc", createHashMap]) getOrDefault [(_ad select 0) + "|" + str (_ad select 2), []]) params [["_txt", ""], ["_mod", {}]];
    if (_txt isEqualType "" && {_txt isNotEqualTo ""} && {!isNil "comspec_atak_native_fnc_tr"}) then { _ad set [1, _txt call comspec_atak_native_fnc_tr]; };
    if (_mod isEqualType {}) then { _this call _mod; };
}];
_a call ace_interact_menu_fnc_createAction
