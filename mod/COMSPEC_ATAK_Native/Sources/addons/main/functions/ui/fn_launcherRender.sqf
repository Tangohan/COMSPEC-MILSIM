/*
    Lanceur (V2) : champ de recherche (en main, plein écran), puis grille d'icônes par section : 3 colonnes en portrait, 5 en paysage PETIT, 6 en plein écran.
    En-tête de section : barre d'accent, titre, nombre d'apps et filet, sur un bandeau léger (lisible sur tout fond d'écran).
    Recherche : filtre sur le nom de l'app (uiNamespace COMSPEC_ATAK_LauncherFilter), redessin 0,25 s après la frappe,
    le champ garde le focus et le curseur en fin de texte. Entrée ouvre la première app trouvée.
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
private _cols = _l get "cols";
private _pad = (_l get "pad") * 2;
private _font = _l get "fontSmall";
private _ratio = (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]);
([] call comspec_atak_native_fnc_accent) params ["_acc", "_accHex"];
private _tw = (_bw - 0.012 - _pad * (_cols + 1)) / _cols;
private _th = _tw * _ratio * ([0.92, 0.8] select (_l get "mini")); // mode PETIT : tuiles moins hautes, une rangée de plus visible
private _secH = _font * 1.6;
private _gw = _bw - 0.012 - 2 * _pad;
private _apps = ([] call comspec_atak_native_fnc_appList) select { [_x] call comspec_atak_native_fnc_appVisible };
private _interactive = (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["interactive", false];
// Mode PETIT : pas de champ de recherche (il prenait le sixième de l'écran), les icônes remontent.
private _mini = _l get "mini";
private _searchOn = _interactive && {!_mini} && {profileNamespace getVariable ["COMSPEC_ATAK_LauncherSearch", true]};
private _filter = [uiNamespace getVariable ["COMSPEC_ATAK_LauncherFilter", ""], ""] select !_searchOn;
private _y = _pad;

// Recherche
if (_searchOn) then {
    private _sh = _font * 1.7;
    private _sbg = ["COMSPEC_RscText", [_pad, _y, _gw, _sh]] call comspec_atak_native_fnc_pageCtrl;
    _sbg ctrlSetBackgroundColor [0.05, 0.06, 0.055, 0.82];
    _sbg ctrlEnable false;
    private _ic = ["COMSPEC_RscPicture", [_pad * 1.6, _y + _sh * 0.2, _sh * 0.6 / _ratio, _sh * 0.6], "\z\comspec_atak_native\addons\main\data\map_zoomin.paa"] call comspec_atak_native_fnc_pageCtrl;
    _ic ctrlSetTextColor [0.62, 0.7, 0.66, 1];
    _ic ctrlEnable false;
    private _ex = _pad * 2.4 + _sh * 0.6 / _ratio;
    private _hint = ["COMSPEC_RscText", [_ex, _y, _gw - _ex, _sh], "Rechercher une app"] call comspec_atak_native_fnc_pageCtrl;
    _hint ctrlSetFontHeight (_font * 0.95);
    _hint ctrlSetTextColor [0.5, 0.57, 0.53, 1];
    _hint ctrlEnable false;
    _hint ctrlShow (_filter isEqualTo "");
    private _ed = ["COMSPEC_RscEdit", [_ex, _y + _sh * 0.1, _gw - _ex - _sh * 1.2, _sh * 0.8], _filter] call comspec_atak_native_fnc_pageCtrl;
    _ed ctrlSetBackgroundColor [0, 0, 0, 0];
    _ed ctrlSetFontHeight (_font * 0.95);
    _ed setVariable ["hint", _hint];
    _ed ctrlAddEventHandler ["KeyUp", {
        params ["_e", "_key"];
        (_e getVariable ["hint", controlNull]) ctrlShow ((ctrlText _e) isEqualTo "");
        if ((ctrlText _e) isEqualTo (uiNamespace getVariable ["COMSPEC_ATAK_LauncherFilter", ""]) && {!(_key in [0x1C, 0x9C])}) exitWith {};
        uiNamespace setVariable ["COMSPEC_ATAK_LauncherFilter", ctrlText _e];
        // Entrée : ouvre la première app trouvée.
        if (_key in [0x1C, 0x9C]) exitWith {
            private _f = toLower trim (ctrlText _e);
            // Premier résultat affiché pour ce texte (relevé au rendu), sinon même recherche que la liste : nom source,
            // section ou nom traduit (en anglais, « settings » ne trouvait rien : seul le nom français était testé).
            (uiNamespace getVariable ["COMSPEC_ATAK_LauncherFirst", ["", ""]]) params [["_ff", ""], ["_fp", ""]];
            private _hitPage = if ((toLower trim _ff) isEqualTo _f && {_fp isNotEqualTo ""}) then { _fp } else {
                // Même base que le rendu : puce ÉPINGLÉES ou section choisie (Entrée avant le redessin différé).
                private _all = ([] call comspec_atak_native_fnc_appList) select { [_x] call comspec_atak_native_fnc_appVisible };
                private _sec = uiNamespace getVariable ["COMSPEC_ATAK_LauncherSection", ""];
                private _base = _all;
                if (_sec isEqualTo "*PIN") then {
                    private _pins = profileNamespace getVariable ["COMSPEC_ATAK_PinnedApps", []];
                    if !(_pins isEqualType []) then { _pins = []; };
                    _base = _pins apply { private _p = _x; (_all select { (_x get "page") isEqualTo _p }) param [0, createHashMap] };
                    _base = _base select { (count _x) > 0 };
                } else {
                    if (_sec isNotEqualTo "") then { _base = _all select { (_x get "section") isEqualTo _sec }; };
                };
                // Puce obsolète (aucune app) : le rendu repasse sur TOUT.
                if ((count _base) isEqualTo 0) then { _base = _all; };
                ((_base select { ((toLower (_x get "name")) find _f) >= 0 || {((toLower (_x get "section")) find _f) >= 0} || {((toLower ((_x get "name") call comspec_atak_native_fnc_tr)) find _f) >= 0} }) param [0, createHashMap]) getOrDefault ["page", ""]
            };
            if (_hitPage isNotEqualTo "" && {_f isNotEqualTo ""}) then {
                uiNamespace setVariable ["COMSPEC_ATAK_LauncherFilter", ""];
                [{ [_this] call comspec_atak_native_fnc_navigate; }, _hitPage] call CBA_fnc_execNextFrame;
            };
        };
        private _tok = diag_tickTime;
        uiNamespace setVariable ["COMSPEC_ATAK_LauncherTok", _tok];
        [{
            params ["_tok"];
            if ((uiNamespace getVariable ["COMSPEC_ATAK_LauncherTok", -1]) isNotEqualTo _tok) exitWith {};
            if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["pageDrawn", ""]) isNotEqualTo "LAUNCHER") exitWith {};
            ["LAUNCHER"] call comspec_atak_native_fnc_pageRender;
            // Le champ recréé reprend le focus, curseur en fin de texte.
            private _e2 = uiNamespace getVariable ["COMSPEC_ATAK_LauncherEdit", controlNull];
            if (!isNull _e2) then { ctrlSetFocus _e2; _e2 ctrlSetTextSelection [count (ctrlText _e2), 0]; };
        }, [_tok], 0.25] call CBA_fnc_waitAndExecute;
    }];
    uiNamespace setVariable ["COMSPEC_ATAK_LauncherEdit", _ed];
    if (_filter isNotEqualTo "") then {
        private _clr = ["COMSPEC_RscButtonOverlay", [_pad + _gw - _sh * 1.1, _y, _sh * 1.1, _sh], "×"] call comspec_atak_native_fnc_pageCtrl;
        _clr ctrlSetFontHeight _font;
        _clr ctrlSetTooltip (("Effacer la recherche") call comspec_atak_native_fnc_tr);
        _clr ctrlAddEventHandler ["ButtonClick", { uiNamespace setVariable ["COMSPEC_ATAK_LauncherFilter", ""]; [{ ["LAUNCHER"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }];
    };
    _y = _y + _sh + _pad;
};

// Filtres (en main, hors mode PETIT) : TOUT, ÉPINGLÉES, une puce par section.
// Retour de jeu : puces trop petites (texte à 0,8 x fontSmall, sous le plancher des légendes : le trait d'un « I »
// d'un pixel disparaissait, « COMMUN CATION »). Grille régulière de deux rangées (5 + 5 pour 10 filtres), puces plus
// hautes, texte en fontSmall ; une rangée de plus seulement si un libellé ne tient pas dans sa case.
private _pins = profileNamespace getVariable ["COMSPEC_ATAK_PinnedApps", []];
if !(_pins isEqualType []) then { _pins = []; };
private _secSel = [uiNamespace getVariable ["COMSPEC_ATAK_LauncherSection", ""], ""] select !_searchOn;
private _sections = [];
{ _sections pushBackUnique (_x get "section"); } forEach _apps;
if (_secSel isNotEqualTo "" && {_secSel isNotEqualTo "*PIN"} && {!(_secSel in _sections)}) then { _secSel = ""; };
if (_secSel isEqualTo "*PIN" && {(count _pins) isEqualTo 0}) then { _secSel = ""; };
if (_searchOn) then {
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
        private _c = ["COMSPEC_RscButton", [_cx, _cy, _cw, _ch]] call comspec_atak_native_fnc_pageCtrl;
        _c ctrlSetText _txt; // i18n:brut (déjà traduit)
        // Libellé encore trop long pour sa case (deux colonnes en portrait) : réduit, jamais sous le plancher.
        _c ctrlSetFontHeight ((_cf * ((_cw / _maxW) min 1)) max COMSPEC_ATAK_FontMin);
        _c ctrlSetBackgroundColor ([[0.09, 0.11, 0.1, 0.9], ((_acc select [0, 3]) apply { _x * 0.5 }) + [1]] select _on);
        _c ctrlSetTextColor ([[0.78, 0.84, 0.81, 1], [1, 1, 1, 1]] select _on);
        _c setVariable ["key", _key];
        _c ctrlAddEventHandler ["ButtonClick", {
            params ["_c"];
            private _k = _c getVariable ["key", ""];
            uiNamespace setVariable ["COMSPEC_ATAK_LauncherSection", [_k, ""] select (_k isEqualTo (uiNamespace getVariable ["COMSPEC_ATAK_LauncherSection", ""]))];
            [{ ["LAUNCHER"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
        }];
    } forEach _chips;
    _y = _y + (ceil (_n / _cols)) * (_ch + _gap) - _gap + _pad;
    if ((count _pins) isEqualTo 0 && {_filter isEqualTo ""}) then {
        private _hint = ["COMSPEC_RscText", [_pad, _y - _pad * 0.4, _gw, _font * 1.2], "Astuce : clic droit sur une app pour l'épingler ou l'ajouter à un groupe"] call comspec_atak_native_fnc_pageCtrl;
        _hint ctrlSetFontHeight ((_font * 0.8) max COMSPEC_ATAK_FontMin);
        _hint ctrlSetTextColor [0.6, 0.68, 0.64, 1];
        _hint ctrlEnable false;
        _y = _y + _font * 1.2;
    };
};

// Feuille d'actions (clic droit sur une app, ou création de groupe).
private _sheetPage = uiNamespace getVariable ["COMSPEC_ATAK_AppSheet", ""];
private _sheetMode = uiNamespace getVariable ["COMSPEC_ATAK_AppSheetMode", ""];
if (_interactive && {(_sheetPage isNotEqualTo "") || {_sheetMode in ["new", "pick"]}}) then {
    private _sheetH = _font * 7.2;
    private _sbg = ["COMSPEC_RscText", [_pad, _y, _gw, _sheetH]] call comspec_atak_native_fnc_pageCtrl;
    _sbg ctrlSetBackgroundColor [0.05, 0.07, 0.065, 0.96];
    _sbg ctrlEnable false;
    private _by = _y + _font * 1.8;
    private _bw3 = (_gw - _pad * 2.8) / 3;
    private _mkBtn = {
        params ["_x0", "_label", "_code", ["_pri", false]];
        private _b = [["COMSPEC_RscButton", "COMSPEC_RscButtonPrimary"] select _pri, [_x0, _by, _bw3, _font * 1.6], _label] call comspec_atak_native_fnc_pageCtrl;
        _b ctrlSetFontHeight (_font * 0.85);
        if (!_pri) then { _b ctrlSetBackgroundColor [0.12, 0.145, 0.13, 1]; };
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
        _st ctrlSetStructuredText parseText format ["<t font='COMSPEC_BarlowBold'>%1</t><br/><t size='0.8' color='#8a9a93'>Que faire avec cette app ?</t>", _appNm];
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
            [{ ["LAUNCHER"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
        }, false] call _mkBtn;
        [_pad * 1.4 + _bw3 + _pad * 0.4, "Au groupe…", {
            uiNamespace setVariable ["COMSPEC_ATAK_AppSheetMode", "pick"];
            [{ ["LAUNCHER"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
        }, true] call _mkBtn;
        [_pad * 1.4 + 2 * (_bw3 + _pad * 0.4), "Fermer", {
            uiNamespace setVariable ["COMSPEC_ATAK_AppSheet", ""];
            uiNamespace setVariable ["COMSPEC_ATAK_AppSheetMode", ""];
            [{ ["LAUNCHER"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
        }, false] call _mkBtn;
    } else {
        if (_mode isEqualTo "new") then {
            private _st = ["COMSPEC_RscStructuredText", [_pad * 1.4, _y + _pad * 0.4, _gw - _pad * 2, _font * 1.5]] call comspec_atak_native_fnc_pageCtrl;
            _st ctrlSetStructuredText parseText "<t font='COMSPEC_BarlowBold'>Nouveau groupe</t><br/><t size='0.8' color='#8a9a93'>Donnez un nom court (ex. Terrain, Ops).</t>";
            _st ctrlEnable false;
        };
    };
    if (_mode isEqualTo "pick") then {
        private _folders = ["list"] call comspec_atak_native_fnc_appFolders;
        private _fy = _by + _font * 1.9;
        private _flbl = ["COMSPEC_RscText", [_pad * 1.4, _fy, _gw - _pad * 2, _font * 1.1], "Choisir un groupe :"] call comspec_atak_native_fnc_pageCtrl;
        _flbl ctrlSetFontHeight (_font * 0.85);
        _flbl ctrlSetTextColor [0.7, 0.78, 0.74, 1];
        _flbl ctrlEnable false;
        _fy = _fy + _font * 1.2;
        {
            _x params ["_fid", "_fnm"];
            private _fb = ["COMSPEC_RscButton", [_pad * 1.4, _fy, _gw - _pad * 2.8, _font * 1.45], _fnm] call comspec_atak_native_fnc_pageCtrl;
            _fb ctrlSetFontHeight (_font * 0.9);
            _fb ctrlSetBackgroundColor [0.1, 0.13, 0.12, 1];
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
                [{ ["LAUNCHER"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
            }];
            _fy = _fy + _font * 1.55;
        } forEach _folders;
        private _nb = ["COMSPEC_RscButtonPrimary", [_pad * 1.4, _fy, _gw - _pad * 2.8, _font * 1.45], "+ Nouveau groupe"] call comspec_atak_native_fnc_pageCtrl;
        _nb ctrlSetFontHeight (_font * 0.9);
        _nb ctrlAddEventHandler ["ButtonClick", {
            uiNamespace setVariable ["COMSPEC_ATAK_AppSheetMode", "new"];
            [{ ["LAUNCHER"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
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
                [{ ["LAUNCHER"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
            }];
            private _cx = ["COMSPEC_RscButton", [_pad * 1.4 + _gw * 0.75, _ny, _gw * 0.16, _font * 1.5], "Annuler"] call comspec_atak_native_fnc_pageCtrl;
            _cx ctrlSetFontHeight (_font * 0.85);
            _cx ctrlSetBackgroundColor [0.12, 0.145, 0.13, 1];
            _cx ctrlAddEventHandler ["ButtonClick", {
                uiNamespace setVariable ["COMSPEC_ATAK_AppSheet", ""];
                uiNamespace setVariable ["COMSPEC_ATAK_AppSheetMode", ""];
                [{ ["LAUNCHER"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
            }];
            _sbg ctrlSetPosition [_pad, _y, _gw, _font * 5.2];
            _sbg ctrlCommit 0;
            _y = _y + _font * 5.4;
        } else {
            _y = _y + _sheetH + _pad;
        };
    };
};

// Groupes : [clé de repli, titre, apps]. Recherche ou filtre : un seul groupe ; sinon épinglées, récentes, sections.
private _byPage = createHashMap;
{ _byPage set [_x get "page", _x]; } forEach _apps;
private _pinApps = (_pins apply { _byPage getOrDefault [_x, createHashMap] }) select { (count _x) > 0 };
private _groups = [];
switch (true) do {
    case (_filter isNotEqualTo ""): {
        private _f = toLower trim _filter;
        private _base = [_apps, _pinApps] select (_secSel isEqualTo "*PIN");
        if (_secSel isNotEqualTo "" && {_secSel isNotEqualTo "*PIN"}) then { _base = _apps select { (_x get "section") isEqualTo _secSel }; };
        _groups pushBack ["", format ["Résultats pour « %1 »", _filter], _base select { ((toLower (_x get "name")) find _f) >= 0 || {((toLower (_x get "section")) find _f) >= 0} || {((toLower ((_x get "name") call comspec_atak_native_fnc_tr)) find _f) >= 0} }];
        // Entrée dans la recherche : ouvre ce premier résultat affiché.
        uiNamespace setVariable ["COMSPEC_ATAK_LauncherFirst", [_filter, ((((_groups select 0) select 2) param [0, createHashMap]) getOrDefault ["page", ""])]];
    };
    case (_secSel isEqualTo "*PIN"): { _groups pushBack ["", "Épinglées", _pinApps]; };
    case (_secSel isEqualTo "*FOLD"): { _groups pushBack ["*FOLD", "Mes groupes", []]; };
    case (_secSel isNotEqualTo ""): { _groups pushBack ["", _secSel, _apps select { (_x get "section") isEqualTo _secSel }]; };
    default {
        if ((count _pinApps) > 0) then { _groups pushBack ["*PIN", "Épinglées", _pinApps]; };
        private _rec = profileNamespace getVariable ["COMSPEC_ATAK_AppRecent", []];
        if !(_rec isEqualType []) then { _rec = []; };
        private _recApps = ((_rec apply { _byPage getOrDefault [_x, createHashMap] }) select { (count _x) > 0 && {!((_x get "page") in _pins)} && {!(_x getOrDefault ["wip", false])} }) select [0, _cols];
        if ((count _recApps) > 0) then { _groups pushBack ["*REC", "Récentes", _recApps]; };
        // Groupes joueur : toujours visibles en tête (même vides → bouton créer).
        _groups pushBack ["*FOLD", "Mes groupes", []];
        { private _sec = _x; _groups pushBack [_sec, _sec, _apps select { (_x get "section") isEqualTo _sec }]; } forEach _sections;
    };
};
private _folded = profileNamespace getVariable ["COMSPEC_ATAK_LauncherFolded", []];
if !(_folded isEqualType []) then { _folded = []; };
private _seen = profileNamespace getVariable ["COMSPEC_ATAK_AppSeen", []];
if !(_seen isEqualType []) then { _seen = []; };
// Fondu d'arrivée : seulement en revenant sur l'accueil (pas à chaque frappe de recherche ni rafraîchissement).
private _fadeIn = (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["pagePrev", ""]) isNotEqualTo "LAUNCHER") && {["anim"] call comspec_atak_native_fnc_fx};
private _rowN = 0;
// Pastilles calculées une fois par app (en-tête de section replié puis tuile, avant : deux fois).
private _badgeOf = createHashMap;
{ _badgeOf set [_x get "page", [_x get "page"] call comspec_atak_native_fnc_appBadge]; } forEach _apps;
private _chev = "\z\comspec_atak_native\addons\main\data\%1.paa";
{
    _x params ["_key", "_sec", "_items"];
    private _isFolded = _key isNotEqualTo "" && {_key in _folded};
    private _unread = 0;
    { _unread = _unread + (_badgeOf getOrDefault [_x get "page", 0]); } forEach _items;
    // En-tête : bandeau, barre d'accent, titre, nombre (et non-lus si repliée), chevron, filet ; cliquable pour replier.
    private _band = ["COMSPEC_RscText", [_pad, _y, _gw, _secH]] call comspec_atak_native_fnc_pageCtrl;
    _band ctrlSetBackgroundColor ([[0.03, 0.04, 0.035, 0.55], [0.06, 0.075, 0.068, 0.75]] select _isFolded);
    _band ctrlEnable false;
    private _bar = ["COMSPEC_RscText", [_pad, _y + _secH * 0.22, pixelW * 3, _secH * 0.56]] call comspec_atak_native_fnc_pageCtrl;
    _bar ctrlSetBackgroundColor ([_acc, [0.55, 0.62, 0.58, 1]] select (_key in ["*REC"]));
    _bar ctrlEnable false;
    private _extra = if (_isFolded && {_unread > 0}) then { format ["  <t size='0.75' color='#f2ab33'>● %1 non lu(s)</t>", _unread] } else { "" };
    private _cnt = if (_key isEqualTo "*FOLD") then { count (["list"] call comspec_atak_native_fnc_appFolders) } else { count _items };
    private _lbl = ["COMSPEC_RscStructuredText", [_pad * 1.6, _y + _secH * 0.12, _gw - _pad - _secH * 1.4, _secH]] call comspec_atak_native_fnc_pageCtrl;
    _lbl ctrlSetStructuredText parseText ((format ["<t size='0.85' font='COMSPEC_BarlowBold' color='#f2f6f3'>%1</t>  <t size='0.75' color='#b4c1bb'>%2</t>%3", toUpper _sec, _cnt, _extra]) call comspec_atak_native_fnc_tr);
    _lbl ctrlEnable false;
    private _line = ["COMSPEC_RscText", [_pad, _y + _secH - pixelH, _gw, pixelH]] call comspec_atak_native_fnc_pageCtrl;
    _line ctrlSetBackgroundColor [1, 1, 1, 0.08];
    _line ctrlEnable false;
    if (_key isNotEqualTo "") then {
        private _cs = _secH * 0.5;
        private _cw = _cs / _ratio;
        private _cv = ["COMSPEC_RscPicture", [_pad + _gw - _cw - _pad * 0.6, _y + (_secH - _cs) / 2, _cw, _cs], format [_chev, ["ui_chevron_up", "ui_chevron_down"] select _isFolded]] call comspec_atak_native_fnc_pageCtrl;
        _cv ctrlSetTextColor [0.85, 0.9, 0.87, 1];
        _cv ctrlEnable false;
        private _hb = ["COMSPEC_RscButtonOverlay", [_pad, _y, _gw, _secH]] call comspec_atak_native_fnc_pageCtrl;
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
            [{ ["LAUNCHER"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
        }];
    };
    _y = _y + _secH + _pad * 0.6;
    if (!_isFolded) then {
        // Section Mes groupes : tuiles dossier + bouton créer (pas la grille d'apps classique).
        if (_key isEqualTo "*FOLD") then {
            private _folders = ["list"] call comspec_atak_native_fnc_appFolders;
            private _tiles = _folders apply {
                _x params ["_fid", "_fnm", "_fpages"];
                createHashMapFromArray [
                    ["page", "FOLDER"],
                    ["icon", "\z\comspec_atak_native\addons\main\data\app_folder.paa"],
                    ["name", format ["%1 (%2)", _fnm, count _fpages]],
                    ["folderId", _fid],
                    ["wip", false],
                    ["new", false]
                ]
            };
            // Tuile « Nouveau groupe »
            _tiles pushBack (createHashMapFromArray [
                ["page", "*NEWFOLDER*"],
                ["icon", "\z\comspec_atak_native\addons\main\data\app_folder.paa"],
                ["name", "Nouveau groupe"],
                ["folderId", ""],
                ["wip", false],
                ["new", false]
            ]);
            {
                private _col = _forEachIndex mod _cols;
                private _row = floor (_forEachIndex / _cols);
                private _tile = [_d, _g, [_pad + _col * (_tw + _pad), _y + _row * (_th + _pad), _tw, _th], _x get "icon", _x get "name",
                    0, "FOLDER", _font, false, false, false] call comspec_atak_native_fnc_tileCreate;
                private _btn = _tile select ((count _tile) - 1);
                _btn setVariable ["folderId", _x getOrDefault ["folderId", ""]];
                _btn setVariable ["isNewFolder", (_x get "page") isEqualTo "*NEWFOLDER*"];
                // Remplace le clic gauche (navigate FOLDER générique) : ouvre le bon groupe.
                _btn ctrlRemoveAllEventHandlers "ButtonClick";
                _btn ctrlAddEventHandler ["ButtonClick", {
                    params ["_c"];
                    if (_c getVariable ["isNewFolder", false]) then {
                        uiNamespace setVariable ["COMSPEC_ATAK_AppSheet", ""];
                        uiNamespace setVariable ["COMSPEC_ATAK_AppSheetMode", "new"];
                        [{ ["LAUNCHER"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
                    } else {
                        uiNamespace setVariable ["COMSPEC_ATAK_FolderId", _c getVariable ["folderId", ""]];
                        ["FOLDER"] call comspec_atak_native_fnc_navigate;
                    };
                }];
                private _list = uiNamespace getVariable ["COMSPEC_ATAK_PageControls", []];
                _list append _tile;
                uiNamespace setVariable ["COMSPEC_ATAK_PageControls", _list];
            } forEach _tiles;
            private _rows = ceil ((count _tiles) / _cols);
            _rowN = _rowN + _rows;
            _y = _y + _rows * (_th + _pad) + _pad * 0.6;
        } else {
            if ((count _items) isEqualTo 0) then {
                private _none = ["COMSPEC_RscText", [_pad, _y, _gw, _font * 1.4], "Aucune app ne correspond."] call comspec_atak_native_fnc_pageCtrl;
                _none ctrlSetFontHeight _font;
                _none ctrlSetTextColor [0.55, 0.62, 0.58, 1];
                _y = _y + _font * 1.4 + _pad;
            };
            {
                private _col = _forEachIndex mod _cols;
                private _row = floor (_forEachIndex / _cols);
                private _page = _x get "page";
                private _wip = _x getOrDefault ["wip", false];
                private _tile = [_d, _g, [_pad + _col * (_tw + _pad), _y + _row * (_th + _pad), _tw, _th], _x get "icon", _x get "name",
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
                    [{ ["LAUNCHER"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
                }];
                if (_fadeIn) then {
                    private _delay = ((_rowN + _row) min 6) * 0.03;
                    { _x ctrlSetFade 1; _x ctrlCommit 0; _x ctrlSetFade 0; _x ctrlCommit (0.14 + _delay); } forEach _tile;
                };
                private _list = uiNamespace getVariable ["COMSPEC_ATAK_PageControls", []];
                _list append _tile;
                uiNamespace setVariable ["COMSPEC_ATAK_PageControls", _list];
            } forEach _items;
            private _rows = ceil ((count _items) / _cols);
            _rowN = _rowN + _rows;
            _y = _y + _rows * (_th + _pad) + _pad * 0.6;
        };
    } else {
        _y = _y + _pad * 0.2;
    };
} forEach _groups;
true
