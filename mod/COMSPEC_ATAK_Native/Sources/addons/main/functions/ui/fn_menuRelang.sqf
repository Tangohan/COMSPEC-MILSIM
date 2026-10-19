/*
    Menus CBA COMSPEC dans la nouvelle langue, sans relancer la mission (changement de « Langue / Language »).
    Les réglages COMSPEC (ATAK natif, Overwatch, SSE) ont été déclarés traduits par fn_addSetting / fn_trMenu, qui
    gardent chaque traduction et son texte français (COMSPEC_ATAK_MenuFr) : on repart du français et on retraduit le
    titre, l'aide, la section et les libellés de liste rangés par CBA (cba_settings_default). CBA reconstruit le menu à
    chaque ouverture : la nouvelle langue y apparaît dès la suivante.
*/
private _lang = missionNamespace getVariable ["COMSPEC_ATAK_Lang", "fr"];
if ((missionNamespace getVariable ["COMSPEC_ATAK_MenuLang", ""]) isEqualTo _lang) exitWith {};
if (isNil "cba_settings_default" || {isNil "cba_settings_allSettings"}) exitWith {};
missionNamespace setVariable ["COMSPEC_ATAK_MenuLang", _lang];
private _fr = uiNamespace getVariable ["COMSPEC_ATAK_MenuFr", createHashMap];
private _tr = {
    if !(_this isEqualType "") exitWith { _this };
    private _base = _fr getOrDefault [_this, _this];
    // Français aussi : les textes écrits en anglais dans le code passent par fr.txt.
    private _r = _base call comspec_atak_native_fnc_tr;
    if (_r isNotEqualTo _base) then { _fr set [_r, _base]; };
    _r
};
{
    private _info = cba_settings_default getVariable _x;
    if (!isNil "_info" && {(_info param [4, ""]) isEqualTo "COMSPEC"} && {_x isNotEqualTo "comspec_atak_native_language"}) then {
        _info set [5, (_info select 5) call _tr];
        _info set [6, (_info select 6) call _tr];
        _info set [9, (_info select 9) call _tr];
        if ((_info select 2) isEqualTo "LIST") then {
            private _data = _info select 3;
            _data set [1, (_data select 1) apply { _x call _tr }];
        };
    };
} forEach cba_settings_allSettings;
// Index du menu (recherche, catégories, sous-catégories) gardés par CBA depuis sa dernière ouverture : avec les anciens
// noms de section, des réglages restaient sous un titre de l'ancienne langue. CBA les reconstruit à la prochaine ouverture.
cba_settings_searchIndex = nil;
cba_settings_categorySettings = nil;
cba_settings_subCategories = nil;
