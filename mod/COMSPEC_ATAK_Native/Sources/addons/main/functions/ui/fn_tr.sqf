/*
    Traduction à l'affichage. Le code reste écrit en français ; chaque texte montré à l'écran (ctrlSetText,
    ctrlSetStructuredText, ctrlSetTooltip, lbAdd, hint… passés par tools/i18n.py wrap) arrive ici.
    Langue française : rendu tel quel, sans coût. Langue anglaise : le texte est découpé autour des balises
    <t …>, des retours à la ligne, des « · » et des « Libellé : valeur », puis chaque morceau est cherché dans le dictionnaire (i18n/en.txt,
    chargé par fn_langApply) : texte exact, texte en capitales, puis modèle avec %1, %2… (« il y a %1 s » ->
    « %1 s ago »), les valeurs capturées étant elles-mêmes traduites. Un texte inconnu reste en français.
    Accepte aussi un tableau (lbSetText, lnbAddRow) : chaque chaîne est traduite. Résultats mis en cache.
*/
private _v = _this;
if (_v isEqualType []) exitWith { _v apply { _x call comspec_atak_native_fnc_tr } };
// Police autre que Barlow Condensed choisie (Réglages > Police) : le texte structuré passe dans cette police (fn_fontText).
if ((missionNamespace getVariable ["COMSPEC_ATAK_FontAlt", false]) && {_v isEqualType ""} && {((_v find "<t") >= 0) || {(_v find "<br") >= 0}}) then {
    _v = _v call comspec_atak_native_fnc_fontText;
};
if (!(_v isEqualType "") || {_v isEqualTo ""} || {(missionNamespace getVariable ["COMSPEC_ATAK_Lang", "fr"]) isEqualTo "fr"}) exitWith { _v };
private _cache = missionNamespace getVariable ["COMSPEC_ATAK_I18nCache", createHashMap];
private _hit = _cache getOrDefault [_v, nil];
if (!isNil "_hit") exitWith { _hit };
// Chemin d'image ou de son (dont les images téléchargées par la DLL, C:/…/x.jpg) et texture procédurale (#(argb,…)r2t(…),
// image vidéo) : jamais traduits.
if ((_v select [0, 1]) isEqualTo "\" || {(_v select [0, 2]) isEqualTo "#("} || {(_v find ".paa") > 0} || {(_v select [1, 2]) isEqualTo ":/"} || {(count _v) > 4 && {((toLower _v) select [(count _v) - 4]) in [".jpg", "jpeg", ".png", ".ogg"]}}) exitWith { _cache set [_v, _v]; _v };

private _exact = missionNamespace getVariable ["COMSPEC_ATAK_I18nExact", createHashMap];
private _tpls = missionNamespace getVariable ["COMSPEC_ATAK_I18nTpl", createHashMap];
private _nl = toString [10];
// Modèles sans début fixe : rangés par fn_langApply sous ce repère + leur fin (3 derniers caractères).
private _gen = toString [1];

// Modèle « … %1 … » : renvoie la traduction ou "".
private _tplMatch = {
    params ["_core", "_depth"];
    private _res = "";
    private _n = count _core;
    {
        _x params ["_parts", "_ids", "_en"];
        private _p0 = _parts select 0;
        if ((_core select [0, count _p0]) isEqualTo _p0) then {
            private _pos = count _p0;
            private _caps = [];
            private _last = (count _parts) - 1;
            for "_i" from 1 to _last do {
                if (_pos < 0) exitWith {};
                private _pi = _parts select _i;
                private _at = -1;
                if (_i isEqualTo _last) then {
                    private _st = _n - (count _pi);
                    if (_st > _pos && {(_core select [_st]) isEqualTo _pi}) then { _at = _st; };
                } else {
                    private _f = (_core select [_pos]) find _pi;
                    if (_f > 0) then { _at = _pos + _f; };
                };
                if (_at < 0) then { _pos = -1; } else {
                    _caps pushBack (_core select [_pos, _at - _pos]);
                    _pos = _at + (count _pi);
                };
            };
            if (_pos >= 0) then {
                private _args = [];
                { _args set [_x - 1, [(_caps select _forEachIndex), _depth + 1] call _seg]; } forEach _ids;
                _args = _args apply { [_x, ""] select (isNil "_x") };
                _res = format ([_en] + _args);
            };
        };
        if (_res isNotEqualTo "") exitWith {};
    } forEach ((_tpls getOrDefault [_core select [0, 3], []]) + (_tpls getOrDefault [_gen + (_core select [(_n - 3) max 0]), _tpls getOrDefault [_gen, []]]));
    _res
};

// Un morceau de texte sans balise : espaces autour gardés, cœur traduit.
private _seg = {
    params ["_text", ["_depth", 0]];
    private _core = trim _text;
    if (_core isEqualTo "" || {_depth > 2}) exitWith { _text };
    private _r = _exact getOrDefault [_core, ""];
    // « Libellé : valeur » assemblé dans le code : libellé traduit, ponctuation anglaise.
    if (_r isEqualTo "" && {(_core find " : ") > 0}) then {
        private _k = _core find " : ";
        private _lbl = _core select [0, _k];
        private _lblEn = _exact getOrDefault [_lbl, _lbl];
        if (_lblEn isNotEqualTo _lbl) then { _r = _lblEn + ": " + ([_core select [_k + 3], _depth + 1] call _seg); };
    };
    // Sans lettre (nombres, heures, grilles, ponctuation) : rien à traduire, on évite le parcours des modèles
    // (valeurs capturées surtout : le texte du curseur de carte change à chaque mouvement de souris).
    if (_r isEqualTo "" && {(toLower _core) isEqualTo (toUpper _core)}) exitWith { _text };
    if (_r isEqualTo "") then { _r = [_core, _depth] call _tplMatch; };
    if (_r isEqualTo "" && {(_core find _nl) >= 0}) then {
        _r = ((_core splitString _nl) apply { [_x, _depth] call _seg }) joinString _nl;
    };
    if (_r isEqualTo "" && {(_core find " · ") > 0}) then {
        _r = (([_core, " · "] call CBA_fnc_split) apply { [_x, _depth + 1] call _seg }) joinString " · ";
    };
    if (_r isEqualTo "") exitWith { _text };
    private _at = _text find _core;
    (_text select [0, _at]) + _r + (_text select [_at + count _core])
};

// Découpe autour des balises du texte structuré.
private _out = "";
private _rest = _v;
while { _rest isNotEqualTo "" } do {
    private _i = _rest find "<";
    if (_i < 0) then { _out = _out + ([_rest] call _seg); _rest = ""; } else {
        if (_i > 0) then { _out = _out + ([_rest select [0, _i]] call _seg); };
        private _j = (_rest select [_i]) find ">";
        if (_j < 0) then { _out = _out + (_rest select [_i]); _rest = ""; } else {
            _out = _out + (_rest select [_i, _j + 1]);
            _rest = _rest select [_i + _j + 1];
        };
    };
};
if (count _cache > 4000) then { _cache = createHashMap; missionNamespace setVariable ["COMSPEC_ATAK_I18nCache", _cache]; };
_cache set [_v, _out];
_out
