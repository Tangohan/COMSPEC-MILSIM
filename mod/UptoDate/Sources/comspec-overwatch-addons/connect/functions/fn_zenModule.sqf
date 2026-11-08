/*
    zen_custom_modules_fnc_register avec la catégorie et le nom du module Zeus dans la langue du téléphone
    (comspec_overwatch_connect_fnc_tr). Mêmes paramètres ; posé par mod/COMSPEC_ATAK_Native/tools/i18n.py wrap.
*/
private _a = +_this;
// Catégorie donnée par sa classe de CfgFactionClasses : même texte que les modules de config, un seul dossier dans la liste.
private _cfg = configFile >> "CfgFactionClasses" >> (_a select 0);
_a set [0, if (isClass _cfg) then { getText (_cfg >> "displayName") } else { (_a select 0) call comspec_overwatch_connect_fnc_tr }];
_a set [1, (_a select 1) call comspec_overwatch_connect_fnc_tr];
_a call zen_custom_modules_fnc_register
