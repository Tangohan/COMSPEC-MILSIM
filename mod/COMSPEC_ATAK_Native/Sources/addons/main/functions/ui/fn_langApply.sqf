/*
    Langue du téléphone (réglage CBA « Langue du téléphone / Phone language » : Auto, Français, English).
    Auto suit la langue du jeu (français si Arma est en français, anglais sinon). En anglais, charge une fois le
    dictionnaire i18n/en.txt (tableau [["français", "anglais"], ...], tenu par tools/i18n.py) et prépare les modèles
    à %1, %2… pour fn_tr. Params : [redessiner la page ouverte (changement en cours de partie), langue imposée].
*/
params [["_redraw", false], ["_force", ""]];
private _set = missionNamespace getVariable ["comspec_atak_native_language", "fr"];
// _force : langue imposée (fn_trMenu charge le dictionnaire pour les menus CBA des autres addons).
if (_force isNotEqualTo "") then { _set = _force; };
private _lang = switch (_set) do {
    case "en": { "en" };
    case "auto": { ["en", "fr"] select (language isEqualTo "French") };
    default { "fr" };
};
missionNamespace setVariable ["COMSPEC_ATAK_I18nCache", createHashMap];
if (_lang isEqualTo "en" && {isNil {missionNamespace getVariable "COMSPEC_ATAK_I18nExact"}}) then {
    private _pairs = parseSimpleArray (loadFile "\z\comspec_atak_native\addons\main\i18n\en.txt");
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
                    _buf = _buf + _c;
                    _i = _i + 1;
                };
            };
            _parts pushBack _buf;
            // Deux valeurs collées (« %1%2 ») : impossible à séparer, modèle ignoré.
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
    diag_log format ["[COMSPEC ATAK NATIVE][INFO][I18N] English dictionary: %1 entries", count _pairs];
};
missionNamespace setVariable ["COMSPEC_ATAK_Lang", _lang];
if (_redraw && {hasInterface}) then {
    private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
    private _page = _s getOrDefault ["activePage", ""];
    if (_page isNotEqualTo "" && {!isNull ([] call comspec_atak_native_fnc_display)}) then {
        [{ [_this, false] call comspec_atak_native_fnc_navigate; }, _page] call CBA_fnc_execNextFrame;
    };
};
_lang
