/*
    Traduction à l'affichage. Le code reste écrit en français ; chaque texte montré à l'écran (ctrlSetText,
    ctrlSetStructuredText, ctrlSetTooltip, lbAdd, hint… passés par tools/i18n.py wrap) arrive ici.
    Langue française : rendu tel quel, sauf les textes écrits en anglais dans le code (dictionnaire i18n/fr.txt).
    Aucune traduction chargée (COMSPEC_ATAK_I18nN = 0) : rendu tel quel, sans coût. Langue anglaise : le texte est découpé autour des balises
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
if (!(_v isEqualType "") || {_v isEqualTo ""} || {(missionNamespace getVariable ["COMSPEC_ATAK_I18nN", 0]) isEqualTo 0}) exitWith { _v };
private _cache = missionNamespace getVariable ["COMSPEC_ATAK_I18nCache", createHashMap];
private _hit = _cache getOrDefault [_v, nil];
if (!isNil "_hit") exitWith { _hit };
// Textes avec chiffres (barre d'état, curseur de carte, cap, heures…) : petit cache à part, vidé seul, pour que ces
// valeurs toujours nouvelles ne vident plus le cache des textes fixes (qu'il fallait ensuite tout retraduire).
private _cacheNum = missionNamespace getVariable ["COMSPEC_ATAK_I18nCacheNum", createHashMap];
_hit = _cacheNum getOrDefault [_v, nil];
if (!isNil "_hit") exitWith { _hit };
// Chemin d'image ou de son (dont les images téléchargées par la DLL, C:/…/x.jpg) et texture procédurale (#(argb,…)r2t(…),
// image vidéo) : jamais traduits.
if ((_v select [0, 1]) isEqualTo "\" || {(_v select [0, 2]) isEqualTo "#("} || {(_v find ".paa") > 0} || {(_v select [1, 2]) isEqualTo ":/"} || {(count _v) > 4 && {((toLower _v) select [(count _v) - 4]) in [".jpg", "jpeg", ".png", ".ogg"]}}) exitWith { _cache set [_v, _v]; _v };

private _exact = missionNamespace getVariable ["COMSPEC_ATAK_I18nExact", createHashMap];
private _tpls = missionNamespace getVariable ["COMSPEC_ATAK_I18nTpl", createHashMap];
private _nl = toString [10];
// « Libellé : valeur » : ponctuation de la langue affichée (espace avant les deux-points en français).
private _colon = [": ", " : "] select ((missionNamespace getVariable ["COMSPEC_ATAK_Lang", "en"]) isEqualTo "fr");
// Modèles sans début fixe : rangés par fn_langApply sous ce repère + leur fin (3 derniers caractères).
private _gen = toString [1];

// Modèle « … %1 … » : renvoie la traduction ou "".
private _tplMatch = {
    params ["_core", "_depth"];
    private _res = "";
    private _n = count _core;
    private _hasDot = (_core find " · ") >= 0;
    private _hasColon = (_core find " : ") > 0;
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
                    // Valeur vide acceptée (« %1 marqueur(s) renvoyé(s)%2. » avec %2 = "") : sinon tout le texte restait en français.
                    if (_st >= _pos && {(_core select [_st]) isEqualTo _pi}) then { _at = _st; };
                } else {
                    private _f = (_core select [_pos]) find _pi;
                    if (_f >= 0) then { _at = _pos + _f; };
                };
                if (_at < 0) then { _pos = -1; } else {
                    _caps pushBack (_core select [_pos, _at - _pos]);
                    _pos = _at + (count _pi);
                };
            };
            // Valeur qui contient « · » alors que le modèle n'en a pas (« Ordre %1 » sur « Ordre de déplacement — … · … ») :
            // le modèle avalait toute la ligne ; on laisse la découpe autour des « · » traduire chaque morceau.
            if (_pos >= 0 && {_hasDot} && {((_parts joinString "") find " · ") < 0} && {(_caps findIf { (_x find " · ") >= 0 }) >= 0}) then { _pos = -1; };
            // Même chose pour « Libellé : valeur » : « Ordre %1 » ne doit pas avaler « Ordre : escorte de … ».
            if (_pos >= 0 && {_hasColon} && {((_parts joinString "") find " : ") < 0} && {(_caps findIf { (_x select [0, 2]) isEqualTo ": " }) >= 0}) then { _pos = -1; };
            if (_pos >= 0) then {
                private _args = [];
                { _args set [_x - 1, [(_caps select _forEachIndex), _depth + 1] call _seg]; } forEach _ids;
                // Valeur vidée par fn_langApply (deux valeurs collées « %1%2 ») : jamais capturée, rendue vide.
                if ((count _args) < 9) then { _args resize 9; };
                _args = _args apply { if (isNil "_x") then { "" } else { _x } };
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
    // Sans lettre (nombres, heures, grilles, ponctuation) : rien à traduire, on évite le parcours des modèles
    // (valeurs capturées surtout : le texte du curseur de carte change à chaque mouvement de souris).
    if (_r isEqualTo "" && {(toLower _core) isEqualTo (toUpper _core)}) exitWith { _text };
    // « · » collé à une balise (« 2 charge(s) cochée(s) · <t …>sécurité levée</t> ») : le morceau finit ou commence
    // par « · » ; traduit sans lui.
    if (_r isEqualTo "" && {(count _core) > 2}) then {
        // Longueurs prises sur les chaînes elles-mêmes : « · » n'a pas la longueur d'un caractère simple.
        if ((_core select [(count _core) - (count " ·")]) isEqualTo " ·") then {
            private _in = _core select [0, (count _core) - (count " ·")];
            private _tr = [_in, _depth] call _seg;
            if (_tr isNotEqualTo _in) then { _r = _tr + " ·"; };
        } else {
            if ((_core select [0, count "· "]) isEqualTo "· ") then {
                private _in = _core select [count "· "];
                private _tr = [_in, _depth] call _seg;
                if (_tr isNotEqualTo _in) then { _r = "· " + _tr; };
            };
        };
    };
    // Modèle avant « Libellé : valeur » : « Connexion Athena : sur %1, saisissez le code %2 » est dans le dictionnaire
    // en entier, alors que le libellé seul (« Connexion Athena ») laissait la suite en français dans les notifications.
    if (_r isEqualTo "") then { _r = [_core, _depth] call _tplMatch; };
    // Lignes et « · » avant « Libellé : valeur » : le dictionnaire est découpé de la même façon (tools/i18n.py), donc
    // « Poste : bâtiments %1 / %2 · forêts %3 / %4 » y est en morceaux ; le libellé seul (« Poste ») gâchait la suite.
    if (_r isEqualTo "" && {(_core find _nl) >= 0}) then {
        // CBA_fnc_split garde les lignes vides (splitString les retirait : « État de l'ATAK :\n\nAlimentation… » perdait son blanc).
        _r = (([_core, _nl] call CBA_fnc_split) apply { [_x, _depth] call _seg }) joinString _nl;
    };
    if (_r isEqualTo "" && {(_core find " · ") > 0}) then {
        _r = (([_core, " · "] call CBA_fnc_split) apply { [_x, _depth + 1] call _seg }) joinString " · ";
    };
    // « Libellé : valeur » assemblé dans le code : libellé traduit, ponctuation anglaise.
    if (_r isEqualTo "" && {(_core find " : ") > 0}) then {
        private _k = _core find " : ";
        private _lbl = _core select [0, _k];
        private _lblEn = _exact getOrDefault [_lbl, _lbl];
        if (_lblEn isNotEqualTo _lbl) then { _r = _lblEn + _colon + ([_core select [_k + 3], _depth + 1] call _seg); };
    };
    // « Titre — émetteur » assemblé dans le code (alertes du poste : « Opérateur à terre — Alpha 1 ») : chaque morceau.
    if (_r isEqualTo "" && {_depth < 2} && {(_core find " — ") > 0}) then {
        private _r2 = (([_core, " — "] call CBA_fnc_split) apply { [_x, _depth + 1] call _seg }) joinString " — ";
        if (_r2 isNotEqualTo _core) then { _r = _r2; };
    };
    // Puce ou symbole devant le texte (« ■ SEEK PRÊT », « ‹ RETOUR », « ● EN LIGNE ») : le texte qui suit est cherché seul.
    if (_r isEqualTo "" && {_depth < 2}) then {
        private _sp = _core find " ";
        if (_sp > 0 && {_sp <= 4} && {private _p = _core select [0, _sp]; (toLower _p) isEqualTo (toUpper _p)}) then {
            private _tail = [_core select [_sp + 1], _depth + 1] call _seg;
            if (_tail isNotEqualTo (_core select [_sp + 1])) then { _r = (_core select [0, _sp + 1]) + _tail; };
        };
    };
    if (_r isEqualTo "") exitWith { _text };
    private _at = _text find _core;
    (_text select [0, _at]) + _r + (_text select [_at + count _core])
};

// Découpe autour des balises du texte structuré. « < » n'ouvre une balise que suivi d'une lettre ou de « / » (<t, <br, </t>, <img) :
// « < RÉGLAGES », « <  PRÉC. » ou « noir < 25 % » restent du texte, cherché en entier dans le dictionnaire.
private _out = "";
private _txt = "";
private _rest = _v;
while { _rest isNotEqualTo "" } do {
    private _i = _rest find "<";
    private _c = if (_i < 0) then { "" } else { _rest select [_i + 1, 1] };
    if (_i >= 0 && {(_c isEqualTo "/") || {(toLower _c) isNotEqualTo (toUpper _c)}}) then {
        _txt = _txt + (_rest select [0, _i]);
        if (_txt isNotEqualTo "") then { _out = _out + ([_txt] call _seg); _txt = ""; };
        private _j = (_rest select [_i]) find ">";
        // « < » sans « > » : pas une balise (bouton « < RÉGLAGES · AFFICHAGE ») ; le texte est traduit quand même.
        if (_j < 0) then { _out = _out + ([_rest select [_i]] call _seg); _rest = ""; } else {
            _out = _out + (_rest select [_i, _j + 1]);
            _rest = _rest select [_i + _j + 1];
        };
    } else {
        if (_i < 0) then { _txt = _txt + _rest; _rest = ""; } else {
            _txt = _txt + (_rest select [0, _i + 1]);
            _rest = _rest select [_i + 1];
        };
    };
};
if (_txt isNotEqualTo "") then { _out = _out + ([_txt] call _seg); };
if (((_v splitString "0123456789") joinString "") isNotEqualTo _v) exitWith {
    if (count _cacheNum > 1500) then { _cacheNum = createHashMap; };
    missionNamespace setVariable ["COMSPEC_ATAK_I18nCacheNum", _cacheNum];
    _cacheNum set [_v, _out];
    _out
};
if (count _cache > 4000) then { _cache = createHashMap; missionNamespace setVariable ["COMSPEC_ATAK_I18nCache", _cache]; };
_cache set [_v, _out];
_out
