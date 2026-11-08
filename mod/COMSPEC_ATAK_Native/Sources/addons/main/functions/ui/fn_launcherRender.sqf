/*
    Lanceur (V2) : champ de recherche (en main, plein écran), puis grille d'icônes par section : 3 colonnes en portrait, 5 en paysage PETIT, 6 en plein écran.
    En-tête de section : barre d'accent, titre, nombre d'apps et filet, sur un bandeau léger (lisible sur tout fond d'écran).
    Recherche (2.9.27) : filtrage sur place par fn_launcherFilter (uiNamespace COMSPEC_ATAK_LauncherFilter, modèle
    COMSPEC_ATAK_LauncherModel) : en-têtes et tuiles créés une fois ici, masqués et replacés à la frappe (0,1 s), sans
    redessiner la page ni perdre le focus. Entrée ouvre la première app trouvée, Échap efface (fn_keyBlock). En mode
    PETIT : champ compact, sans les puces de filtre.
    V3 (retour de jeu « améliorer nettement le menu des apps ») :
      - Filtres sous la recherche : TOUT, ÉPINGLÉES et une puce par section (uiNamespace COMSPEC_ATAK_LauncherSection),
        en grille de deux rangées (5 + 5) depuis la 2.6.0.
      - En tête : ÉPINGLÉES (clic droit sur une tuile : épingler / désépingler, profil COMSPEC_ATAK_PinnedApps) puis
        RÉCENTES (une rangée, profil COMSPEC_ATAK_AppRecent tenu par fn_navigate).
      - Sections repliables d'un clic sur leur en-tête (profil COMSPEC_ATAK_LauncherFolded) ; repliée, l'en-tête
        garde le nombre d'apps et les non-lus.
      - Tuiles : pastille NOUVEAU (config new=1, jamais ouverte), liseré des épinglées, W.I.P grisé (config wip=1).
      - Apparition des tuiles en fondu, rangée par rangée, à l'arrivée sur l'accueil (animations activées).
*/
disableSerialization;
private _d = ([] call comspec_atak_native_fnc_display);
if (isNull _d) exitWith { false };
private _g = _d displayCtrl 88531;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", ""];
// Apps regroupées (réglage d'addon ou du joueur, fn_appSpaces) : d'autres tuiles ; sinon, le lanceur ci-dessous.
if (["launcherView"] call comspec_atak_native_fnc_appSpaces) exitWith {
    uiNamespace setVariable ["COMSPEC_ATAK_LauncherModel", createHashMap];
    ["launcher"] call comspec_atak_native_fnc_pageSpace
};
private _cols = _l get "cols";
private _pad = (_l get "pad") * 2;
private _font = _l get "fontSmall";
private _ratio = (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]);
// Thème (fn_theme, 3.1.0) : accent, textes et fonds des en-têtes, puces et feuille d'actions.
private _thm = [] call comspec_atak_native_fnc_theme;
private _acc = _thm get "accent";
private _accHex = _thm get "hexAccent";
private _tw = (_bw - 0.012 - _pad * (_cols + 1)) / _cols;
private _th = _tw * _ratio * ([0.92, 0.8] select (_l get "mini")); // mode PETIT : tuiles moins hautes, une rangée de plus visible
private _secH = _font * 1.6;
private _gw = _bw - 0.012 - 2 * _pad;
private _apps = ["filter", ([] call comspec_atak_native_fnc_appList) select { [_x] call comspec_atak_native_fnc_appVisible }] call comspec_atak_native_fnc_appSpaces;
private _interactive = (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["interactive", false];
// Mode PETIT : champ de recherche compact (hauteur d'un en-tête de section), sans les puces de filtre.
private _mini = _l get "mini";
private _searchOn = _interactive && {profileNamespace getVariable ["COMSPEC_ATAK_LauncherSearch", true]};
private _chipsOn = _searchOn && {!_mini};
private _filter = [uiNamespace getVariable ["COMSPEC_ATAK_LauncherFilter", ""], ""] select !_searchOn;
private _y = _pad;
private _srch = [controlNull, controlNull, controlNull];

// Recherche : filtrage sur place (fn_launcherFilter), la page n'est plus redessinée à chaque lettre.
if (_searchOn) then {
    private _sh = _font * ([1.7, 1.3] select _mini);
    private _sbg = ["COMSPEC_RscText", [_pad, _y, _gw, _sh]] call comspec_atak_native_fnc_pageCtrl;
    private _sf = _thm get "field";
    _sbg ctrlSetBackgroundColor [_sf select 0, _sf select 1, _sf select 2, 0.86];
    _sbg ctrlEnable false;
    // Loupe de recherche (icône ui_search, 3.1.0 : avant, la loupe « zoom avant » de la carte avec son « + »).
    private _ic = ["COMSPEC_RscPicture", [_pad * 1.6, _y + _sh * 0.2, _sh * 0.6 / _ratio, _sh * 0.6], "\z\comspec_atak_native\addons\main\data\ui_search.paa"] call comspec_atak_native_fnc_pageCtrl;
    _ic ctrlSetTextColor (_thm get "textDim");
    _ic ctrlEnable false;
    private _ex = _pad * 2.4 + _sh * 0.6 / _ratio;
    private _hint = ["COMSPEC_RscText", [_ex, _y, _gw - _ex, _sh], "Rechercher une app"] call comspec_atak_native_fnc_pageCtrl;
    _hint ctrlSetFontHeight (_font * ([0.95, 0.85] select _mini));
    _hint ctrlSetTextColor (_thm get "textMuted");
    _hint ctrlEnable false;
    _hint ctrlShow (_filter isEqualTo "");
    private _ed = ["COMSPEC_RscEdit", [_ex, _y + _sh * 0.1, _gw - _ex - _sh * 1.2, _sh * 0.8], _filter] call comspec_atak_native_fnc_pageCtrl;
    _ed ctrlSetBackgroundColor [0, 0, 0, 0];
    _ed ctrlSetTextColor (_thm get "text");
    _ed ctrlSetFontHeight (_font * ([0.95, 0.85] select _mini));
    // Repéré par fn_keyBlock : Échap efface la recherche au lieu de ranger le téléphone.
    _ed setVariable ["launcherSearch", true];
    _ed ctrlAddEventHandler ["KeyUp", {
        params ["_e", "_key"];
        private _t = ctrlText _e;
        // Entrée : filtre appliqué tout de suite, ouvre la première app trouvée.
        if (_key in [0x1C, 0x9C]) exitWith {
            uiNamespace setVariable ["COMSPEC_ATAK_LauncherTok", -1];
            uiNamespace setVariable ["COMSPEC_ATAK_LauncherFilter", _t];
            private _hit = [_t, true] call comspec_atak_native_fnc_launcherFilter;
            if (_hit isNotEqualTo "") then {
                uiNamespace setVariable ["COMSPEC_ATAK_LauncherFilter", ""];
                [{ [_this] call comspec_atak_native_fnc_navigate; }, _hit] call comspec_atak_native_fnc_nextFrame;
            };
        };
        if (_t isEqualTo (uiNamespace getVariable ["COMSPEC_ATAK_LauncherFilter", ""])) exitWith {};
        uiNamespace setVariable ["COMSPEC_ATAK_LauncherFilter", _t];
        // Débounce court : une frappe rapide ne refait la mise en page qu'une fois.
        private _tok = diag_tickTime;
        uiNamespace setVariable ["COMSPEC_ATAK_LauncherTok", _tok];
        [{
            params ["_tok"];
            if ((uiNamespace getVariable ["COMSPEC_ATAK_LauncherTok", -1]) isNotEqualTo _tok) exitWith {};
            [uiNamespace getVariable ["COMSPEC_ATAK_LauncherFilter", ""]] call comspec_atak_native_fnc_launcherFilter;
        }, [_tok], 0.1] call comspec_atak_native_fnc_waitAndExec;
    }];
    uiNamespace setVariable ["COMSPEC_ATAK_LauncherEdit", _ed];
    private _clr = ["COMSPEC_RscButtonOverlay", [_pad + _gw - _sh * 1.1, _y, _sh * 1.1, _sh], "×"] call comspec_atak_native_fnc_pageCtrl;
    _clr ctrlSetFontHeight _font;
    _clr ctrlSetTextColor (_thm get "text");
    _clr ctrlSetTooltip (("Effacer la recherche") call comspec_atak_native_fnc_tr);
    _clr ctrlShow (_filter isNotEqualTo "");
    _clr ctrlAddEventHandler ["ButtonClick", {
        uiNamespace setVariable ["COMSPEC_ATAK_LauncherTok", -1];
        uiNamespace setVariable ["COMSPEC_ATAK_LauncherFilter", ""];
        private _e = uiNamespace getVariable ["COMSPEC_ATAK_LauncherEdit", controlNull];
        if (!isNull _e) then { _e ctrlSetText (("") call comspec_atak_native_fnc_tr); ctrlSetFocus _e; };
        [""] call comspec_atak_native_fnc_launcherFilter;
    }];
    _srch = [_ed, _hint, _clr];
    _y = _y + _sh + ([_pad, _pad * 0.5] select _mini);
};

// Profil de rôle ou vue complète (fn_appSpaces) : le bouton pour passer de l'un à l'autre, rien sans profil ni apps regroupées.
private _viewBar = ["viewBar"] call comspec_atak_native_fnc_appSpaces;
if (_viewBar isNotEqualTo "") then {
    private _vh = _font * 1.7;
    private _vb = ["COMSPEC_RscButton", [_pad, _y, _gw, _vh], _viewBar] call comspec_atak_native_fnc_pageCtrl;
    _vb ctrlSetFontHeight (_font max COMSPEC_ATAK_FontMin);
    _vb ctrlSetBackgroundColor (_thm get "surface2");
    _vb ctrlSetTextColor (_thm get "text");
    _vb ctrlAddEventHandler ["ButtonClick", { ["toggleAll"] call comspec_atak_native_fnc_appSpaces; }];
    _y = _y + _vh + _pad * 0.5;
};

// Filtres (en main, hors mode PETIT) : TOUT, ÉPINGLÉES, une puce par section.
// Retour de jeu : puces trop petites (texte à 0,8 x fontSmall, sous le plancher des légendes : le trait d'un « I »
// d'un pixel disparaissait, « COMMUN CATION »). Grille régulière de deux rangées (5 + 5 pour 10 filtres), puces plus
// hautes, texte en fontSmall ; une rangée de plus seulement si un libellé ne tient pas dans sa case.
private _pins = profileNamespace getVariable ["COMSPEC_ATAK_PinnedApps", []];
if !(_pins isEqualType []) then { _pins = []; };
private _secSel = [uiNamespace getVariable ["COMSPEC_ATAK_LauncherSection", ""], ""] select !_chipsOn;
private _sections = [];
{ _sections pushBackUnique (_x get "section"); } forEach _apps;
// Filtre GROUPES (*FOLD) gardé : il était remis à TOUT ici (pas une section d'apps), le filtre ne filtrait rien.
if (_secSel isNotEqualTo "" && {!(_secSel in ["*PIN", "*FOLD"])} && {!(_secSel in _sections)}) then { _secSel = ""; };
if (_secSel isEqualTo "*PIN" && {(count _pins) isEqualTo 0}) then { _secSel = ""; };
if (_chipsOn) then {
    private _chips = [["TOUT", ""]];
    if ((count _pins) > 0) then { _chips pushBack ["ÉPINGLÉES", "*PIN"]; };
    _chips pushBack ["GROUPES", "*FOLD"];
    { _chips pushBack [toUpper _x, _x]; } forEach _sections;
    private _cf = _font max COMSPEC_ATAK_FontMin;
    private _ch = _cf * 2.1;
    private _gap = _pad * 0.6;
    private _m = _d ctrlCreate ["COMSPEC_RscTextCenter", -1, _g];
    [_m, "COMSPEC_RscTextCenter"] call comspec_atak_native_fnc_fontApply;
    _m ctrlSetPosition [0, 0, _gw * 2, _ch];
    _m ctrlCommit 0;
    _m ctrlSetFontHeight _cf;
    private _maxW = 0;
    {
        private _txt = (_x select 0) call comspec_atak_native_fnc_tr;
        _m ctrlSetText _txt; // i18n:brut (déjà traduit)
        _maxW = _maxW max ((ctrlTextWidth _m) + _pad * 1.6);
        _x pushBack _txt;
    } forEach _chips;
    ctrlDelete _m;
    private _n = count _chips;
    private _cols = ceil (_n / 2);
    // Écran étroit (portrait) : moins de colonnes tant que le plus long libellé ne tient pas, au moins deux.
    while { _cols > 2 && {(_gw - _gap * (_cols - 1)) / _cols < _maxW} } do { _cols = _cols - 1; };
    private _cw = (_gw - _gap * (_cols - 1)) / _cols;
    {
        _x params ["", "_key", "_txt"];
        private _cx = _pad + (_forEachIndex mod _cols) * (_cw + _gap);
        private _cy = _y + (floor (_forEachIndex / _cols)) * (_ch + _gap);
        private _on = _key isEqualTo _secSel;
        private _cf0 = ["COMSPEC_RscText", [_cx, _cy, _cw, _ch]] call comspec_atak_native_fnc_pageCtrl;
        private _c = ["COMSPEC_RscButtonFlat", [_cx, _cy, _cw, _ch]] call comspec_atak_native_fnc_pageCtrl;
        _c ctrlSetText _txt; // i18n:brut (déjà traduit)
        // Libellé encore trop long pour sa case (deux colonnes en portrait) : réduit, jamais sous le plancher.
        _c ctrlSetFontHeight ((_cf * ((_cw / _maxW) min 1)) max COMSPEC_ATAK_FontMin);
        _cf0 ctrlSetBackgroundColor ([(_thm get "surface2"), _acc] select _on);
        _c ctrlSetTextColor ([(_thm get "text"), (_thm get "onAccent")] select _on);
        _c setVariable ["key", _key];
        _c ctrlAddEventHandler ["ButtonClick", {
            params ["_c"];
            private _k = _c getVariable ["key", ""];
            uiNamespace setVariable ["COMSPEC_ATAK_LauncherSection", [_k, ""] select (_k isEqualTo (uiNamespace getVariable ["COMSPEC_ATAK_LauncherSection", ""]))];
            // Changer de filtre ferme la feuille d'actions ouverte (choix ou création de groupe) : elle restait affichée
            // entre les filtres et les apps sous SUPPORT ET FEUX et les autres catégories.
            uiNamespace setVariable ["COMSPEC_ATAK_AppSheet", ""];
            uiNamespace setVariable ["COMSPEC_ATAK_AppSheetMode", ""];
            [{ ["LAUNCHER"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
        }];
    } forEach _chips;
    _y = _y + (ceil (_n / _cols)) * (_ch + _gap) - _gap + _pad;
    if ((count _pins) isEqualTo 0) then {
        private _hint = ["COMSPEC_RscText", [_pad, _y - _pad * 0.4, _gw, _font * 1.2], "Astuce : clic droit sur une app pour l'épingler ou l'ajouter à un groupe"] call comspec_atak_native_fnc_pageCtrl;
        _hint ctrlSetFontHeight ((_font * 0.8) max COMSPEC_ATAK_FontMin);
        _hint ctrlSetTextColor (_thm get "textMuted");
        _hint ctrlEnable false;
        _y = _y + _font * 1.2;
    };
};

// Feuille d'actions (clic droit sur une app, ou création de groupe).
private _sheetPage = uiNamespace getVariable ["COMSPEC_ATAK_AppSheet", ""];
private _sheetMode = uiNamespace getVariable ["COMSPEC_ATAK_AppSheetMode", ""];
// « Choisir un groupe » n'a de sens que pour une app choisie (clic droit) ; « Nouveau groupe » sans app (tuile
// « Nouveau groupe ») seulement là où cette tuile existe : TOUT et GROUPES. Hors de ces cas, l'état resté d'une
// visite précédente est oublié.
if ((_sheetMode isEqualTo "pick" && {_sheetPage isEqualTo ""}) || {_sheetMode isEqualTo "new" && {_sheetPage isEqualTo ""} && {!(_secSel in ["", "*FOLD"])}}) then {
    _sheetMode = "";
    uiNamespace setVariable ["COMSPEC_ATAK_AppSheetMode", ""];
};
if (_interactive && {(_sheetPage isNotEqualTo "") || {_sheetMode in ["new", "pick"]}}) then {
    private _sheetH = _font * 7.2;
    private _sbg = ["COMSPEC_RscText", [_pad, _y, _gw, _sheetH]] call comspec_atak_native_fnc_pageCtrl;
    private _ss = _thm get "surface";
    _sbg ctrlSetBackgroundColor [_ss select 0, _ss select 1, _ss select 2, 0.97];
    _sbg ctrlEnable false;
    private _by = _y + _font * 1.8;
    private _bw3 = (_gw - _pad * 2.8) / 3;
    private _mkBtn = {
        params ["_x0", "_label", "_code", ["_pri", false]];
        private _b = [["COMSPEC_RscButton", "COMSPEC_RscButtonPrimary"] select _pri, [_x0, _by, _bw3, _font * 1.6], _label] call comspec_atak_native_fnc_pageCtrl;
        _b ctrlSetFontHeight (_font * 0.85);
        if (!_pri) then { _b ctrlSetBackgroundColor (_thm get "surface2"); _b ctrlSetTextColor (_thm get "text"); };
        _b setVariable ["code", _code];
        _b ctrlAddEventHandler ["ButtonClick", {
            params ["_c"];
            private _fn = _c getVariable ["code", {}];
            [] call _fn;
        }];
    };
    private _mode = _sheetMode;
    if (_sheetPage isNotEqualTo "" && {!(_mode in ["new", "pick"])}) then {
        private _appNm = (((_apps select { (_x get "page") isEqualTo _sheetPage }) param [0, createHashMap]) getOrDefault ["name", _sheetPage]) call comspec_atak_native_fnc_tr;
        private _st = ["COMSPEC_RscStructuredText", [_pad * 1.4, _y + _pad * 0.4, _gw - _pad * 2, _font * 1.5]] call comspec_atak_native_fnc_pageCtrl;
        _st ctrlSetStructuredText parseText ((format ["<t font='COMSPEC_BarlowBold' color='%2'>%1</t><br/><t size='0.8' color='%3'>Que faire avec cette app ?</t>", _appNm, _thm get "hexText", _thm get "hexTextDim"]) call comspec_atak_native_fnc_tr);
        _st ctrlEnable false;
        private _pinned = _sheetPage in _pins;
        [_pad * 1.4, ["Épingler", "Désépingler"] select _pinned, {
            private _p = uiNamespace getVariable ["COMSPEC_ATAK_AppSheet", ""];
            private _l = profileNamespace getVariable ["COMSPEC_ATAK_PinnedApps", []];
            if !(_l isEqualType []) then { _l = []; };
            _l = +_l;
            if (_p in _l) then { _l = _l - [_p]; ["Retirée des épinglées", 2] call comspec_atak_native_fnc_toast; } else {
                if ((count _l) >= 12) exitWith { ["12 apps épinglées au plus", 2] call comspec_atak_native_fnc_toast; };
                _l pushBack _p;
                ["Épinglée en tête", 2] call comspec_atak_native_fnc_toast;
            };
            profileNamespace setVariable ["COMSPEC_ATAK_PinnedApps", _l];
            [] call comspec_atak_native_fnc_saveProfile;
            uiNamespace setVariable ["COMSPEC_ATAK_AppSheet", ""];
            uiNamespace setVariable ["COMSPEC_ATAK_AppSheetMode", ""];
            [{ ["LAUNCHER"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
        }, false] call _mkBtn;
        [_pad * 1.4 + _bw3 + _pad * 0.4, "Au groupe…", {
            uiNamespace setVariable ["COMSPEC_ATAK_AppSheetMode", "pick"];
            [{ ["LAUNCHER"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
        }, true] call _mkBtn;
        [_pad * 1.4 + 2 * (_bw3 + _pad * 0.4), "Fermer", {
            uiNamespace setVariable ["COMSPEC_ATAK_AppSheet", ""];
            uiNamespace setVariable ["COMSPEC_ATAK_AppSheetMode", ""];
            [{ ["LAUNCHER"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
        }, false] call _mkBtn;
    } else {
        if (_mode isEqualTo "new") then {
            private _st = ["COMSPEC_RscStructuredText", [_pad * 1.4, _y + _pad * 0.4, _gw - _pad * 2, _font * 1.5]] call comspec_atak_native_fnc_pageCtrl;
            _st ctrlSetStructuredText parseText ((format ["<t font='COMSPEC_BarlowBold' color='%1'>Nouveau groupe</t><br/><t size='0.8' color='%2'>Donnez un nom court (ex. Terrain, Ops).</t>", _thm get "hexText", _thm get "hexTextDim"]) call comspec_atak_native_fnc_tr);
            _st ctrlEnable false;
        };
    };
    if (_mode isEqualTo "pick") then {
        private _folders = ["list"] call comspec_atak_native_fnc_appFolders;
        private _fy = _by + _font * 1.9;
        private _flbl = ["COMSPEC_RscText", [_pad * 1.4, _fy, _gw - _pad * 2, _font * 1.1], "Choisir un groupe :"] call comspec_atak_native_fnc_pageCtrl;
        _flbl ctrlSetFontHeight (_font * 0.85);
        _flbl ctrlSetTextColor (_thm get "textDim");
        _flbl ctrlEnable false;
        _fy = _fy + _font * 1.2;
        {
            _x params ["_fid", "_fnm"];
            private _fb = ["COMSPEC_RscButton", [_pad * 1.4, _fy, _gw - _pad * 2.8, _font * 1.45], _fnm] call comspec_atak_native_fnc_pageCtrl;
            _fb ctrlSetFontHeight (_font * 0.9);
            _fb ctrlSetBackgroundColor (_thm get "surface2");
            _fb ctrlSetTextColor (_thm get "text");
            _fb setVariable ["fid", _fid];
            _fb ctrlAddEventHandler ["ButtonClick", {
                params ["_c"];
                private _fid = _c getVariable ["fid", ""];
                private _p = uiNamespace getVariable ["COMSPEC_ATAK_AppSheet", ""];
                if (["add", _fid, _p] call comspec_atak_native_fnc_appFolders) then {
                    ["Ajoutée au groupe", 2] call comspec_atak_native_fnc_toast;
                } else {
                    ["Impossible d'ajouter (groupe plein ?)", 2] call comspec_atak_native_fnc_toast;
                };
                uiNamespace setVariable ["COMSPEC_ATAK_AppSheet", ""];
                uiNamespace setVariable ["COMSPEC_ATAK_AppSheetMode", ""];
                [{ ["LAUNCHER"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
            }];
            _fy = _fy + _font * 1.55;
        } forEach _folders;
        // « + Nouveau groupe » et « Annuler » côte à côte : avant, aucun moyen de fermer ce choix sans choisir un
        // groupe ; il restait affiché entre les filtres et les apps, quel que soit le filtre, même après réouverture.
        private _nbw = (_gw - _pad * 3.2) * 0.68;
        private _nb = ["COMSPEC_RscButtonPrimary", [_pad * 1.4, _fy, _nbw, _font * 1.45], "+ Nouveau groupe"] call comspec_atak_native_fnc_pageCtrl;
        _nb ctrlSetFontHeight (_font * 0.9);
        _nb ctrlAddEventHandler ["ButtonClick", {
            uiNamespace setVariable ["COMSPEC_ATAK_AppSheetMode", "new"];
            [{ ["LAUNCHER"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
        }];
        private _cb = ["COMSPEC_RscButton", [_pad * 1.8 + _nbw, _fy, (_gw - _pad * 3.2) - _nbw, _font * 1.45], "Annuler"] call comspec_atak_native_fnc_pageCtrl;
        _cb ctrlSetFontHeight (_font * 0.85);
        _cb ctrlSetBackgroundColor (_thm get "surface2");
        _cb ctrlSetTextColor (_thm get "text");
        _cb ctrlAddEventHandler ["ButtonClick", {
            uiNamespace setVariable ["COMSPEC_ATAK_AppSheet", ""];
            uiNamespace setVariable ["COMSPEC_ATAK_AppSheetMode", ""];
            [{ ["LAUNCHER"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
        }];
        _fy = _fy + _font * 1.7;
        _sbg ctrlSetPosition [_pad, _y, _gw, (_fy - _y) + _pad];
        _sbg ctrlCommit 0;
        _y = _fy + _pad;
    } else {
        if (_mode isEqualTo "new") then {
            private _ny = _by + _font * 1.9;
            private _ed = ["COMSPEC_RscEdit", [_pad * 1.4, _ny, _gw * 0.52, _font * 1.5], "Mon groupe"] call comspec_atak_native_fnc_pageCtrl;
            _ed ctrlSetFontHeight (_font * 0.95);
            uiNamespace setVariable ["COMSPEC_ATAK_FolderNameEdit", _ed];
            private _ok = ["COMSPEC_RscButtonPrimary", [_pad * 1.4 + _gw * 0.55, _ny, _gw * 0.18, _font * 1.5], "Créer"] call comspec_atak_native_fnc_pageCtrl;
            _ok ctrlSetFontHeight (_font * 0.9);
            _ok ctrlAddEventHandler ["ButtonClick", {
                private _ed = uiNamespace getVariable ["COMSPEC_ATAK_FolderNameEdit", controlNull];
                private _name = if (isNull _ed) then { "Groupe" } else { ctrlText _ed };
                private _id = ["create", _name] call comspec_atak_native_fnc_appFolders;
                private _p = uiNamespace getVariable ["COMSPEC_ATAK_AppSheet", ""];
                if (_id isEqualTo "") then {
                    ["Impossible de créer le groupe (12 max)", 2] call comspec_atak_native_fnc_toast;
                } else {
                    if (_p isNotEqualTo "") then { ["add", _id, _p] call comspec_atak_native_fnc_appFolders; };
                    ["Groupe créé", 2] call comspec_atak_native_fnc_toast;
                };
                uiNamespace setVariable ["COMSPEC_ATAK_AppSheet", ""];
                uiNamespace setVariable ["COMSPEC_ATAK_AppSheetMode", ""];
                [{ ["LAUNCHER"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
            }];
            private _cx = ["COMSPEC_RscButton", [_pad * 1.4 + _gw * 0.75, _ny, _gw * 0.16, _font * 1.5], "Annuler"] call comspec_atak_native_fnc_pageCtrl;
            _cx ctrlSetFontHeight (_font * 0.85);
            _cx ctrlSetBackgroundColor (_thm get "surface2");
            _cx ctrlSetTextColor (_thm get "text");
            _cx ctrlAddEventHandler ["ButtonClick", {
                uiNamespace setVariable ["COMSPEC_ATAK_AppSheet", ""];
                uiNamespace setVariable ["COMSPEC_ATAK_AppSheetMode", ""];
                [{ ["LAUNCHER"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
            }];
            _sbg ctrlSetPosition [_pad, _y, _gw, _font * 5.2];
            _sbg ctrlCommit 0;
            _y = _y + _font * 5.4;
        } else {
            _y = _y + _sheetH + _pad;
        };
    };
};

// Groupes : [clé de repli, titre, apps, affiché sans recherche, affiché en recherche]. Puce choisie : un seul groupe ;
// sinon épinglées, récentes, mes groupes puis les sections. En recherche, seules les sections (chaque app une fois).
private _byPage = createHashMap;
{ _byPage set [_x get "page", _x]; } forEach _apps;
private _pinApps = (_pins apply { _byPage getOrDefault [_x, createHashMap] }) select { (count _x) > 0 };
private _groups = [];
private _secGroups = { { private _sec = _x; _groups pushBack [_sec, _sec, _apps select { (_x get "section") isEqualTo _sec }, _this, true]; } forEach _sections; };
switch (true) do {
    case (_secSel isEqualTo "*PIN"): { _groups pushBack ["", "Épinglées", _pinApps, true, true]; };
    case (_secSel isEqualTo "*FOLD"): {
        _groups pushBack ["*FOLD", "Mes groupes", [], true, false];
        // Recherche depuis GROUPES : toutes les apps, par section.
        if (_searchOn) then { false call _secGroups; };
    };
    case (_secSel isNotEqualTo ""): { _groups pushBack ["", _secSel, _apps select { (_x get "section") isEqualTo _secSel }, true, true]; };
    default {
        if ((count _pinApps) > 0) then { _groups pushBack ["*PIN", "Épinglées", _pinApps, true, false]; };
        private _rec = profileNamespace getVariable ["COMSPEC_ATAK_AppRecent", []];
        if !(_rec isEqualType []) then { _rec = []; };
        private _recApps = ((_rec apply { _byPage getOrDefault [_x, createHashMap] }) select { (count _x) > 0 && {!((_x get "page") in _pins)} && {!(_x getOrDefault ["wip", false])} }) select [0, _cols];
        if ((count _recApps) > 0) then { _groups pushBack ["*REC", "Récentes", _recApps, true, false]; };
        // Groupes joueur : toujours visibles en tête (même vides → bouton créer).
        _groups pushBack ["*FOLD", "Mes groupes", [], true, false];
        true call _secGroups;
    };
};
private _folded = profileNamespace getVariable ["COMSPEC_ATAK_LauncherFolded", []];
if !(_folded isEqualType []) then { _folded = []; };
private _seen = profileNamespace getVariable ["COMSPEC_ATAK_AppSeen", []];
if !(_seen isEqualType []) then { _seen = []; };
// Fondu d'arrivée : seulement en revenant sur l'accueil (pas à chaque frappe de recherche ni rafraîchissement).
private _fadeIn = (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["pagePrev", ""]) isNotEqualTo "LAUNCHER") && {["anim"] call comspec_atak_native_fnc_fx};
// Pastilles calculées une fois par app (en-tête de section replié puis tuile, avant : deux fois).
private _badgeOf = createHashMap;
{ _badgeOf set [_x get "page", [_x get "page"] call comspec_atak_native_fnc_appBadge]; } forEach _apps;
private _chev = "\z\comspec_atak_native\addons\main\data\%1.paa";
// Tout est créé une fois, en haut de la zone des apps (_y0) ; fn_launcherFilter place, montre et masque ensuite (au
// rendu puis à chaque frappe de recherche, sans rien recréer).
private _y0 = _y;
private _model = [];
private _track = {
    private _list = uiNamespace getVariable ["COMSPEC_ATAK_PageControls", []];
    _list append _this;
    uiNamespace setVariable ["COMSPEC_ATAK_PageControls", _list];
};
{
    _x params ["_key", "_sec", "_items", "_showNF", "_showF"];
    private _isFolded = _key isNotEqualTo "" && {_key in _folded};
    private _unread = 0;
    { _unread = _unread + (_badgeOf getOrDefault [_x get "page", 0]); } forEach _items;
    // En-tête : bandeau, barre d'accent, titre, nombre (et non-lus si repliée), chevron, filet ; cliquable pour replier.
    private _hdr = [];
    private _band = ["COMSPEC_RscText", [_pad, _y0, _gw, _secH]] call comspec_atak_native_fnc_pageCtrl;
    // Bandeau d'en-tête : fond de page (lisible sur tout fond d'écran), plus marqué quand la section est repliée.
    private _bb = [_thm get "bg0", _thm get "surface"] select _isFolded;
    _band ctrlSetBackgroundColor [_bb select 0, _bb select 1, _bb select 2, [0.62, 0.8] select _isFolded];
    _band ctrlEnable false;
    _hdr pushBack _band;
    private _bar = ["COMSPEC_RscText", [_pad, _y0 + _secH * 0.22, pixelW * 3, _secH * 0.56]] call comspec_atak_native_fnc_pageCtrl;
    _bar ctrlSetBackgroundColor ([_acc, _thm get "rail"] select (_key in ["*REC"]));
    _bar ctrlEnable false;
    _hdr pushBack _bar;
    private _extra = if (_isFolded && {_unread > 0}) then { format ["  <t size='0.75' color='" + (COMSPEC_ATAK_Th get "hexWarn") + "'>● %1 non lu(s)</t>", _unread] } else { "" };
    private _cnt = if (_key isEqualTo "*FOLD") then { count (["list"] call comspec_atak_native_fnc_appFolders) } else { count _items };
    private _lbl = ["COMSPEC_RscStructuredText", [_pad * 1.6, _y0 + _secH * 0.12, _gw - _pad - _secH * 1.4, _secH]] call comspec_atak_native_fnc_pageCtrl;
    _lbl ctrlEnable false;
    _hdr pushBack _lbl;
    private _line = ["COMSPEC_RscText", [_pad, _y0 + _secH - pixelH, _gw, pixelH]] call comspec_atak_native_fnc_pageCtrl;
    _line ctrlSetBackgroundColor (_thm get "border");
    _line ctrlEnable false;
    _hdr pushBack _line;
    if (_key isNotEqualTo "") then {
        private _cs = _secH * 0.5;
        private _cw = _cs / _ratio;
        private _cv = ["COMSPEC_RscPicture", [_pad + _gw - _cw - _pad * 0.6, _y0 + (_secH - _cs) / 2, _cw, _cs], format [_chev, ["ui_chevron_up", "ui_chevron_down"] select _isFolded]] call comspec_atak_native_fnc_pageCtrl;
        _cv ctrlSetTextColor (_thm get "text");
        _cv ctrlEnable false;
        _hdr pushBack _cv;
        private _hb = ["COMSPEC_RscButtonOverlay", [_pad, _y0, _gw, _secH]] call comspec_atak_native_fnc_pageCtrl;
        _hb setVariable ["key", _key];
        _hb ctrlSetTooltip ((["Replier la section", "Déplier la section"] select _isFolded) call comspec_atak_native_fnc_tr);
        _hb ctrlAddEventHandler ["ButtonClick", {
            params ["_c"];
            private _k = _c getVariable ["key", ""];
            private _f = profileNamespace getVariable ["COMSPEC_ATAK_LauncherFolded", []];
            if !(_f isEqualType []) then { _f = []; };
            _f = +_f;
            if (_k in _f) then { _f = _f - [_k]; } else { _f pushBack _k; };
            profileNamespace setVariable ["COMSPEC_ATAK_LauncherFolded", _f];
            [] call comspec_atak_native_fnc_saveProfile;
            [{ ["LAUNCHER"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
        }];
        _hdr pushBack _hb;
    };
    // Tuiles : [contrôles, bouton, clé de recherche, page, W.I.P, position [x, y]].
    private _tiles = [];
    private _none = controlNull;
    // Section repliée : tuiles créées seulement si la recherche peut les montrer.
    if (!_isFolded || {_searchOn && {_showF}}) then {
        // Section Mes groupes : tuiles dossier + bouton créer (pas la grille d'apps classique).
        if (_key isEqualTo "*FOLD") then {
            private _folders = ["list"] call comspec_atak_native_fnc_appFolders;
            private _fl = _folders apply {
                _x params ["_fid", "_fnm", "_fpages"];
                [_fid, format ["%1 (%2)", _fnm, count _fpages]]
            };
            // Tuile « Nouveau groupe »
            _fl pushBack ["*NEWFOLDER*", "Nouveau groupe"];
            {
                _x params ["_fid", "_fnm"];
                private _tile = [_d, _g, [_pad, _y0, _tw, _th], "\z\comspec_atak_native\addons\main\data\app_folder.paa", _fnm,
                    0, "FOLDER", _font, false, false, false] call comspec_atak_native_fnc_tileCreate;
                private _btn = _tile select ((count _tile) - 1);
                _btn setVariable ["folderId", [_fid, ""] select (_fid isEqualTo "*NEWFOLDER*")];
                _btn setVariable ["isNewFolder", _fid isEqualTo "*NEWFOLDER*"];
                // Remplace le clic gauche (navigate FOLDER générique) : ouvre le bon groupe.
                _btn ctrlRemoveAllEventHandlers "ButtonClick";
                _btn ctrlAddEventHandler ["ButtonClick", {
                    params ["_c"];
                    if (_c getVariable ["isNewFolder", false]) then {
                        uiNamespace setVariable ["COMSPEC_ATAK_AppSheet", ""];
                        uiNamespace setVariable ["COMSPEC_ATAK_AppSheetMode", "new"];
                        [{ ["LAUNCHER"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
                    } else {
                        uiNamespace setVariable ["COMSPEC_ATAK_FolderId", _c getVariable ["folderId", ""]];
                        ["FOLDER"] call comspec_atak_native_fnc_navigate;
                    };
                }];
                _tile call _track;
                _tiles pushBack [_tile, _btn, "", "FOLDER", false, [_pad, _y0]];
            } forEach _fl;
        } else {
            if ((count _items) isEqualTo 0) then {
                _none = ["COMSPEC_RscText", [_pad, _y0, _gw, _font * 1.4], "Aucune app ne correspond."] call comspec_atak_native_fnc_pageCtrl;
                _none ctrlSetFontHeight _font;
                _none ctrlSetTextColor (_thm get "textMuted");
            };
            {
                private _page = _x get "page";
                private _wip = _x getOrDefault ["wip", false];
                private _tile = [_d, _g, [_pad, _y0, _tw, _th], _x get "icon", _x get "name",
                    _badgeOf getOrDefault [_page, 0], _page, _font, _wip,
                    (_x getOrDefault ["new", false]) && {!(_page in _seen)}, _page in _pins] call comspec_atak_native_fnc_tileCreate;
                // Clic droit : feuille d'actions (épingler / groupe).
                private _btn = _tile select ((count _tile) - 1);
                _btn setVariable ["pinPage", _page];
                _btn ctrlAddEventHandler ["MouseButtonUp", {
                    params ["_c", "_b"];
                    if (_b isNotEqualTo 1) exitWith {};
                    private _p = _c getVariable ["pinPage", ""];
                    uiNamespace setVariable ["COMSPEC_ATAK_AppSheet", _p];
                    uiNamespace setVariable ["COMSPEC_ATAK_AppSheetMode", ""];
                    [{ ["LAUNCHER"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
                }];
                _tile call _track;
                // Clé de recherche : nom français, nom traduit et nom de classe, sans casse ni accents (calculée une fois).
                private _sk = ["fold", format ["%1|%2|%3|%4", _x get "name", (_x get "name") call comspec_atak_native_fnc_tr, _x get "id", _page]] call comspec_atak_native_fnc_patchCatalog;
                _tiles pushBack [_tile, _btn, _sk, _page, _wip, [_pad, _y0]];
            } forEach _items;
        };
    };
    _model pushBack (createHashMapFromArray [
        ["hdr", _hdr], ["hy", _y0], ["lbl", _lbl], ["sec", _sec], ["cnt", _cnt], ["extra", _extra],
        ["folded", _isFolded], ["showNF", _showNF], ["showF", _showF], ["tiles", _tiles], ["none", _none]
    ]);
} forEach _groups;
// Recherche sans résultat.
private _empty = controlNull;
if (_searchOn) then {
    _empty = ["COMSPEC_RscText", [_pad, _y0, _gw, _font * 1.4], "Aucune app ne correspond : essayez un autre mot, ou Échap pour effacer."] call comspec_atak_native_fnc_pageCtrl;
    _empty ctrlSetFontHeight _font;
    _empty ctrlSetTextColor (_thm get "textMuted");
    _empty ctrlEnable false;
    _empty ctrlShow false;
};
_srch params ["_ed", "_shint", "_sclr"];
uiNamespace setVariable ["COMSPEC_ATAK_LauncherModel", createHashMapFromArray [
    ["edit", _ed], ["hint", _shint], ["clear", _sclr], ["empty", _empty], ["group", _g],
    ["geo", [_y0, _pad, _tw, _th, _cols, _secH, _font]], ["groups", _model], ["first", ""]
]];
[_filter, true] call comspec_atak_native_fnc_launcherFilter;
// Fondu d'arrivée, rangée par rangée, après la mise en place (un ctrlCommit de position l'aurait coupé).
if (_fadeIn) then {
    {
        {
            _x params ["_cs", "_btn", "", "", "", "_at"];
            if (ctrlShown _btn) then {
                private _delay = ((floor (((_at select 1) - _y0) / (_th + _pad))) min 6) * 0.03;
                { _x ctrlSetFade 1; _x ctrlCommit 0; _x ctrlSetFade 0; _x ctrlCommit (0.14 + _delay); } forEach _cs;
            };
        } forEach (_x get "tiles");
    } forEach _model;
};
true
