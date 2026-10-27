/*
    Langue du mod (réglage CBA COMSPEC « Langue / Language », en tête de la liste : English (par défaut), Français, les
    langues des mods de traduction chargés (fn_langList), puis Auto). Hors français, charge une fois le dictionnaire de la langue
    (tableau [["français", "traduction"], ...] ; anglais : i18n/en.txt, tenu par tools/i18n.py) par-dessus l'anglais :
    une phrase que le mod de traduction n'a pas (encore) traduite s'affiche en anglais. Prépare aussi les modèles à
    %1, %2… pour fn_tr. Français : charge i18n/fr.txt ([["texte écrit en anglais dans le code", "français"], ...]), pour
    les quelques écrans et messages dont le texte source est en anglais (Overwatch connect surtout).
    Params : [redessiner la page ouverte (changement en cours de partie), langue imposée].
*/
params [["_redraw", false], ["_force", ""]];
private _set = missionNamespace getVariable ["comspec_atak_native_language", ""];
// Au preInit, CBA n'a pas encore posé la valeur du réglage : on la lit dans les réglages client enregistrés par CBA
// (profil : ["#CBA_HASH#", clés, valeurs, défaut], valeur [valeur, priorité]), sinon l'anglais (valeur par défaut).
if (_set isEqualTo "") then {
    private _h = profileNamespace getVariable ["cba_settings_hash", []];
    if (_h isEqualType [] && {count _h >= 3} && {(_h select 1) isEqualType []}) then {
        private _i = (_h select 1) find "comspec_atak_native_language";
        if (_i >= 0) then {
            private _v = (_h select 2) param [_i, ""];
            if (_v isEqualType []) then { _v = _v param [0, ""]; };
            if (_v isEqualType "") then { _set = _v; };
        };
    };
};
if (_set isEqualTo "") then { _set = "en"; };
// _force : langue imposée (fn_trMenu charge le dictionnaire pour les menus CBA des autres addons).
if (_force isNotEqualTo "") then { _set = _force; };
private _lang = [_set] call comspec_atak_native_fnc_langResolve;
missionNamespace setVariable ["COMSPEC_ATAK_I18nCache", createHashMap];
missionNamespace setVariable ["COMSPEC_ATAK_I18nCacheNum", createHashMap];
private _loaded = missionNamespace getVariable ["COMSPEC_ATAK_I18nLoaded", ""];
// Dictionnaire déjà préparé pendant cette session de jeu (mission précédente) : gardé dans uiNamespace, il
// n'est pas relu (740 Ko, 10 000 paires, plusieurs centaines de ms à chaque arrivée en mission hors français).
if (_loaded isNotEqualTo _lang && {!isNil {uiNamespace getVariable ("COMSPEC_ATAK_I18nUi_" + _lang)}}) then {
    (uiNamespace getVariable ("COMSPEC_ATAK_I18nUi_" + _lang)) params ["_exactUi", "_tplsUi"];
    missionNamespace setVariable ["COMSPEC_ATAK_I18nExact", _exactUi];
    missionNamespace setVariable ["COMSPEC_ATAK_I18nTpl", _tplsUi];
    missionNamespace setVariable ["COMSPEC_ATAK_I18nLoaded", _lang];
    _loaded = _lang;
};
if (_loaded isNotEqualTo _lang) then {
    private _perfT0 = diag_tickTime;
    private _langs = [] call comspec_atak_native_fnc_langList;
    // Anglais d'abord, puis la langue choisie : ses traductions remplacent l'anglais phrase par phrase. Français :
    // seulement les textes écrits en anglais dans le code (fr.txt).
    private _chain = if (_lang isEqualTo "fr") then { [["fr", "Français", "\z\comspec_atak_native\addons\main\i18n\fr.txt", []]] } else {
        _langs select { ((_x select 0) isEqualTo "en") || {(_x select 0) isEqualTo _lang} }
    };
    private _merged = createHashMap;
    {
        private _raw = loadFile (_x select 2);
        private _arr = if (_raw isEqualTo "") then { [] } else { parseSimpleArray _raw };
        if !(_arr isEqualType []) then { _arr = []; };
        private _n = 0;
        {
            // Une ligne = ["français", "traduction"] ; une 3e colonne (rappel de l'anglais pour les traducteurs) est
            // ignorée, une traduction vide aussi (la phrase reste dans la langue de secours).
            if (_x isEqualType [] && {count _x >= 2} && {(_x select 0) isEqualType ""} && {(_x select 1) isEqualType ""} && {(_x select 1) isNotEqualTo ""}) then {
                _merged set [_x select 0, _x select 1];
                _n = _n + 1;
            };
        } forEach _arr;
        diag_log format ["[COMSPEC ATAK NATIVE][INFO][I18N] Dictionary %1 (%2): %3 entries", _x select 0, _x select 2, _n];
    } forEach _chain;
    private _pairs = _merged toArray false;
    private _exact = createHashMap;
    private _tpls = createHashMap;
    private _gen = toString [1];
    {
        _x params ["_fr", "_en"];
        if ((_fr find "%") < 0) then {
            _exact set [_fr, _en];
            private _up = toUpper _fr;
            if !(_up in _exact) then { _exact set [_up, toUpper _en]; };
        } else {
            // « il y a %1 s » -> morceaux ["il y a ", " s"], numéros [1].
            private _parts = [];
            private _ids = [];
            private _buf = "";
            private _i = 0;
            private _n = count _fr;
            while { _i < _n } do {
                private _c = _fr select [_i, 1];
                private _d = _fr select [_i + 1, 1];
                if (_c isEqualTo "%" && {_d in ["1", "2", "3", "4", "5", "6", "7", "8", "9"]}) then {
                    _parts pushBack _buf;
                    _ids pushBack (parseNumber _d);
                    _buf = "";
                    _i = _i + 2;
                } else {
                    // « %% » du modèle (format) : un seul « % » dans le texte affiché (« Batterie 99 % »).
                    if (_c isEqualTo "%" && {_d isEqualTo "%"}) then {
                        _buf = _buf + "%";
                        _i = _i + 2;
                    } else {
                        _buf = _buf + _c;
                        _i = _i + 1;
                    };
                };
            };
            _parts pushBack _buf;
            // « % » sans numéro (« 40 % de pertes ») : texte fixe, cherché tel quel.
            if (_ids isEqualTo []) then { _exact set [_fr, _en]; };
            // Deux valeurs collées (« %1%2 », souvent un complément facultatif comme « (carte) » ou « , rayon 3 m ») : la
            // première capture les deux et la seconde reste vide (64 modèles ignorés jusque-là, leur texte restait en
            // français). Modèle ignoré seulement si la seconde revient ailleurs (elle serait affichée deux fois).
            private _j = 1;
            while { _j < ((count _parts) - 1) } do {
                private _id = _ids select _j;
                if ((_parts select _j) isEqualTo "" && {({ _x isEqualTo _id } count _ids) isEqualTo 1}) then {
                    _parts deleteAt _j;
                    _ids deleteAt _j;
                } else { _j = _j + 1; };
            };
            private _ok = (count _ids) > 0 && {((_parts select [1, (count _parts) - 2]) findIf { _x isEqualTo "" }) < 0};
            if (_ok) then {
                private _p0 = _parts select 0;
                // Début de 3 caractères ou plus : rangé sous ce début. Sinon (« %1 m », « à %1 ») : rangé sous la fin
                // (3 derniers caractères, la fin du texte doit y correspondre), ou avec les modèles sans fin fixe.
                private _pz = _parts select ((count _parts) - 1);
                private _key = if ((count _p0) >= 3) then { _p0 select [0, 3] } else {
                    [_gen, _gen + (_pz select [(count _pz) - 3])] select ((count _pz) >= 3)
                };
                private _list = _tpls getOrDefault [_key, []];
                _list pushBack [_parts, _ids, _en];
                _tpls set [_key, _list];
                // Variante en capitales (titres de section mis en capitales : « CIBLES MARQUÉES : 0 »), comme pour les
                // textes exacts ; seulement avec un début fixe (rangée sous ce début) et sans balise dans la traduction.
                private _up = _parts apply { toUpper _x };
                if ((count _p0) >= 3 && {_up isNotEqualTo _parts} && {(_en find "<") < 0}) then {
                    private _uKey = (_up select 0) select [0, 3];
                    private _uList = _tpls getOrDefault [_uKey, []];
                    _uList pushBack [_up, _ids, toUpper _en];
                    _tpls set [_uKey, _uList];
                };
            };
        };
    } forEach _pairs;
    // Modèles sans début fixe rangés par fin : chaque liste « fin » reçoit aussi ceux sans fin fixe (fn_tr ne parcourt
    // ainsi qu'une cinquantaine de modèles au lieu de plus de deux cents pour un texte inconnu).
    private _noEnd = _tpls getOrDefault [_gen, []];
    { if ((count _x) > 1 && {(_x select [0, 1]) isEqualTo _gen}) then { _tpls set [_x, (_tpls get _x) + _noEnd]; }; } forEach (keys _tpls);
    // Modèles les plus longs d'abord : « Distance au point : %1 » avant « Distance : %1 ».
    { _tpls set [_x, [_tpls get _x, [], { -(count ((_x select 0) joinString "")) }] call BIS_fnc_sortBy]; } forEach (keys _tpls);
    missionNamespace setVariable ["COMSPEC_ATAK_I18nExact", _exact];
    missionNamespace setVariable ["COMSPEC_ATAK_I18nTpl", _tpls];
    missionNamespace setVariable ["COMSPEC_ATAK_I18nLoaded", _lang];
    uiNamespace setVariable ["COMSPEC_ATAK_I18nUi_" + _lang, [_exact, _tpls]];
    diag_log format ["[COMSPEC ATAK NATIVE][PERF] Dictionnaire %1 préparé : %2 entrées, %3 ms", _lang, count _pairs, round ((diag_tickTime - _perfT0) * 1000)];
};
missionNamespace setVariable ["COMSPEC_ATAK_Lang", _lang];
// Nombre de traductions chargées : 0 = fn_tr rend le texte tel quel, sans coût.
missionNamespace setVariable ["COMSPEC_ATAK_I18nN", count (missionNamespace getVariable ["COMSPEC_ATAK_I18nExact", createHashMap])];
// Changement en cours de partie : les menus CBA COMSPEC suivent (fn_menuRelang ne fait rien si la langue est la même).
if (_redraw) then { [] call comspec_atak_native_fnc_menuRelang; };
// Noms des touches COMSPEC (Contrôles > Configurer les addons) dans la nouvelle langue.
if (_redraw) then { [] call comspec_atak_native_fnc_keyRelang; };
if (_redraw && {hasInterface}) then {
    private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
    private _page = _s getOrDefault ["activePage", ""];
    if (_page isNotEqualTo "" && {!isNull ([] call comspec_atak_native_fnc_display)}) then {
        [{ [_this, false] call comspec_atak_native_fnc_navigate; }, _page] call CBA_fnc_execNextFrame;
    };
};
_lang
