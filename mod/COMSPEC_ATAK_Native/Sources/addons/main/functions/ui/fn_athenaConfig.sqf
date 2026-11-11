/*
    Configuration ATHENA : un seul bouton dans le menu Échap (logo Athena, pastille d'état de la liaison) qui ouvre la
    fenêtre « Configuration ATHENA » : état de la liaison et du service Athéna, versions des mods, puis une ligne par
    entrée du registre (diagnostic de la liaison, réalisme et réseau, SSE…). Remplace la barre COMSPEC d'Overwatch
    (état, Dépannage liaison, Service Athéna), le bouton Réalisme et réseau et le bouton SSE ROLEPLAY · Extensions.

    FICHIER PARTAGÉ, copie identique dans deux mods (toute retouche se fait dans les deux) :
      ATAK natif  main/functions/ui/fn_athenaConfig.sqf          comspec_atak_native_fnc_athenaConfig
      SSE         extensions/functions/fn_athenaConfig.sqf       comspec_sse_fnc_athenaConfig
    Une seule copie sert, celle de l'hôte, décrit au preInit dans comspec_athena_config_host (missionNamespace) :
      HashMap "fn" (cette fonction), "tr" (Code : texte français -> langue du joueur), "logo" (logo de la fenêtre),
      "icon" / "iconHover" (logo du bouton, survol), "dot" (pastille ronde teintée), "row" (classe du bouton
      transparent posé sur chaque ligne).
    L'ATAK natif est toujours l'hôte quand il est chargé ; le SSE ne l'est que sans l'ATAK natif. Les deux mods
    chargés : un seul bouton (le SSE ne s'accroche pas au menu Échap).

    Registre (contrat) : comspec_athena_config_entries (missionNamespace), rempli au preInit par chaque mod.
      Entrée : [id, titre, description, icône, condition, action, ordre, mode]
        id           String unique ; une entrée de même id remplace la précédente
        titre        String, ou Code -> String (texte déjà traduit, relu à chaque ouverture)
        description  String, ou Code -> String : une phrase
        icône        chemin .paa, ou tableau de chemins (le premier qui existe ; aucun : logo du bouton)
        condition    Code -> Boolean : entrée affichée. Aucune entrée affichée : pas de bouton dans le menu Échap.
        action       Code, reçoit [écran parent] selon le mode
        ordre        Number, croissant
        mode         "child" : la fenêtre reste ouverte, l'action ouvre son écran par-dessus (parent = la fenêtre)
                     "close" : la fenêtre se ferme, le menu Échap reste ouvert (parent = le menu Échap)
                     "menu"  : la fenêtre et le menu Échap se ferment (parent = displayNull)
      Ajout, au preInit :
        private _r = (missionNamespace getVariable ["comspec_athena_config_entries", []]) select { (_x select 0) isNotEqualTo "mon_id" };
        _r pushBack ["mon_id", { "Titre" call mon_tr }, { "Une phrase." call mon_tr }, "\chemin\icone_ca.paa", { true }, { params ["_parent"]; ... }, 50, "child"];
        missionNamespace setVariable ["comspec_athena_config_entries", _r];
      État (facultatif) : comspec_athena_config_status, Code -> [niveau, lignes, id mis en avant]
        niveau "ok" | "warn" | "err" | "off" (couleur de la pastille) ; lignes : textes traduits (en-tête, info-bulle) ;
        id : entrée qui prend le focus et le liseré de couleur (ex. le diagnostic quand la liaison est coupée).
        Posé par Overwatch connect (liaison Athena, service Athéna).

    Sûreté : aucun écran n'est fermé dans le gestionnaire d'un de ses contrôles. Un clic note l'entrée, l'action part
    à l'image suivante (CBA_fnc_execNextFrame) ; aucune attente bloquante ; les boucles d'affichage tournent en spawn
    et s'arrêtent avec leur écran. Aucune alerte de l'interface d'Arma.

    Params : ["button", menu Échap] | ["open", écran parent] | ["run", id] | ["entries"] -> entrées affichées |
             ["status"] -> [niveau, lignes, id] | ["place", groupe du bouton] | ["refresh", groupe du bouton] |
             ["hover", bouton transparent, survolé ?] | ["header", fenêtre]
*/
params [["_mode", ""], ["_a", displayNull], ["_b", false]];
disableSerialization;
private _host = missionNamespace getVariable ["comspec_athena_config_host", createHashMap];
if !(_host isEqualType createHashMap) exitWith {};
private _self = _host getOrDefault ["fn", {}];
private _T = {
    private _f = (missionNamespace getVariable ["comspec_athena_config_host", createHashMap]) getOrDefault ["tr", {}];
    private _r = _this call _f;
    if (isNil "_r" || {!(_r isEqualType "")}) then { _this } else { _r }
};
// Titre ou description d'une entrée : texte, ou code qui le rend.
private _txt = {
    if (_this isEqualType "") exitWith { _this };
    if !(_this isEqualType {}) exitWith { "" };
    private _r = call _this;
    if (isNil "_r" || {!(_r isEqualType "")}) then { "" } else { _r }
};
// Texte posé dans un texte structuré : < > & échappés.
private _esc = { ((_this splitString "&") joinString "&amp;" splitString "<") joinString "&lt;" splitString ">" joinString "&gt;" };
private _lvlColor = {
    switch (_this) do {
        case "ok": { [0.204, 0.827, 0.6, 1] };
        case "warn": { [0.96, 0.62, 0.04, 1] };
        case "err": { [0.94, 0.27, 0.27, 1] };
        default { [0.55, 0.6, 0.58, 1] };
    }
};

switch (_mode) do {
    case "entries": {
        private _ok = (missionNamespace getVariable ["comspec_athena_config_entries", []]) select {
            _x isEqualType [] && {(count _x) >= 8} && {(_x select 0) isEqualType ""} && {(_x select 4) isEqualType {}} && {(_x select 5) isEqualType {}}
            && { private _c = call (_x select 4); !isNil "_c" && {_c isEqualTo true} }
        };
        [_ok, [], { private _o = _x select 6; [100, _o] select (_o isEqualType 0) }, "ASCEND"] call BIS_fnc_sortBy
    };

    case "status": {
        private _f = missionNamespace getVariable ["comspec_athena_config_status", 0];
        private _s = if (_f isEqualType {}) then { call _f } else { [] };
        if (isNil "_s" || {!(_s isEqualType [])}) then { _s = []; };
        _s params [["_lvl", "", [""]], ["_lines", [], [[]]], ["_focus", "", [""]]];
        [_lvl, _lines select { _x isEqualType "" && {_x isNotEqualTo ""} }, _focus]
    };

    // Bouton du menu Échap (Extended_DisplayLoad_EventHandlers de RscDisplayInterrupt et RscDisplayMPInterrupt).
    case "button": {
        private _d = _a;
        if (!hasInterface || {!(_d isEqualType displayNull)} || {isNull _d}) exitWith {};
        // Réouverture du menu : l'ancien bouton est retiré ici, jamais dans son propre gestionnaire.
        private _old = _d displayCtrl 93960;
        if (!isNull _old) then { ctrlDelete _old; };
        if ((["entries"] call _self) isEqualTo []) exitWith {};
        // Hauteur tirée de l'écran, bornée en pixels : lisible en très petite interface, jamais énorme en très grande.
        private _h = ((0.034 * safeZoneH) max (24 * pixelH)) min (44 * pixelH);
        private _ux = pixelW / pixelH; // longueur verticale -> même longueur à l'écran en largeur (16:9, 21:9, 4:3)
        private _pad = _h * 0.28 * _ux;
        private _ih = _h * 0.72;
        private _iw = _ih * _ux;
        private _dh = _h * 0.24;
        private _dw = _dh * _ux;

        private _grp = _d ctrlCreate ["ctrlControlsGroupNoScrollbars", 93960];
        private _bg = _d ctrlCreate ["ctrlStatic", -1, _grp];
        _bg ctrlSetBackgroundColor [0.035, 0.043, 0.04, 0.94];
        private _bar = _d ctrlCreate ["ctrlStatic", -1, _grp];
        _bar ctrlSetBackgroundColor [0.204, 0.827, 0.6, 1];
        private _icon = _d ctrlCreate ["ctrlStaticPictureKeepAspect", -1, _grp];
        _icon ctrlSetText (_host getOrDefault ["icon", ""]);
        private _lb = _d ctrlCreate ["ctrlStatic", -1, _grp];
        _lb ctrlSetFont "RobotoCondensedBold";
        _lb ctrlSetFontHeight (_h * 0.5);
        _lb ctrlSetTextColor [0.93, 0.95, 0.94, 1];
        _lb ctrlSetText ("ATHENA Configuration" call _T);
        private _tw = (ctrlTextWidth _lb) + 4 * pixelW;
        private _dot = _d ctrlCreate ["ctrlStaticPictureKeepAspect", -1, _grp];
        _dot ctrlSetText (_host getOrDefault ["dot", ""]);
        private _ov = _d ctrlCreate [_host getOrDefault ["row", "ctrlButton"], -1, _grp];

        private _xl = _pad + _iw + _pad * 0.7;
        private _xd = _xl + _tw + _pad * 0.7;
        private _w = _xd + _dw + _pad;
        {
            _x params ["_c", "_p"];
            _c ctrlSetPosition _p;
            _c ctrlCommit 0;
        } forEach [
            [_grp, [safeZoneX + safeZoneW - _w - 10 * pixelW, safeZoneY + 10 * pixelH, _w, _h]],
            [_bg, [0, 0, _w, _h]],
            [_bar, [0, 0, 2 * pixelW, _h]],
            [_icon, [_pad, (_h - _ih) / 2, _iw, _ih]],
            [_lb, [_xl, 0, _tw, _h]],
            [_dot, [_xd, (_h - _dh) / 2, _dw, _dh]],
            [_ov, [0, 0, _w, _h]]
        ];
        _ov ctrlSetText "";
        _ov setVariable ["comspec_parts", [_bg, _icon, _host getOrDefault ["icon", ""], _host getOrDefault ["iconHover", ""], [0.035, 0.043, 0.04, 0.94], [0.07, 0.12, 0.10, 0.97]]];
        _grp setVariable ["comspec_parts", [_dot, _ov]];
        _ov ctrlAddEventHandler ["ButtonClick", {
            params ["_c"];
            [{
                params ["_d"];
                if (isNull _d) exitWith {};
                ["open", _d] call ((missionNamespace getVariable ["comspec_athena_config_host", createHashMap]) getOrDefault ["fn", {}]);
            }, [ctrlParent _c]] call CBA_fnc_execNextFrame;
        }];
        {
            _ov ctrlAddEventHandler [_x select 0, format ["['hover', _this select 0, %1] call ((missionNamespace getVariable ['comspec_athena_config_host', createHashMap]) getOrDefault ['fn', {}])", _x select 1]];
        } forEach [["MouseEnter", true], ["MouseExit", false], ["SetFocus", true], ["KillFocus", false]];

        ["place", _grp] call _self;
        ["refresh", _grp] call _self;
        // Place revue pendant la première seconde (les autres mods posent leurs contrôles après nous), puis pastille et
        // info-bulle suivies chaque seconde tant que le menu est ouvert.
        [_grp] spawn {
            params ["_grp"];
            disableSerialization;
            private _n = 0;
            while { !isNull _grp } do {
                uiSleep ([0.05, 0.3, 0.7, 1] select (_n min 3));
                if (isNull _grp) exitWith {};
                private _fn = (missionNamespace getVariable ["comspec_athena_config_host", createHashMap]) getOrDefault ["fn", {}];
                if (_n < 3) then { ["place", _grp] call _fn; };
                ["refresh", _grp] call _fn;
                _n = _n + 1;
            };
        };
    };

    // Place libre : contrôles visibles du menu (vanilla, ACE, CBA, autres mods) relevés à l'écran, puis premier
    // emplacement libre le long du bord haut (de droite à gauche), du bord droit, du bord bas, puis du bord gauche.
    case "place": {
        private _grp = _a;
        if (!(_grp isEqualType controlNull) || {isNull _grp}) exitWith {};
        private _d = ctrlParent _grp;
        if (isNull _d) exitWith {};
        (ctrlPosition _grp) params ["", "", "_w", "_h"];
        private _scr = safeZoneW * safeZoneH;
        private _busy = [];
        {
            private _c = _x;
            if (_c isEqualTo _grp || {(ctrlParentControlsGroup _c) isEqualTo _grp} || {(ctrlType _c) isEqualTo 15}) then { continue };
            // Contrôle d'un groupe : position relative au groupe (défilement ignoré), groupe masqué = contrôle masqué.
            private _p = +(ctrlPosition _c);
            private _vis = ctrlShown _c && {(ctrlFade _c) < 0.95};
            private _g = ctrlParentControlsGroup _c;
            private _guard = 0;
            while { !isNull _g && {_guard < 8} } do {
                private _gp = ctrlPosition _g;
                _p set [0, (_p select 0) + (_gp select 0)];
                _p set [1, (_p select 1) + (_gp select 1)];
                _vis = _vis && {ctrlShown _g};
                _g = ctrlParentControlsGroup _g;
                _guard = _guard + 1;
            };
            _p params ["_cx", "_cy", "_cw", "_ch"];
            if (!_vis || {_cw <= 0} || {_ch <= 0}) then { continue };
            // Fonds et bandeaux (presque) plein écran : ignorés, sinon aucune place ne serait libre.
            if (_cw > 0.6 * safeZoneW || {_ch > 0.6 * safeZoneH} || {(_cw * _ch) > 0.25 * _scr}) then { continue };
            _busy pushBack [_cx, _cy, _cx + _cw, _cy + _ch];
        } forEach (allControls _d);

        private _mx = 10 * pixelW;
        private _my = 10 * pixelH;
        private _gx = 4 * pixelW;
        private _gy = 4 * pixelH;
        private _xr = safeZoneX + safeZoneW - _mx - _w;
        private _xl = safeZoneX + _mx;
        private _yt = safeZoneY + _my;
        private _yb = safeZoneY + safeZoneH - _my - _h;
        private _sx = (_w * 0.5) max (16 * pixelW);
        private _sy = (_h * 0.5) max (8 * pixelH);
        private _cands = [];
        for "_i" from _xr to _xl step -_sx do { _cands pushBack [_i, _yt]; };
        for "_i" from _yt to _yb step _sy do { _cands pushBack [_xr, _i]; };
        for "_i" from _xr to _xl step -_sx do { _cands pushBack [_i, _yb]; };
        for "_i" from _yt to _yb step _sy do { _cands pushBack [_xl, _i]; };
        private _pick = _cands findIf {
            private _r = [(_x select 0) - _gx, (_x select 1) - _gy, (_x select 0) + _w + _gx, (_x select 1) + _h + _gy];
            (_busy findIf { (_r select 0) < (_x select 2) && {(_x select 0) < (_r select 2)} && {(_r select 1) < (_x select 3)} && {(_x select 1) < (_r select 3)} }) < 0
        };
        private _pos = if (_pick < 0) then { [_xr, _yt] } else { _cands select _pick };
        _grp ctrlSetPosition [_pos select 0, _pos select 1, _w, _h];
        _grp ctrlCommit 0;
    };

    // Pastille (couleur de l'état) et info-bulle du bouton.
    case "refresh": {
        private _grp = _a;
        if (!(_grp isEqualType controlNull) || {isNull _grp}) exitWith {};
        (_grp getVariable ["comspec_parts", []]) params [["_dot", controlNull], ["_ov", controlNull]];
        if (isNull _dot || {isNull _ov}) exitWith {};
        (["status"] call _self) params ["_lvl", "_lines"];
        _dot ctrlShow (_lvl isNotEqualTo "");
        _dot ctrlSetTextColor (_lvl call _lvlColor);
        private _open = "Ouvrir la configuration ATHENA" call _T;
        _ov ctrlSetTooltip ([_open, format ["%1 — %2", _lines joinString " · ", _open]] select (_lines isNotEqualTo []));
    };

    case "hover": {
        private _c = _a;
        if (!(_c isEqualType controlNull) || {isNull _c}) exitWith {};
        (_c getVariable ["comspec_parts", []]) params [["_bg", controlNull], ["_icon", controlNull], ["_iconN", ""], ["_iconH", ""], ["_colN", []], ["_colH", []]];
        private _on = _b isEqualTo true;
        if (!isNull _bg && {_colN isNotEqualTo []}) then { _bg ctrlSetBackgroundColor ([_colN, _colH] select _on); };
        if (!isNull _icon && {_iconH isNotEqualTo ""}) then { _icon ctrlSetText ([_iconN, _iconH] select _on); };
    };

    // Fenêtre « Configuration ATHENA », par-dessus le menu Échap.
    case "open": {
        private _parent = _a;
        if (!(_parent isEqualType displayNull)) then { _parent = displayNull; };
        if (isNull _parent) then { { if (!isNull (findDisplay _x)) exitWith { _parent = findDisplay _x; }; } forEach [49, 46]; };
        if (!hasInterface || {isNull _parent}) exitWith {};
        if (!isNull (uiNamespace getVariable ["comspec_athena_config_window", displayNull])) exitWith {};
        private _entries = ["entries"] call _self;
        private _d = _parent createDisplay "RscDisplayEmpty";
        if (isNull _d) exitWith {};
        uiNamespace setVariable ["comspec_athena_config_window", _d];
        _d displayAddEventHandler ["Unload", { uiNamespace setVariable ["comspec_athena_config_window", displayNull]; }];

        // Unité tirée de la hauteur de l'écran ; _ux : même longueur à l'écran en largeur (16:9, 21:9, 4:3).
        private _u = safeZoneH * 0.01;
        private _ux = _u * pixelW / pixelH;
        private _w = (66 * _ux) min (safeZoneW * 0.94);
        private _rowH = 9.5 * _u;
        private _gap = 1 * _u;
        private _headH = 16 * _u;
        private _footH = 8 * _u;
        private _n = (count _entries) max 1;
        private _h = (_headH + _n * (_rowH + _gap) + _gap + _footH) min (safeZoneH * 0.94);
        private _x0 = safeZoneX + (safeZoneW - _w) / 2;
        private _y0 = safeZoneY + (safeZoneH - _h) / 2;
        private _mk = {
            params ["_cls", "_pos", ["_grp", controlNull]];
            private _c = if (isNull _grp) then { _d ctrlCreate [_cls, -1] } else { _d ctrlCreate [_cls, -1, _grp] };
            _c ctrlSetPosition _pos;
            _c ctrlCommit 0;
            _c
        };

        (["ctrlStatic", [safeZoneX, safeZoneY, safeZoneW, safeZoneH]] call _mk) ctrlSetBackgroundColor [0, 0, 0, 0.6];
        (["ctrlStatic", [_x0, _y0, _w, _h]] call _mk) ctrlSetBackgroundColor [0.035, 0.043, 0.04, 0.98];
        (["ctrlStatic", [_x0, _y0, _w, (0.35 * _u) max (2 * pixelH)]] call _mk) ctrlSetBackgroundColor [0.204, 0.827, 0.6, 1];

        // En-tête : logo, titre, état de la liaison et du service Athéna, versions.
        private _logo = _host getOrDefault ["logo", ""];
        if (_logo isEqualTo "" || {!fileExists _logo}) then { _logo = _host getOrDefault ["icon", ""]; };
        (["ctrlStaticPictureKeepAspect", [_x0 + 2.5 * _ux, _y0 + 2 * _u, 12 * _ux, 12 * _u]] call _mk) ctrlSetText _logo;
        private _tx = _x0 + 16.5 * _ux;
        private _tw = _w - 19 * _ux;
        private _title = ["ctrlStatic", [_tx, _y0 + 2 * _u, _tw, 5 * _u]] call _mk;
        _title ctrlSetFont "RobotoCondensedBold";
        _title ctrlSetFontHeight (4 * _u);
        _title ctrlSetTextColor [0.93, 0.95, 0.94, 1];
        _title ctrlSetText ("Configuration ATHENA" call _T);
        private _sdot = ["ctrlStaticPictureKeepAspect", [_tx, _y0 + 7.9 * _u, 1.8 * _ux, 1.8 * _u]] call _mk;
        _sdot ctrlSetText (_host getOrDefault ["dot", ""]);
        private _st = ["ctrlStatic", [_tx + 2.6 * _ux, _y0 + 7.2 * _u, _tw - 2.6 * _ux, 3.2 * _u]] call _mk;
        _st ctrlSetFont "RobotoCondensed";
        _st ctrlSetFontHeight (2.6 * _u);
        _st ctrlSetTextColor [0.86, 0.9, 0.88, 1];
        private _ver = [];
        {
            _x params ["_cls", "_fmt"];
            private _cfg = configFile >> "CfgPatches" >> _cls;
            if (isClass _cfg) then {
                private _v = getText (_cfg >> "versionStr");
                if (_v isEqualTo "") then { _v = str (getNumber (_cfg >> "version")); };
                _ver pushBack format [_fmt call _T, _v];
            };
        } forEach [["comspec_atak_native_main", "ATAK natif %1"], ["comspec_sse_main", "SSE %1"]];
        private _vt = ["ctrlStatic", [_tx, _y0 + 10.6 * _u, _tw, 3 * _u]] call _mk;
        _vt ctrlSetFont "RobotoCondensed";
        _vt ctrlSetFontHeight (2.2 * _u);
        _vt ctrlSetTextColor [0.6, 0.66, 0.63, 1];
        _vt ctrlSetText (_ver joinString "   ·   ");
        (["ctrlStatic", [_x0 + 2.5 * _ux, _y0 + _headH - pixelH, _w - 5 * _ux, pixelH]] call _mk) ctrlSetBackgroundColor [0.16, 0.2, 0.18, 1];
        _d setVariable ["comspec_header", [_sdot, _st]];
        ["header", _d] call _self;

        // Entrées : icône, titre, une phrase d'explication ; toute la ligne est cliquable (bouton transparent posé dessus).
        (["status"] call _self) params ["_lvl", "", "_focus"];
        private _lx = _x0 + 2.5 * _ux;
        private _lw = _w - 5 * _ux;
        private _list = ["ctrlControlsGroup", [_lx, _y0 + _headH + _gap, _lw, _h - _headH - _footH - _gap]] call _mk;
        private _rw = _lw - 1.2 * _ux;
        private _rows = [];
        private _focusRow = controlNull;
        {
            _x params ["_id", "_tt", "_ds", "_ic"];
            private _ry = _forEachIndex * (_rowH + _gap);
            private _title1 = _tt call _txt;
            private _desc = _ds call _txt;
            private _icons = [_ic, [_ic], []] select ([_ic isEqualType [], _ic isEqualType "", true] find true);
            private _ii = _icons findIf { _x isEqualType "" && {_x isNotEqualTo ""} && {fileExists _x} };
            private _iconPath = if (_ii < 0) then { _host getOrDefault ["icon", ""] } else { _icons select _ii };
            private _isFocus = _id isEqualTo _focus && {_lvl in ["warn", "err"]};

            private _bg = ["ctrlStatic", [0, _ry, _rw, _rowH], _list] call _mk;
            _bg ctrlSetBackgroundColor [0.067, 0.082, 0.075, 1];
            (["ctrlStatic", [0, _ry, (0.45 * _ux) max (3 * pixelW), _rowH], _list] call _mk) ctrlSetBackgroundColor ([[0.204, 0.827, 0.6, 0.55], _lvl call _lvlColor] select _isFocus);
            private _pic = ["ctrlStaticPictureKeepAspect", [2.2 * _ux, _ry + 2 * _u, 5.5 * _ux, 5.5 * _u], _list] call _mk;
            _pic ctrlSetText _iconPath;
            _pic ctrlSetTextColor [0.93, 0.95, 0.94, 1];
            private _tl = ["ctrlStatic", [10 * _ux, _ry + 1.2 * _u, _rw - 15 * _ux, 3.6 * _u], _list] call _mk;
            _tl ctrlSetFont "RobotoCondensedBold";
            _tl ctrlSetFontHeight (3 * _u);
            _tl ctrlSetTextColor [0.93, 0.95, 0.94, 1];
            _tl ctrlSetText _title1;
            private _dl = ["ctrlStructuredText", [10 * _ux, _ry + 4.8 * _u, _rw - 15 * _ux, 4.4 * _u], _list] call _mk;
            _dl ctrlSetFontHeight (2.2 * _u);
            _dl ctrlSetStructuredText parseText format ["<t font='RobotoCondensed' color='#9AA59F'>%1</t>", _desc call _esc];
            private _chev = ["ctrlStatic", [_rw - 4 * _ux, _ry, 3 * _ux, _rowH], _list] call _mk;
            _chev ctrlSetFont "RobotoCondensedBold";
            _chev ctrlSetFontHeight (4 * _u);
            _chev ctrlSetTextColor [0.204, 0.827, 0.6, 1];
            _chev ctrlSetText "›";
            private _ov = [_host getOrDefault ["row", "ctrlButton"], [0, _ry, _rw, _rowH], _list] call _mk;
            _ov ctrlSetText "";
            _ov ctrlSetTooltip ([_desc, _title1] select (_desc isEqualTo ""));
            _ov setVariable ["comspec_id", _id];
            _ov setVariable ["comspec_parts", [_bg, controlNull, "", "", [0.067, 0.082, 0.075, 1], [0.09, 0.14, 0.12, 1]]];
            _ov ctrlAddEventHandler ["ButtonClick", {
                params ["_c"];
                private _id = _c getVariable ["comspec_id", ""];
                [{
                    ["run", _this select 0] call ((missionNamespace getVariable ["comspec_athena_config_host", createHashMap]) getOrDefault ["fn", {}]);
                }, [_id]] call CBA_fnc_execNextFrame;
            }];
            {
                _ov ctrlAddEventHandler [_x select 0, format ["['hover', _this select 0, %1] call ((missionNamespace getVariable ['comspec_athena_config_host', createHashMap]) getOrDefault ['fn', {}])", _x select 1]];
            } forEach [["MouseEnter", true], ["MouseExit", false], ["SetFocus", true], ["KillFocus", false]];
            _rows pushBack _ov;
            if (_isFocus) then { _focusRow = _ov; };
        } forEach _entries;

        // Pied : Fermer (l'écran se ferme à l'image suivante, hors du gestionnaire du bouton).
        private _bw = 16 * _ux;
        private _cl = ["ctrlButton", [_x0 + _w - 2.5 * _ux - _bw, _y0 + _h - 6.5 * _u, _bw, 4.6 * _u]] call _mk;
        _cl ctrlSetFont "RobotoCondensedBold";
        _cl ctrlSetFontHeight (2.6 * _u);
        _cl ctrlSetText ("Fermer" call _T);
        _cl ctrlSetTooltip ("Fermer et revenir au menu Échap (touche Échap)" call _T);
        _cl ctrlSetBackgroundColor [0.10, 0.12, 0.11, 1];
        _cl ctrlAddEventHandler ["ButtonClick", {
            [{ params ["_d"]; if (!isNull _d) then { _d closeDisplay 2; }; }, [ctrlParent (_this select 0)]] call CBA_fnc_execNextFrame;
        }];
        _rows pushBack _cl;

        // Clavier : flèches haut / bas d'une ligne à l'autre, Entrée ouvre (bouton qui a le focus), Échap ferme.
        _d setVariable ["comspec_rows", _rows];
        _d displayAddEventHandler ["KeyDown", {
            params ["_d", "_key"];
            if !(_key in [200, 208]) exitWith { false };
            private _list = _d getVariable ["comspec_rows", []];
            if (_list isEqualTo []) exitWith { false };
            private _i = _list find (focusedCtrl _d);
            _i = if (_i < 0) then { 0 } else { (_i + ([1, -1] select (_key isEqualTo 200)) + count _list) mod (count _list) };
            ctrlSetFocus (_list select _i);
            true
        }];
        ctrlSetFocus ([_rows select 0, _focusRow] select (!isNull _focusRow));

        // État de l'en-tête suivi chaque seconde tant que la fenêtre est ouverte.
        [_d] spawn {
            params ["_d"];
            disableSerialization;
            while { !isNull _d } do {
                uiSleep 1;
                if (isNull _d) exitWith {};
                ["header", _d] call ((missionNamespace getVariable ["comspec_athena_config_host", createHashMap]) getOrDefault ["fn", {}]);
            };
        };
    };

    case "header": {
        private _d = _a;
        if (!(_d isEqualType displayNull) || {isNull _d}) exitWith {};
        (_d getVariable ["comspec_header", []]) params [["_sdot", controlNull], ["_st", controlNull]];
        if (isNull _st) exitWith {};
        (["status"] call _self) params ["_lvl", "_lines"];
        _sdot ctrlShow (_lvl isNotEqualTo "");
        _sdot ctrlSetTextColor (_lvl call _lvlColor);
        _st ctrlSetText (_lines joinString "   ·   ");
    };

    // Action d'une entrée, toujours appelée à l'image suivante du clic (CBA_fnc_execNextFrame).
    case "run": {
        private _id = _a;
        if !(_id isEqualType "") exitWith {};
        private _e = (["entries"] call _self) select { (_x select 0) isEqualTo _id };
        if (_e isEqualTo []) exitWith {};
        (_e select 0) params ["", "", "", "", "", "_act", "", ["_how", "child", [""]]];
        private _win = uiNamespace getVariable ["comspec_athena_config_window", displayNull];
        switch (_how) do {
            case "close": {
                private _menu = findDisplay 49;
                if (!isNull _win) then { _win closeDisplay 2; };
                [{ params ["_act", "_menu"]; [_menu] call _act; }, [_act, _menu]] call CBA_fnc_execNextFrame;
            };
            case "menu": {
                if (!isNull _win) then { _win closeDisplay 2; };
                private _menu = findDisplay 49;
                if (!isNull _menu) then { _menu closeDisplay 2; };
                [{ params ["_act"]; [displayNull] call _act; }, [_act]] call CBA_fnc_execNextFrame;
            };
            default {
                if (isNull _win) exitWith {};
                [_win] call _act;
            };
        };
    };
};
