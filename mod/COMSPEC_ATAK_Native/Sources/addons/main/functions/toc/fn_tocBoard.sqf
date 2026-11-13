/*
    Rendu des écrans TOC muraux (fn_tocScreen) hors de la carte elle-même, dans la texture UI 1600 x 900 (faces 4:1 :
    1600 x 400) : en-tête (titre, heures ZULU et locale), bandeau FLASH, pied (écran · affichage, état de la liaison),
    panneaux des affichages à carte (SPLIT, TOC, SQUAD) et affichages « tableau » :
      FEED     flux du camp en tableau         NET      groupes du réseau (deux tableaux)     ORBAT    ORBAT du site (3 colonnes)
      SAT      console ISR (fn_tocSat) : image r2t plein cadre, HUD du capteur (coins, réticule, échelle, données, pistes
               amies), bandeau « Lecture seule · SAT n · … » en bas ; aucune commande (la main est au PC du poste)
      CLOCK    ZULU et locale en grand, groupe date-heure, prochaine ligne d'exécution, chronos du poste (fn_tocRoom)
      LOG      main courante                   EXCHECK  liste d'exécution (barre d'avancement, ligne suivante en évidence)
      KILLS    pertes et résultats par élément (totaux)   REQ  demandes reçues des éléments (statut en pastille)
      MOSAIC   mosaïque de caméras (coins de visée, bandeau indicatif, barres de liaison, PAS DE SIGNAL)
      STRIP    bandeau horloge 4:1             PLATE    plaque de nom 4:1
      COLA     colonne horloge et chronos      COLB     colonne réseau, groupes et flux (colonnes 8:9 d'un écran
               incurvé : 800 x 900 virtuels, de part et d'autre de la zone centrale 16:9)
      COLC     colonne caméras (deux vignettes 16:9, sources et réglage de la mosaïque)   COLD   colonne main courante
      COLE     colonne exécution (prochaine ligne, liste)   COLF   colonne demandes et pertes (dispositions : fn_tocLayout)
    L'affichage TEXTE (panneau à LED, texte défilant) est dessiné par fn_tocText.
    Refonte du 11/11 (« un rendu pro et propre ») : tout vient du thème (fn_tocTheme : couleurs, tailles, polices,
    régions, colonnes, règles sous FLASH ; maquettes tools/toc_mockups.py avec les mêmes nombres, docs/TOC-THEME.md).
    Dessin « immédiat » : à chaque image, chaque élément prend un contrôle dans une réserve par calque (fonds, filets et
    pastilles, images, cadres sur les images, textes) puis les contrôles non repris sont masqués ; l'ordre des calques
    garantit qu'un texte n'est jamais sous un fond. Textes à taille fixe (jamais moins de 28 px virtuels), coupés à leur
    largeur avec « … », réécrits seulement s'ils changent ; tableaux limités à leur hauteur avec « + N autres ».
    Modes :
      ["draw", [écran, display, affichage, contexte]]   image complète (fn_tocScreen, toutes les 1 à 2 s, écran allumé) ;
                                              contexte des affichages à carte : [["op", vue PIP], ["squad", groupe], ["sqNodes", nœuds]]
      ["overlay", [écran, display]]           SAT : HUD du capteur seul (fn_tocSat « hudPaint »), à chaque image
      ["live", [écran, display]]              image vivante : SAT à chaque image, mosaïque et caméra du chef 6 à 7 fois par seconde
      ["hide", display]                       rien à faire (tout est repris à chaque image), gardé pour les appels existants
      ["flash", camp]                         FLASH en cours : [texte, auteur, heure] ou []
      ["boardText", [affichage, état, camp]]  texte structuré LOG, EXCHECK, KILLS, REQ (le PC du poste lit KILLS)
*/
params [["_mode", "draw"], ["_a", []]];
disableSerialization;
private _fn = comspec_atak_native_fnc_tocBoard;
private _thm = ["get"] call comspec_atak_native_fnc_tocTheme;
private _texW = 1600;
private _texH = 900;
private _esc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };
// Texte coupé à n caractères avec « … » (caractères Unicode : un accent n'est jamais coupé en deux).
private _cut = {
    params ["_s", "_n"];
    if !(_s isEqualType "") then { _s = str _s; };
    forceUnicode 0;
    if ((count _s) <= _n) exitWith { _s };
    forceUnicode 0;
    (_s select [0, (_n - 1) max 1]) + "…"
};
private _tcol = { params ["_k", ["_al", 1]]; private _v = +((_thm get "c") getOrDefault [_k, [1, 0, 1, 1]]); _v set [3, _al]; _v };
private _ink = { ["ink", _this] call comspec_atak_native_fnc_tocTheme };
// Rectangle en pixels virtuels -> position dans le display (ou le groupe plein écran).
private _toR = { params ["_rx", "_ry", "_rw", "_rh"]; [_rx / _texW, _ry / _texH, _rw / _texW, _rh / _texH] };
// Image satellite (console ISR, fn_tocSat) : plein cadre (la texture est en 16:9), bandeau de lecture seule en bas (46 px).
private _stripH = 46 / _texH;
switch (_mode) do {
    case "flash": {
        private _f = (["state", _a] call comspec_atak_native_fnc_tocRoom) select 6;
        if ((count _f) >= 4 && {serverTime - (_f select 3) < 180}) then { [_f select 0, _f select 1, _f select 2] } else { [] }
    };
    case "live": {
        // Écran à image vivante (satellite, mosaïque, caméra du chef) : texture UI redessinée 6 à 7 fois par seconde,
        // tant que l'écran garde cet affichage, sa texture sur ce poste et reste allumé (proche et regardé, fn_tocScreen) :
        // un écran figé ou en veille ne coûte plus rien à l'image.
        _a params ["_o", "_d"];
        private _live = uiNamespace getVariable ["COMSPEC_ATAK_TocBoardLive", []];
        if ((_live findIf { (_x select 0) isEqualTo _o }) >= 0) exitWith {};
        _live pushBack [_o, _d];
        uiNamespace setVariable ["COMSPEC_ATAK_TocBoardLive", _live];
        if (!isNil { missionNamespace getVariable "COMSPEC_ATAK_TocBoardPfh" }) exitWith {};
        missionNamespace setVariable ["COMSPEC_ATAK_TocBoardPfh", [{
            private _l = (uiNamespace getVariable ["COMSPEC_ATAK_TocBoardLive", []]) select {
                _x params ["_o", "_d"];
                !isNull _o && {!isNull _d} && {(_o getVariable ["COMSPEC_ATAK_TocTex", ""]) isNotEqualTo ""} && {_o getVariable ["COMSPEC_ATAK_TocOn", false]}
                    && {(((_o getVariable ["COMSPEC_ATAK_TocScr", []]) param [3, ""]) in ["SAT", "MOSAIC"]) || {_o getVariable ["COMSPEC_ATAK_TocVideo", false]}}
            };
            uiNamespace setVariable ["COMSPEC_ATAK_TocBoardLive", _l];
            if ((count _l) isEqualTo 0) exitWith {
                [missionNamespace getVariable "COMSPEC_ATAK_TocBoardPfh"] call CBA_fnc_removePerFrameHandler;
                missionNamespace setVariable ["COMSPEC_ATAK_TocBoardPfh", nil];
            };
            // Satellite : cadres et image à chaque image (la phase suit le panoramique) ; mosaïque et vidéo : 6 à 7 fois par seconde.
            private _now = diag_tickTime;
            private _slow = _now >= (uiNamespace getVariable ["COMSPEC_ATAK_TocBoardNext", 0]);
            if (_slow) then { uiNamespace setVariable ["COMSPEC_ATAK_TocBoardNext", _now + 0.15]; };
            {
                // Satellite : redessin seulement si l'image est rendue ici (sinon « overlay » masque le HUD).
                if ((((_x select 0) getVariable ["COMSPEC_ATAK_TocScr", []]) param [3, ""]) isEqualTo "SAT") then {
                    if (["overlay", _x] call comspec_atak_native_fnc_tocBoard) then { displayUpdate (_x select 1); };
                } else {
                    if (_slow) then { displayUpdate (_x select 1); };
                };
            } forEach _l;
        }, 0] call CBA_fnc_addPerFrameHandler];
    };
    case "hide": {};
    case "overlay": {
        _a params ["_o", "_d"];
        private _ui = _d getVariable ["COMSPEC_ATAK_TocBoardUi", createHashMap];
        if ((count _ui) isEqualTo 0) exitWith { false };
        private _c = _o getVariable ["COMSPEC_ATAK_TocScr", []];
        private _side = [_c param [1, ""], str side group player] select ((_c param [1, ""]) isEqualTo "");
        private _n = ((_c param [8, 0]) max 0) min 1;
        private _hud = _ui get "hud";
        private _rendered = (["rt", [_side, _n]] call comspec_atak_native_fnc_tocSat) isNotEqualTo "";
        if (!_rendered) exitWith { ["hudHide", _hud] call comspec_atak_native_fnc_tocSat; false };
        // Zone visible : sous un FLASH (52 px en haut), au-dessus du bandeau de lecture seule. Calques d'un écran du mur :
        // marqueurs, pistes, cadres et données ; pas de quadrillage.
        private _y0 = [0, 52 / _texH] select ((count (["flash", _side] call _fn)) > 0);
        ["hudPaint", [_hud, _side, _n, [0, 0, 1, 1], [0, _y0, 1, 1 - _stripH - _y0],
            createHashMapFromArray [["MRKR", true], ["BFT", true], ["GRID", false], ["DATA", true], ["BOX", true]]]] call comspec_atak_native_fnc_tocSat;
        true
    };
    case "draw": {
        _a params ["_o", "_d", "_view", ["_ctx", createHashMap]];
        private _cfg = _o getVariable ["COMSPEC_ATAK_TocScr", []];
        _cfg params [["_name", "TOC"], ["_cside", ""]];
        // Nom écrit sur l'image : seulement celui que quelqu'un a donné à l'écran. Le nom du modèle (« 98-inch giant
        // ceiling-hung screen », posé d'office comme nom) n'y figure jamais : l'en-tête et le pied disent ce que l'écran
        // montre (retour de TTRD : « inutile, la taille de l'écran notée en haut »).
        private _mdl = getText (configOf (_o getVariable ["COMSPEC_ATAK_TocHost", _o]) >> "displayName");
        private _label = ["", _name] select (_name isNotEqualTo "" && {_name isNotEqualTo "TOC"} && {_mdl isEqualTo "" || {(_name find (_mdl select [0, 20])) < 0}});
        private _side = [_cside, str side group player] select (_cside isEqualTo "");
        private _wide = _view in ["STRIP", "PLATE"];
        if (_wide) then { _texH = 400; };
        // Colonne 8:9 d'un écran incurvé : 800 x 900 virtuels, ni en-tête ni pied (ceux de la zone centrale suffisent).
        private _col = _view in ["COLA", "COLB", "COLC", "COLD", "COLE", "COLF"];
        if (_col) then { _texW = 800; };
        private _g = _d getVariable ["COMSPEC_ATAK_TocBoard", controlNull];
        private _ui = _d getVariable ["COMSPEC_ATAK_TocBoardUi", createHashMap];
        if (isNull _g) then {
            // Calques, du dessous au-dessus : fonds, filets et pastilles, images, satellite, cadres sur les images, textes.
            _g = _d ctrlCreate ["RscControlsGroupNoScrollbars", -1];
            _g ctrlSetPosition [0, 0, 1, 1];
            _g ctrlCommit 0;
            _d setVariable ["COMSPEC_ATAK_TocBoard", _g];
            private _lays = createHashMap;
            {
                private _l = _d ctrlCreate ["RscControlsGroupNoScrollbars", -1, _g];
                _l ctrlSetPosition [0, 0, 1, 1];
                _l ctrlCommit 0;
                _lays set [_x, _l];
            } forEach ["b0", "b1", "P", "sat", "b2", "T"];
            // Satellite (console ISR) : image plein cadre, bandeau de lecture seule, attente, HUD du capteur (fn_tocSat).
            private _sl = _lays get "sat";
            private _mk = { params ["_cls", ["_pos", [0, 0, 0, 0]]]; private _c = _d ctrlCreate [_cls, -1, _sl]; _c ctrlSetPosition _pos; _c ctrlCommit 0; _c };
            private _pic = ["RscPicture", [0, 0, 1, 1]] call _mk;
            private _strip = ["COMSPEC_RscTocText", [0, 1 - _stripH, 1, _stripH]] call _mk;
            _strip ctrlSetBackgroundColor (["bg0", 0.78] call _tcol);
            private _wait = ["COMSPEC_RscTocText", [0.1, 0.42, 0.8, 0.14]] call _mk;
            private _hud = ["hudMake", [_d, _sl, "WALL"]] call comspec_atak_native_fnc_tocSat;
            _ui = createHashMapFromArray [["lays", _lays], ["pools", createHashMap], ["pic", _pic], ["strip", _strip], ["wait", _wait], ["hud", _hud]];
            _d setVariable ["COMSPEC_ATAK_TocBoardUi", _ui];
        };
        _g ctrlShow true;
        private _pools = _ui get "pools";
        { (_x) set [1, 0]; } forEach (values _pools);
        // ---- Réserves : _take [calque] -> contrôle (créé au besoin dans son calque) ----
        private _take = {
            params ["_k"];
            private _p = _pools getOrDefault [_k, []];
            if ((count _p) isEqualTo 0) then { _p = [[], 0]; _pools set [_k, _p]; };
            private _i = _p select 1;
            _p set [1, _i + 1];
            private _list = _p select 0;
            if (_i < (count _list)) exitWith { _list select _i };
            private _cls = (createHashMapFromArray [["L", "COMSPEC_RscTocLabel"], ["R", "COMSPEC_RscTocLabelRight"], ["C", "COMSPEC_RscTocLabelCenter"], ["S", "COMSPEC_RscTocText"], ["P", "RscPicture"]]) getOrDefault [_k, "RscText"];
            private _lay = (_ui get "lays") get ((createHashMapFromArray [["L", "T"], ["R", "T"], ["C", "T"], ["S", "T"]]) getOrDefault [_k, _k]);
            private _c = _d ctrlCreate [_cls, -1, _lay];
            _list pushBack _c;
            _c
        };
        private _put = { params ["_c", "_r"]; _c ctrlSetPosition (_r call _toR); _c ctrlCommit 0; _c ctrlShow true; };
        private _box = { params ["_r", "_tok", ["_al", 1], ["_lay", "b0"]]; private _c = [_lay] call _take; _c ctrlSetBackgroundColor ([_tok, _al] call _tcol); [_c, _r] call _put; _c };
        // Texte d'une ligne dans une case : style du thème (police, taille, couleur), traduit puis coupé à la largeur
        // (estimation large, puis mesure du jeu quand elle est plausible). Réécrit seulement s'il change.
        private _text = {
            params ["_r", "_s", ["_st", "body"], ["_tok", "text"], ["_al", "L"], ["_raw", false]];
            if (_al isEqualTo "P") then { _al = "C"; };
            private _c = [_al] call _take;
            ((_thm get "type") getOrDefault [_st, [28, "RobotoCondensed"]]) params ["_px", "_font"];
            private _sk = [_st, _tok, _texH];
            if ((_c getVariable ["COMSPEC_ATAK_St", []]) isNotEqualTo _sk) then {
                _c setVariable ["COMSPEC_ATAK_St", _sk];
                _c ctrlSetFont _font;
                _c ctrlSetFontHeight (_px / _texH);
                _c ctrlSetTextColor ([_tok] call _tcol);
            };
            private _w = _r select 2;
            private _key = format ["%1|%2|%3", _s, round _w, _st];
            if ((_c getVariable ["COMSPEC_ATAK_Txt", ""]) isNotEqualTo _key) then {
                _c setVariable ["COMSPEC_ATAK_Txt", _key];
                private _cw = _px * ([0.56, 0.8] select ((_font find "Monospace") >= 0)) * ([1, 1.08] select ((_font find "Bold") >= 0));
                private _room = _w - 8;
                private _t = [[_s call comspec_atak_native_fnc_tr, _s] select _raw, floor (_room / _cw)] call _cut;
                _c ctrlSetText _t; // i18n:brut (déjà traduit)
                for "_i" from 1 to 6 do {
                    private _m = (ctrlTextWidth _c) * _texW;
                    forceUnicode 0;
                    private _n = count _t;
                    // Mesure du jeu crue dès qu'elle existe : le texte ne déborde plus de sa case (coupé avec « … »).
                    if (_m <= 0 || {_m <= _room} || {_n <= 2}) exitWith {};
                    _t = [_t, (floor (_n * _room / _m)) min (_n - 1)] call _cut;
                    _c ctrlSetText _t; // i18n:brut (déjà traduit)
                };
            };
            [_c, _r] call _put;
            _c
        };
        // Texte structuré (plusieurs couleurs sur une ligne) : color='@jeton'.
        private _stx = {
            params ["_r", "_s"];
            private _c = ["S"] call _take;
            if ((_c getVariable ["COMSPEC_ATAK_Txt", ""]) isNotEqualTo _s) then {
                _c setVariable ["COMSPEC_ATAK_Txt", _s];
                _c ctrlSetStructuredText parseText ((_s call comspec_atak_native_fnc_tr) call _ink);
            };
            [_c, _r] call _put;
            _c
        };
        private _pill = {
            params ["_r", "_label", "_tok"];
            _r params ["_qx", "_qy", "_qw", "_qh"];
            [[_qx, _qy + 4, _qw, _qh - 8], _tok, 0.16, "b1"] call _box;
            [[_qx, _qy + 4, 3, _qh - 8], _tok, 1, "b1"] call _box;
            [[_qx + 3, _qy, _qw - 3, _qh], _label, "body", _tok, "C"] call _text;
        };
        // ---- Mise en page : régions du thème, descendues sous un FLASH (_flRule) ----
        private _fl = ["flash", _side] call _fn;
        private _isFl = (count _fl) > 0 && {!_wide} && {!_col};
        private _lv = [_view, "SPLIT"] select (_view isEqualTo "TOC");
        private _rg = { ["region", [_lv, _this, _isFl]] call comspec_atak_native_fnc_tocTheme };
        private _section = {
            params ["_k", "_title", ["_sub", ""], ["_pre", ""]];
            [(_pre + "Head") call _rg, _title, "heading", "text"] call _text;
            if (_sub isNotEqualTo "") then { [(_pre + "Sub") call _rg, _sub, "caption", "muted", "R"] call _text; };
            ((_pre + "Rule") call _rg) params ["_qx", "_qy", "_qw"];
            [[_qx, _qy, _qw, 1], "line", 1, "b1"] call _box;
            [[_qx, _qy - 1, 40, 2], "accent", 1, "b1"] call _box;
        };
        // Section des affichages pleine largeur (régions head / sub / rule).
        private _sectionW = {
            params ["_title", ["_sub", ""]];
            ["head" call _rg, _title, "heading", "text"] call _text;
            if (_sub isNotEqualTo "") then { ["sub" call _rg, _sub, "caption", "muted", "R"] call _text; };
            ("rule" call _rg) params ["_qx", "_qy", "_qw"];
            [[_qx, _qy, _qw, 1], "line", 1, "b1"] call _box;
            [[_qx, _qy - 1, 40, 2], "accent", 1, "b1"] call _box;
        };
        // Cases à gros chiffre : [[nombre, libellé, jeton]...], 2 x 2 dans un panneau, 4 en ligne en pleine largeur.
        private _tiles = {
            params ["_r", "_vals", ["_cols", 2]];
            _r params ["", "", "_w", "_h"];
            private _rows = ceil ((count _vals) / _cols);
            private _tw = (_w - (_cols - 1) * 8) / _cols;
            private _th = (_h - (_rows - 1) * 8) / _rows;
            {
                _x params ["_n", "_lbl", "_tok"];
                private _tx = (_r select 0) + (_forEachIndex mod _cols) * (_tw + 8);
                private _ty = (_r select 1) + (floor (_forEachIndex / _cols)) * (_th + 8);
                [[_tx, _ty, _tw, _th], "bg2"] call _box;
                [[_tx, _ty, 4, _th], _tok, 1, "b1"] call _box;
                [[_tx + 12, _ty, 84, _th], str _n, "num", _tok, "R", true] call _text;
                [[_tx + 104, _ty, _tw - 112, _th], _lbl, "body", "text2"] call _text;
            } forEach _vals;
        };
        // Tableau : en-têtes en légende, lignes de 40 px séparées d'un filet, nombres à droite, pastilles d'état.
        // Lignes : [[texte, jeton]...] dans l'ordre des colonnes ("" : case vide). Trop de lignes : « + N autres ».
        private _table = {
            params ["_ck", "_head", "_area", "_rows", ["_more", "+ %1 autres"], ["_hl", -1]];
            private _cols = (_thm get "cols") getOrDefault [_ck, []];
            if ((count _head) >= 4) then {
                _head params ["_hx", "_hy", "", "_hh"];
                { _x params ["", "_lbl", "_cx", "_cw", "_al"]; if (_lbl isNotEqualTo "") then { [[_hx + _cx, _hy, _cw, _hh], _lbl, "caption", "muted", ["L", "R"] select (_al isEqualTo "R")] call _text; }; } forEach _cols;
            };
            _area params ["_ax", "_ay", "_aw", "_ah"];
            private _cap = floor (_ah / 40);
            private _shown = if ((count _rows) <= _cap || {_more isEqualTo ""}) then { _rows select [0, _cap] } else { _rows select [0, (_cap - 1) max 0] };
            {
                private _row = _x;
                private _ry = _ay + _forEachIndex * 40;
                if (_forEachIndex isEqualTo _hl) then { [[_ax, _ry, _aw, 40], "bg2"] call _box; [[_ax, _ry, 3, 40], "accent", 1, "b1"] call _box; };
                {
                    _x params ["_key", "", "_cx", "_cw", "_al", "_st"];
                    (_row param [_forEachIndex, ["", "text"]]) params [["_s", ""], ["_tok", "text"]];
                    if (_s isNotEqualTo "") then {
                        switch (true) do {
                            case (_al isEqualTo "P"): { [[_ax + _cx, _ry, _cw, 40], _s, _tok] call _pill; };
                            case (_key isEqualTo "dot"): { [[_ax + _cx + 2, _ry + 14, 12, 12], _tok, 1, "b1"] call _box; };
                            default { [[_ax + _cx, _ry, _cw, 40], _s, _st, _tok, _al] call _text; };
                        };
                    };
                } forEach _cols;
                [[_ax, _ry + 39, _aw, 1], "line", 0.7, "b1"] call _box;
            } forEach _shown;
            if ((count _rows) > (count _shown) && {_more isNotEqualTo ""}) then {
                [[_ax, _ay + (count _shown) * 40, _aw, 40], format [_more call comspec_atak_native_fnc_tr, (count _rows) - (count _shown)], "caption", "muted", "L", true] call _text;
            };
            count _shown
        };
        // Coins de visée d'une image en direct (mêmes coins que le satellite) : 4 équerres de 28 px, 3 px d'épaisseur.
        private _brackets = {
            params ["_r", ["_tok", "text"], ["_al", 0.85]];
            _r params ["_qx", "_qy", "_qw", "_qh"];
            {
                _x params ["_cx", "_cy", "_sx", "_sy"];
                [[[_cx, _cx - 28] select (_sx < 0), [_cy, _cy - 3] select (_sy < 0), 28, 3], _tok, _al, "b2"] call _box;
                [[[_cx, _cx - 3] select (_sx < 0), [_cy, _cy - 28] select (_sy < 0), 3, 28], _tok, _al, "b2"] call _box;
            } forEach [[_qx, _qy, 1, 1], [_qx + _qw, _qy, -1, 1], [_qx, _qy + _qh, 1, -1], [_qx + _qw, _qy + _qh, -1, -1]];
        };
        // Vignette caméra : image (ou PAS DE SIGNAL / CASE LIBRE), coins, pastille DIRECT, bandeau indicatif + liaison + heure.
        private _camTile = {
            params ["_r", "_tex", "_cs", "_lk", "_state"];
            _r params ["_qx", "_qy", "_qw", "_qh"];
            if (_state isEqualTo "free") exitWith {
                [_r, "bg1"] call _box;
                { [_x, "line", 1, "b1"] call _box; } forEach [[_qx, _qy, _qw, 1], [_qx, _qy + _qh - 1, _qw, 1], [_qx, _qy, 1, _qh], [_qx + _qw - 1, _qy, 1, _qh]];
                [_r, "CASE LIBRE", "body", "muted", "C"] call _text;
            };
            [_r, "bg1"] call _box;
            if (_tex isNotEqualTo "") then {
                private _p = ["P"] call _take;
                if ((_p getVariable ["COMSPEC_ATAK_Tex", "-"]) isNotEqualTo _tex) then { _p setVariable ["COMSPEC_ATAK_Tex", _tex]; _p ctrlSetText _tex; }; // i18n:brut (texture)
                [_p, _r] call _put;
                [[_qx + 16, _qy + 16, 110, 34], "bg0", 0.75, "b2"] call _box;
                [[_qx + 26, _qy + 27, 12, 12], "red", 1, "b2"] call _box;
                [[_qx + 40, _qy + 16, 86, 34], "DIRECT", "bodyBold", "text"] call _text;
            } else {
                if (_state isEqualTo "nosig") then {
                    [[_qx, _qy + _qh / 2 - 50, _qw, 40], "PAS DE SIGNAL", "heading", "red", "C"] call _text;
                    [[_qx, _qy + _qh / 2 - 8, _qw, 36], "liaison coupée", "caption", "muted", "C"] call _text;
                } else {
                    [[_qx, _qy + _qh / 2 - 40, _qw, 40], "À son tour dans un instant", "body", "text2", "C"] call _text;
                };
            };
            [[_qx + 10, _qy + 10, _qw - 20, _qh - 60], ["lineStrong", "text"] select (_tex isNotEqualTo ""), 0.85] call _brackets;
            [[_qx, _qy + _qh - 40, _qw, 40], "bg0", 0.85, "b2"] call _box;
            [[_qx + 8, _qy + _qh - 40, _qw - 200, 40], _cs, "bodyBold", "text"] call _text;
            private _lvl = (createHashMapFromArray [["bonne", 4], ["moyenne", 3], ["faible", 2], ["coupée", 0]]) getOrDefault [_lk, 4];
            private _lt = (createHashMapFromArray [["bonne", "accent"], ["moyenne", "amber"], ["faible", "amber"], ["coupée", "red"]]) getOrDefault [_lk, "accent"];
            for "_i" from 0 to 3 do {
                private _bh = 8 + _i * 6;
                [[_qx + _qw - 184 + _i * 10, _qy + _qh - 8 - _bh, 6, _bh], [_lt, "lineStrong"] select (_i >= _lvl), 1, "b2"] call _box;
            };
            [[_qx + _qw - 120, _qy + _qh - 40, 116, 40], (_zulu select [0, 5]) + "Z", "data", "text2", "R", true] call _text;
        };
        // ---- Données communes ----
        private _room = ["state", _side] call comspec_atak_native_fnc_tocRoom;
        private _zulu = ["zulu"] call comspec_atak_native_fnc_tocRoom;
        private _local = [dayTime, "HH:MM:SS"] call BIS_fnc_timeToString;
        private _feed = ["feed", _side] call comspec_atak_native_fnc_tocStatus;
        _feed params [["_ft", -1], ["_nodes", []], ["_events", []], ["_stats", []]];
        _stats params [["_jam", 0], ["_relays", 0], ["_players", 0], ["_ais", 0]];
        private _cnt = [0, 0, 0, 0];
        { private _f = ((_x select 4) min 3) max 0; _cnt set [_f, (_cnt select _f) + (_x param [8, 1])]; } forEach _nodes;
        private _stTok = ["accent", "amber", "red", "muted"];
        private _stLbl = ["en direct", "ancien", "perdu", "hors ligne"];
        private _netTiles = [[_cnt select 0, "en ligne", "accent"], [_cnt select 1, "anciens", "amber"], [_cnt select 2, "perdus", "red"], [_cnt select 3, "hors ligne", "muted"]];
        private _netSub = format ["%1 joueurs · %2 IA · %3 relais", _players, _ais, _relays] + (["", format [" · %1 brouilleurs", _jam]] select (_jam > 0));
        // Groupes du flux : [nom, nœuds, meilleur état, position].
        private _groupsOf = {
            params ["_ns"];
            private _gm = createHashMap;
            {
                private _gk = _x param [7, ""];
                private _e = _gm getOrDefault [_gk, [0, 9, _x select 2]];
                _gm set [_gk, [(_e select 0) + (_x param [8, 1]), (_e select 1) min (_x select 4), _e select 2]];
            } forEach _ns;
            private _ks = keys _gm;
            _ks sort true;
            _ks apply { private _e = _gm get _x; [[_x, "(sans groupe)"] select (_x isEqualTo ""), _e select 0, ((_e select 1) min 3) max 0, _e select 2] }
        };
        private _gridOf = { params ["_p"]; if ((count _p) < 2) exitWith { "" }; [[_p select 0, _p select 1, 0], 6] call comspec_atak_native_fnc_gridRef };
        private _prioTok = { params ["_p"]; (createHashMapFromArray [["FLASH", "red"], ["URGENT", "amber"], ["IMPORTANT", "blue"]]) getOrDefault [toUpper _p, "muted"] };

        // ---- Fond, en-tête, FLASH, pied (tous les affichages 16:9) ----
        private _mapView = _view in ["SPLIT", "MAP", "TOC", "SQUAD"];
        private _op = _ctx getOrDefault ["op", []];
        private _isSat = _view isEqualTo "SAT";
        // Satellite plein cadre : ni en-tête ni pied (heure et capteur dans le HUD, nom de l'écran dans le bandeau) ;
        // FLASH en haut de l'image.
        if (_isSat && {_isFl}) then {
            [[0, 0, 1600, 52], "redDim", 0.95, "b2"] call _box;
            [[0, 0, 8, 52], "red", 1, "b2"] call _box;
            [[24, 6, 1552, 40], format ["FLASH · %1 · %2 · %3Z", _fl select 0, _fl select 1, _fl select 2], "heading", "text", "L", true] call _text;
        };
        if (!_wide && {!_isSat} && {!_col}) then {
            if (!_mapView) then { [[0, 0, 1600, 900], "bg0"] call _box; };
            private _frm = ["lay", "FRAME"] call comspec_atak_native_fnc_tocTheme;
            [_frm get "header", "bg1"] call _box;
            [_frm get "rule", ["accent", "red"] select _isFl, 1, "b1"] call _box;
            private _head = (["title", _view] call comspec_atak_native_fnc_tocScreen) select 0;
            private _ttl = [_head, format ["%1 · %2", _head, _label]] select (_label isNotEqualTo "");
            private _ttok = "text";
            if ((count _op) >= 8) then { _ttl = format ["PIP · %1", _op param [5, _name]]; _ttok = "amber"; };
            if (_view isEqualTo "SQUAD") then { _ttl = format ["%1 · %2", _head, [_ctx getOrDefault ["squad", ""], "AUCUN GROUPE"] select ((_ctx getOrDefault ["squad", ""]) isEqualTo "")]; };
            [_frm get "title", _ttl, "title", _ttok] call _text;
            // Affichage HORLOGE : les heures sont déjà en grand dessous, pas une seconde fois dans l'en-tête.
            if (_view isNotEqualTo "CLOCK") then { [_frm get "clock", format ["<t align='right' size='1'><t font='EtelkaMonospaceProBold'>%1</t><t color='@muted'> Z     </t><t font='EtelkaMonospacePro' color='@text2'>%2</t><t color='@muted'> LOCAL</t></t>", _zulu, _local select [0, 5]]] call _stx; };
            if (_isFl) then {
                [_frm get "flash", "redDim", 1, "b1"] call _box;
                [_frm get "flashBar", "red", 1, "b1"] call _box;
                [_frm get "flashTxt", format ["FLASH · %1 · %2 · %3Z", _fl select 0, _fl select 1, _fl select 2], "heading", "text", "L", true] call _text;
            };
            [[0, 848, 1600, 52], "bg0"] call _box;
            [_frm get "footRule", "line", 1, "b1"] call _box;
            private _vl = (["title", _view] call comspec_atak_native_fnc_tocScreen) select 1;
            if ((count _op) >= 8) then { _vl = format ["PIP du poste · %1 · onglet %2", _op param [6, ""], _op param [3, ""]]; };
            [_frm get "footL", [_vl, format ["%1 · %2", _vl, _label]] select (_label isNotEqualTo ""), "caption", "muted"] call _text;
            private _age = [-1, round ((serverTime - _ft) max 0)] select (_ft >= 0);
            [_frm get "footR", switch (true) do {
                case (_age < 0): { "<t align='right'><t color='@red'>●</t> <t color='@text2'>HORS LIGNE · aucun flux du serveur</t></t>" };
                case (_age < 12): { format ["<t align='right'><t color='@accent'>●</t> <t color='@text2'>EN DIRECT · données à %1 s</t></t>", _age] };
                default { format ["<t align='right'><t color='@amber'>●</t> <t color='@text2'>RETARD · données à %1 s</t></t>", _age min 999] };
            }] call _stx;
        };
        // Satellite : calque fixe montré seulement pour lui.
        ((_ui get "lays") get "sat") ctrlShow _isSat;
        if (!_isSat) then { ["hudHide", _ui get "hud"] call comspec_atak_native_fnc_tocSat; };
        _o setVariable ["COMSPEC_ATAK_TocVideo", false];
        private _anyLive = false;

        switch (_view) do {
            case "MAP": {};
            case "SAT": {
                private _n = ((_cfg param [8, 0]) max 0) min 1;
                private _tex = ["tex", [_side, _n]] call comspec_atak_native_fnc_tocSat;
                private _pic = _ui get "pic";
                if ((_pic getVariable ["COMSPEC_ATAK_Tex", ""]) isNotEqualTo _tex) then {
                    _pic setVariable ["COMSPEC_ATAK_Tex", _tex];
                    _pic ctrlSetText ([_tex, "#(argb,8,8,3)color(0,0,0,1)"] select (_tex isEqualTo "")); // i18n:brut (texture)
                };
                // Un écran du mur ne pilote rien : la main est au PC ou au téléphone du poste.
                // Bandeau = pied de l'écran : lecture seule et satellite à gauche, nom de l'écran à droite.
                (_ui get "strip") ctrlSetStructuredText parseText ((format ["<t size='1.15'>  <t color='@amber'>Lecture seule</t><t color='@text2'> · %1</t></t><t size='1.15' align='right' color='@muted'>%2  </t>", [[["label", [_side, _n]] call comspec_atak_native_fnc_tocSat, 70] call _cut] call _esc, [[_label, 16] call _cut] call _esc] call comspec_atak_native_fnc_tr) call _ink);
                (_ui get "wait") ctrlShow (_tex isEqualTo "");
                if (_tex isEqualTo "") then {
                    (_ui get "wait") ctrlSetStructuredText parseText (("<t align='center' size='1.6' color='@text'>Image satellite en attente</t><br/><t align='center' size='1' color='@muted'>Approchez-vous de l'écran : l'image n'est rendue qu'aux joueurs proches qui le regardent</t>" call comspec_atak_native_fnc_tr) call _ink);
                };
                ["overlay", [_o, _d]] call _fn;
                if (_tex isNotEqualTo "") then { ["live", [_o, _d]] call _fn; };
            };
            case "TOC";
            case "SPLIT": {
                // Panneau : RÉSEAU (4 cases), GROUPES (tableau), FLUX (3 dernières lignes). Carte : fn_tocScreen.
                ["divider" call _rg, "line", 1, "b1"] call _box;
                ["", "RÉSEAU", _netSub, "net"] call _section;
                ["tiles" call _rg, _netTiles] call _tiles;
                private _gs = [_nodes] call _groupsOf;
                ["", "GROUPES", format ["%1 éléments", count _gs], "grp"] call _section;
                private _pick = _op param [4, ""];
                [ "GRP_S", "grpCols" call _rg, "grpArea" call _rg, _gs apply {
                    _x params ["_gn", "_n", "_best", "_p"];
                    [[_gn, [["text", "amber", "red", "muted"] select _best, "amber"] select ((count _op) >= 5 && {_gn isEqualTo _pick})], [str _n, "text2"], [[_p] call _gridOf, "text2"]]
                }] call _table;
                ["", "FLUX", format ["%1 sur 20 min", { serverTime - (_x param [7, -1e9]) < 1200 } count _events], "flux"] call _section;
                private _ev = _events select [0 max ((count _events) - 3)];
                reverse _ev;
                if ((count _events) isEqualTo 0) then {
                    ["fluxArea" call _rg, "Aucun compte rendu ni message pour l'instant", "body", "muted"] call _text;
                } else {
                    ["FLUX_S", [], "fluxArea" call _rg, _ev apply {
                        _x params [["_time", ""], ["_kind", ""], ["_prio", ""], ["_who", ""]];
                        [[_time select [0, 5], "muted"], [_kind, [[_prio] call _prioTok, "text"] select ((toUpper _prio) in ["", "ROUTINE"])], [_who, "text2"]]
                    }, ""] call _table;
                };
            };
            case "SQUAD": {
                // Écran d'escouade : plaque du groupe, cases, membres (pastille d'état), dernier compte rendu ; caméra du chef
                // en bas s'il la partage (mêmes caméras que la mosaïque).
                private _squad = _ctx getOrDefault ["squad", ""];
                private _sqNodes = _ctx getOrDefault ["sqNodes", []];
                ["divider" call _rg, "line", 1, "b1"] call _box;
                ["plateBar" call _rg, "accent", 1, "b1"] call _box;
                ["plate" call _rg, toUpper ([_squad, "AUCUN GROUPE"] select (_squad isEqualTo "")), "squadName", "text"] call _text;
                if (_squad isEqualTo "") exitWith {
                    ["plateSub" call _rg, "Choisissez l'escouade au PC du poste, dans l'app Poste TOC ou au menu ACE de l'écran", "body", "text2"] call _text;
                };
                private _sc = [0, 0, 0, 0];
                { private _f = ((_x select 4) min 3) max 0; _sc set [_f, (_sc select _f) + (_x param [8, 1])]; } forEach _sqNodes;
                ["plateSub" call _rg, [format ["%1 nœud(s) ATAK · %2 en direct", count _sqNodes, _sc select 0], "Aucun ATAK de ce groupe sur le réseau"] select ((count _sqNodes) isEqualTo 0), "body", ["text2", "amber"] select ((count _sqNodes) isEqualTo 0)] call _text;
                ["tiles" call _rg, [[_sc select 0, "en ligne", "accent"], [_sc select 1, "anciens", "amber"], [_sc select 2, "perdus", "red"], [_sc select 3, "hors ligne", "muted"]]] call _tiles;
                ["", "MEMBRES", "grille · état", "mem"] call _section;
                // Caméra poitrine du chef, s'il la partage.
                private _ltex = "";
                private _lcs = "";
                private _gs = allGroups select { (groupId _x) isEqualTo _squad && {(str side _x) isEqualTo _side} };
                if ((count _gs) > 0) then {
                    private _lid = netId (leader (_gs select 0));
                    if (((["sources"] call comspec_atak_native_fnc_tocCam) findIf { (_x select 1) isEqualTo _lid }) >= 0) then {
                        _ltex = ["tex", _lid] call comspec_atak_native_fnc_tocMosaic;
                        _lcs = [leader (_gs select 0), true] call comspec_atak_native_fnc_unitCallsign;
                    };
                };
                private _cam = _ltex isNotEqualTo "";
                // Région en texte, pas en tableau : « [nom] call _rg » cherchait une région nommée ["memArea"], rendait
                // [0, 0, 0, 0] et le tableau, sans hauteur, ne montrait aucun membre (« 1 perdus » sans ligne dessous).
                ["MEM", [], (["memArea", "memAreaCam"] select _cam) call _rg, _sqNodes apply {
                    _x params ["", "_cs", "_p", "", "_fr"];
                    private _f = ((_fr min 3) max 0);
                    [["•", _stTok select _f], [_cs, "text"], [[_p] call _gridOf, "text2"], [_stLbl select _f, _stTok select _f]]
                }] call _table;
                if (_cam) then {
                    ["cam" call _rg, _ltex, format ["%1 · poitrine", _lcs], "bonne", "live"] call _camTile;
                    _o setVariable ["COMSPEC_ATAK_TocVideo", true];
                    _anyLive = true;
                } else {
                    // Dernier compte rendu d'un membre (auteur reconnu à son indicatif), deux lignes.
                    private _names = _sqNodes apply { toLower (_x select 1) };
                    private _ev = _events select { (toLower (_x param [3, ""])) in _names };
                    if ((count _ev) > 0) then {
                        (_ev select ((count _ev) - 1)) params [["_time", ""], ["_kind", ""], ["_prio", ""], ["_who", ""], ["_grid", ""], ["_txt", ""]];
                        ["", "DERNIER CR", format ["%1Z · %2", _time select [0, 5], _who], "cr"] call _section;
                        ("crText" call _rg) params ["_qx", "_qy", "_qw"];
                        [[_qx, _qy, _qw, 32], format ["%1 · %2%3", _kind, _prio, ["", " · grille " + _grid] select (_grid isNotEqualTo "")], "bodyBold", [[_prio] call _prioTok, "text"] select ((toUpper _prio) in ["", "ROUTINE"])] call _text;
                        [[_qx, _qy + 32, _qw, 32], _txt, "body", "text", "L", true] call _text;
                    } else {
                        ["", "DERNIER CR", "", "cr"] call _section;
                        ["crText" call _rg, "Aucun compte rendu de ce groupe pour l'instant", "body", "muted"] call _text;
                    };
                };
            };
            case "FEED": {
                [ "kpi" call _rg, [[{ serverTime - (_x param [7, -1e9]) < 1200 } count _events, "comptes rendus 20 min", "text"],
                    [{ (toUpper (_x param [2, ""])) isEqualTo "FLASH" } count _events, "FLASH", "red"],
                    [{ (toUpper (_x param [2, ""])) isEqualTo "URGENT" } count _events, "urgents", "amber"], [_cnt select 0, "nœuds en ligne", "accent"]], 4] call _tiles;
                ["FLUX DU CAMP", "du plus récent au plus ancien · heures ZULU"] call _sectionW;
                private _ev = +_events;
                reverse _ev;
                if ((count _ev) isEqualTo 0) exitWith { ["area" call _rg, "Aucun compte rendu ni message pour l'instant : ils s'inscrivent ici dès qu'un téléphone du camp en envoie", "body", "muted"] call _text; };
                ["FEED", "cols" call _rg, "area" call _rg, _ev apply {
                    _x params [["_time", ""], ["_kind", ""], ["_prio", ""], ["_who", ""], ["_grid", ""], ["_txt", ""], "", "", ["_old", false]];
                    private _pr = toUpper _prio;
                    [[_time select [0, 5], ["text2", "muted"] select _old], [_kind, ["text", "muted"] select _old], [[toLower _prio, ""] select (_pr in ["", "ROUTINE"]), [_prio] call _prioTok],
                     [_who, "text2"], [_grid, "text2"], [_txt, ["text", "muted"] select _old]]
                }, "+ %1 plus anciens au PC du poste"] call _table;
            };
            case "NET": {
                ["kpi" call _rg, _netTiles, 4] call _tiles;
                ["GROUPES DU RÉSEAU", _netSub] call _sectionW;
                private _gs = [_nodes] call _groupsOf;
                if ((count _gs) isEqualTo 0) exitWith { ["area" call _rg, "Aucun nœud ATAK de ce camp sur le réseau", "body", "muted"] call _text; };
                private _rows = _gs apply { _x params ["_gn", "_n", "_best", "_p"]; [[_gn, "text"], [str _n, "text2"], [_stLbl select _best, _stTok select _best], [[_p] call _gridOf, "text2"]] };
                private _per = floor ((("area" call _rg) select 3) / 40);
                ["NET", "cols" call _rg, "area" call _rg, _rows select [0, _per], ""] call _table;
                ["NET", "cols2" call _rg, "area2" call _rg, _rows select [_per], "+ %1 autres"] call _table;
            };
            case "ORBAT": {
                // ORBAT du site sur trois colonnes, par pages (10 s), chaque membre avec son état.
                ["load"] call comspec_atak_native_fnc_bftOrbat;
                private _store = ["store"] call comspec_atak_native_fnc_bftOrbat;
                (["presence"] call comspec_atak_native_fnc_bftOrbat) params ["", "_onKey"];
                (["notice"] call comspec_atak_native_fnc_bftOrbat) params ["_nTxt"];
                private _people = _store get "people";
                private _lines = [];
                {
                    _x params ["", "_ud", "_uName", "_uCode", "_uRows"];
                    _lines pushBack ["u", _uName + (["", format [" (%1)", _uCode]] select (_uCode isNotEqualTo ""))];
                    {
                        private _pe = _people getOrDefault [_x select 1, []];
                        if ((_x select 0) isEqualTo "v") then { _lines pushBack ["v", _x select 2]; } else {
                            _lines pushBack ["m", _pe param [0, ""], (_x select 1) in _onKey, (_pe param [4, 0]) isEqualTo 1];
                        };
                    } forEach _uRows;
                } forEach (_store get "units");
                private _per = floor ((("col1" call _rg) select 3) / 38);
                private _nPg = (ceil ((count _lines) / (3 * _per))) max 1;
                private _pg = (floor (diag_tickTime / 10)) mod _nPg;
                ["ORBAT DU SITE", _nTxt + (["", format [" · page %1 / %2", _pg + 1, _nPg]] select (_nPg > 1))] call _sectionW;
                if ((count _lines) isEqualTo 0) exitWith { ["col1" call _rg, "ORBAT du site non chargé", "body", "muted"] call _text; };
                {
                    (_x call _rg) params ["_x0", "_y0", "_w0"];
                    {
                        private _ry = _y0 + _forEachIndex * 38;
                        switch (_x select 0) do {
                            case "u": { [[_x0, _ry, _w0, 38], _x select 1, "bodyBold", "blue", "L", true] call _text; };
                            case "v": { [[_x0 + 8, _ry + 13, 12, 12], "line", 1, "b1"] call _box; [[_x0 + 24, _ry, _w0 - 24, 38], format ["%1 · poste vacant", _x select 1], "body", "muted"] call _text; };
                            default {
                                _x params ["", "_nm", "_on", "_off"];
                                [[_x0 + 8, _ry + 13, 12, 12], ["lineStrong", "accent"] select _on, 1, "b1"] call _box;
                                [[_x0 + 24, _ry, _w0 - 160, 38], _nm, "body", ["text2", "text"] select _on, "L", true] call _text;
                                [[_x0 + _w0 - 136, _ry, 136, 38], ["hors ligne", "en jeu"] select _on, "caption", ["muted", "accent"] select _on, "R"] call _text;
                            };
                        };
                    } forEach (_lines select [(_pg * 3 + _forEachIndex) * _per, _per]);
                } forEach ["col1", "col2", "col3"];
            };
            case "CLOCK": {
                // ZULU en très grand, groupe date-heure, heure locale de la mission, prochaine ligne d'exécution ; chronos à droite.
                systemTimeUTC params ["_yy", "_mo", "_dd", "_hh", "_mi"];
                private _two = { private _v = floor _this; [str _v, "0" + str _v] select (_v < 10) };
                private _mon = ["JAN", "FEB", "MAR", "APR", "MAY", "JUN", "JUL", "AUG", "SEP", "OCT", "NOV", "DEC"];
                ["zLbl" call _rg, "ZULU", "heading", "accent"] call _text;
                ["zDig" call _rg, _zulu, "display", "text", "L", true] call _text;
                ["dtg" call _rg, format ["DTG %1%2%3Z %4 %5", _dd call _two, _hh call _two, _mi call _two, _mon select ((_mo - 1) max 0), (_yy mod 100) call _two], "data", "text2", "L", true] call _text;
                ["lRule" call _rg, "line", 1, "b1"] call _box;
                ["lLbl" call _rg, "LOCAL (MISSION)", "heading", "muted"] call _text;
                ["lDig" call _rg, _local, "hero", "text2", "L", true] call _text;
                date params ["_my", "_mm", "_md"];
                ["lDate" call _rg, format ["date de la mission · %1 %2 %3", _md call _two, _mon select ((_mm - 1) max 0), _my], "body", "text2", "L", true] call _text;
                // Prochaine ligne d'exécution (première non passée) et temps restant si l'heure prévue est HH:MM en ZULU.
                private _nx = (_room select 4) select { (_x param [6, ""]) isEqualTo "" };
                ["", "PROCHAINE LIGNE", "liste d'exécution", "nx"] call _section;
                if ((count _nx) isEqualTo 0) then {
                    ["nxCode" call _rg, ["Aucune ligne à venir", "Liste d'exécution vide"] select ((count (_room select 4)) isEqualTo 0), "body", "muted"] call _text;
                } else {
                    (_nx select 0) params ["", "_n", "_ev", "_cw", "_el", "_at"];
                    ["nxCode" call _rg, format ["%1 · %2", _n, _cw], "title", "accent", "L", true] call _text;
                    ["nxEv" call _rg, format ["%1 · %2", _ev, _el], "body", "text", "L", true] call _text;
                    private _hm = (_at splitString ":") apply { parseNumber _x };
                    private _rest = "";
                    if ((count _hm) isEqualTo 2 && {(_at find ":") isEqualTo 2}) then {
                        systemTimeUTC params ["", "", "", "_h2", "_m2", "_s2"];
                        private _dsec = ((_hm select 0) * 3600 + (_hm select 1) * 60) - (_h2 * 3600 + _m2 * 60 + _s2);
                        if (_dsec > 43200) then { _dsec = _dsec - 86400; };
                        if (_dsec < -43200) then { _dsec = _dsec + 86400; };
                        _rest = [format [" · en retard de %1 min", round (abs _dsec / 60)], format [" · dans %1:%2", floor (_dsec / 60), (_dsec mod 60) call _two]] select (_dsec >= 0);
                    };
                    ["nxAt" call _rg, format ["prévu %1Z%2", _at, _rest], "data", "text2"] call _text;
                };
                ["divider" call _rg, "line", 1, "b1"] call _box;
                private _tm = _room select 1;
                ["", "CHRONOS", format ["%1 chronos du poste", count _tm], "t"] call _section;
                ("tArea" call _rg) params ["_ax", "_ay", "_aw", "_ah"];
                if ((count _tm) isEqualTo 0) exitWith { [[_ax, _ay, _aw, 40], "Aucun chrono : créez-en un au PC du poste (onglet CHRONOS)", "body", "muted"] call _text; };
                private _maxT = floor (_ah / 76);
                private _shown = [_maxT, _maxT - 1] select ((count _tm) > _maxT);
                private _tc = (_thm get "cols") get "TIMER";
                {
                    (["timerText", _x] call comspec_atak_native_fnc_tocRoom) params ["_txt", "", "_state", "_hex"];
                    _x params ["", "_tn", ["_kind", "UP"], ["_val", 0], ["_run", false]];
                    private _tok = switch (true) do { case (_state isEqualTo "OVER"): { "red" }; case (_state isNotEqualTo "RUN"): { "muted" }; case (_kind isEqualTo "AT"): { "amber" }; default { "accent" }; };
                    private _sub = (switch (_kind) do {
                        case "DOWN": { "compte à rebours" };
                        case "AT": { format ["heure H %1:%2Z", floor (_val / 60) call _two, (_val mod 60) call _two] };
                        default { "chrono" };
                    }) + (switch (_state) do { case "OVER": { " · dépassé" }; case "RUN": { [" · en marche", ""] select (_kind isEqualTo "AT") }; default { " · arrêté" }; });
                    private _ry = _ay + _forEachIndex * 76;
                    [[_ax + ((_tc select 0) select 2), _ry + 4, (_tc select 0) select 3, 40], _tn, "heading", "text", "L", true] call _text;
                    [[_ax + ((_tc select 1) select 2), _ry + 42, (_tc select 1) select 3, 32], _sub, "caption", "muted"] call _text;
                    [[_ax + ((_tc select 2) select 2), _ry + 10, (_tc select 2) select 3, 56], _txt, "num", _tok, "R", true] call _text;
                    [[_ax, _ry + 75, _aw, 1], "line", 0.7, "b1"] call _box;
                } forEach (_tm select [0, _shown]);
                if ((count _tm) > _shown) then { [[_ax, _ay + _shown * 76, _aw, 40], "+ %1 autres chronos au PC du poste", "caption", "muted"] call _text; };
            };
            case "LOG": {
                private _l = +(_room select 3);
                reverse _l;
                ["MAIN COURANTE", format ["%1 entrées · heures ZULU", count _l]] call _sectionW;
                if ((count _l) isEqualTo 0) exitWith { ["area" call _rg, "Main courante vide : comptes rendus, prises de poste, FLASH et chronos s'y inscrivent seuls", "body", "muted"] call _text; };
                private _kTok = createHashMapFromArray [["FLASH", "red"], ["DEMANDE", "amber"], ["EXCHECK", "accent"], ["POSTE", "blue"], ["CHRONO", "text2"], ["CR", "amber"]];
                private _kLbl = createHashMapFromArray [["FLASH", "FLASH"], ["DEMANDE", "demande"], ["EXCHECK", "exécution"], ["POSTE", "poste"], ["CHRONO", "chrono"], ["CR", "compte rendu"]];
                ["LOG", "cols" call _rg, "area" call _rg, _l apply {
                    _x params ["_t", "_k", "_w", "_txt"];
                    [[_t select [0, 8], "text2"], [_kLbl getOrDefault [_k, toLower _k], _kTok getOrDefault [_k, "muted"]], [_txt, "text"], [_w, "text2"]]
                }, "+ %1 plus anciennes au PC du poste"] call _table;
            };
            case "EXCHECK": {
                private _ex = _room select 4;
                private _done = { (_x param [6, ""]) isNotEqualTo "" } count _ex;
                ["LISTE D'EXÉCUTION", format ["%1 / %2 passés", _done, count _ex]] call _sectionW;
                ("prog" call _rg) params ["_px", "_py", "_pw", "_ph"];
                [[_px, _py, _pw, _ph], "line", 1, "b1"] call _box;
                if ((count _ex) > 0) then { [[_px, _py, _pw * _done / (count _ex), _ph], "accent", 1, "b1"] call _box; };
                if ((count _ex) isEqualTo 0) exitWith { ["area" call _rg, "Aucune ligne : préparez la liste au PC du poste (onglet EXCHECK) ou dans le module Écran TOC", "body", "muted"] call _text; };
                private _nxt = _ex findIf { (_x param [6, ""]) isEqualTo "" };
                // Fenêtre qui suit l'avancement : la ligne suivante reste visible (deux lignes passées au-dessus d'elle).
                private _cap = floor ((("area" call _rg) select 3) / 40);
                private _from = (((_nxt max 0) - 2) max 0) min (((count _ex) - _cap) max 0);
                ["EXCHECK", "cols" call _rg, "area" call _rg, (_ex select [_from]) apply {
                    _x params ["", "_n", "_ev", "_cw", "_el", "_at", ["_dn", ""]];
                    private _ok = _dn isNotEqualTo "";
                    private _isN = (_ex find _x) isEqualTo _nxt;
                    [[str _n, "text2"], [_cw, ["text", "accent"] select _ok], [_ev, ["text", "text2"] select _ok], [_el, "text2"], [_at, "text2"], [[_dn, "—"] select (!_ok), ["muted", "accent"] select _ok],
                     [[["prévu", "prochain"] select _isN, "passé"] select _ok, [["muted", "amber"] select _isN, "accent"] select _ok]]
                }, "+ %1 autres lignes", [-1, _nxt - _from] select (_nxt >= 0)] call _table;
            };
            case "KILLS": {
                private _k = _room select 8;
                private _tot = [0, 0, 0, 0];
                { for "_i" from 0 to 3 do { _tot set [_i, (_tot select _i) + ((_x select (_i + 1)) max 0)]; }; } forEach _k;
                ["kpi" call _rg, [[_tot select 0, "amis blessés", "amber"], [_tot select 1, "amis tués", "red"], [_tot select 2, "ennemis neutralisés", "accent"], [_tot select 3, "ennemis blessés / pris", "text2"]], 4] call _tiles;
                ["PAR ÉLÉMENT", "réseau ATAK, BDA et corrections du poste"] call _sectionW;
                ("groups" call _rg) params ["_gx", "_gy", "", "_gh"];
                { _x params ["", "_lbl", "_cx", "_cw"]; [[_gx + _cx, _gy, _cw, _gh], _lbl, "caption", "text2", "R"] call _text; [[_gx + _cx + 8, _gy + _gh - 2, _cw - 8, 1], "lineStrong", 1, "b1"] call _box; } forEach ((_thm get "cols") get "KILLS_G");
                if ((count _k) isEqualTo 0) exitWith { ["area" call _rg, "Rien de rapporté : seuls comptent les pertes amies connues du réseau ATAK et les ennemis déclarés par compte rendu", "body", "muted"] call _text; };
                private _nr = ["KILLS", "cols" call _rg, "area" call _rg, _k apply {
                    _x params ["_el", "_wia", "_kia", "_ekia", "_ewia"];
                    [[_el, "text"], [str _wia, ["muted", "amber"] select (_wia > 0)], [str _kia, ["muted", "red"] select (_kia > 0)], [str _ekia, ["muted", "accent"] select (_ekia > 0)], [str _ewia, ["muted", "text"] select (_ewia > 0)]]
                }] call _table;
                // Ligne TOTAL sous un filet fort (si elle tient).
                ("area" call _rg) params ["_ax", "_ay", "_aw", "_ah"];
                private _ty = _ay + _nr * 40;
                if (_ty + 40 <= _ay + _ah && {(count _k) <= _nr}) then {
                    [[_ax, _ty, _aw, 2], "lineStrong", 1, "b1"] call _box;
                    { _x params ["", "", "_cx", "_cw", "_al"]; [[_ax + _cx, _ty, _cw, 40], [str (_tot select ((_forEachIndex - 1) max 0)), "TOTAL"] select (_forEachIndex isEqualTo 0), ["dataBold", "bodyBold"] select (_forEachIndex isEqualTo 0), "text", _al] call _text; } forEach ((_thm get "cols") get "KILLS");
                };
            };
            case "REQ": {
                private _l = +(_room select 5);
                reverse _l;
                private _stl = createHashMapFromArray [["RECUE", ["reçue", "amber"]], ["PRISE", ["prise en compte", "blue"]], ["REFUSEE", ["refusée", "red"]], ["TERMINEE", ["terminée", "accent"]]];
                ["kpi" call _rg, [[{ (_x select 7) isEqualTo "RECUE" } count _l, "reçues", "amber"], [{ (_x select 7) isEqualTo "PRISE" } count _l, "prises en compte", "blue"],
                    [{ (_x select 7) isEqualTo "TERMINEE" } count _l, "terminées", "accent"], [{ (_x select 7) isEqualTo "REFUSEE" } count _l, "refusées", "red"]], 4] call _tiles;
                ["DEMANDES DES ÉLÉMENTS", "MEDEVAC, appuis feu, ravitaillements"] call _sectionW;
                if ((count _l) isEqualTo 0) exitWith { ["area" call _rg, "Aucune demande reçue des éléments", "body", "muted"] call _text; };
                ["REQ", "cols" call _rg, "area" call _rg, _l apply {
                    _x params ["", "_t", "_k", "_by", "", "_grid", "_txt", "_stt", ["_ans", ""]];
                    private _s = _stl getOrDefault [_stt, [toLower _stt, "muted"]];
                    [[_t, "text2"], [_k, "text"], [_by, "text2"], [_grid, "text2"], [_txt + (["", " → " + _ans] select (_ans isNotEqualTo "")), "text"], _s]
                }, "+ %1 plus anciennes au PC du poste"] call _table;
            };
            case "MOSAIC": {
                (["cfg", _o] call comspec_atak_native_fnc_tocMosaic) params ["_gr", "_mm", "_grp"];
                private _tl = ["tiles", _o] call comspec_atak_native_fnc_tocMosaic;
                private _n = _gr * _gr;
                // Plafond du poste : au-delà, les vignettes passent à tour de rôle (2 s chacune).
                private _ids = (_tl apply { _x select 0 }) select { _x isNotEqualTo "" };
                private _b = ["budget"] call comspec_atak_native_fnc_tocMosaic;
                private _liveIds = _ids;
                if ((count _ids) > _b) then {
                    private _st0 = ((floor (time / 2)) * (_b max 1)) mod ((count _ids) max 1);
                    _liveIds = (_ids + _ids) select [_st0, _b];
                };
                ("area" call _rg) params ["_gx", "_gy", "_gw", "_gh"];
                private _tw = (_gw - (_gr - 1) * 16) / _gr;
                private _th = (_gh - (_gr - 1) * 16) / _gr;
                for "_i" from 0 to (_n - 1) do {
                    (_tl param [_i, ["", ""]]) params ["_id", "_lbl"];
                    private _r = [_gx + (_i mod _gr) * (_tw + 16), _gy + (floor (_i / _gr)) * (_th + 16), _tw, _th];
                    if (_id isEqualTo "") then { [_r, "", "", "", "free"] call _camTile; continue };
                    (["info", _id] call comspec_atak_native_fnc_tocMosaic) params ["_cs", "_lk", "_res"];
                    private _tex = ["", ["tex", _id] call comspec_atak_native_fnc_tocMosaic] select (_id in _liveIds && {_res > 0});
                    if (_tex isNotEqualTo "") then { _anyLive = true; };
                    [_r, _tex, [_cs, _lbl] select (_cs isEqualTo ""), _lk, [["wait", "nosig"] select (_res <= 0), "live"] select (_tex isNotEqualTo "")] call _camTile;
                };
            };
            case "COLA": {
                // Colonne horloge : ZULU en grand, groupe date-heure, heure locale de la mission, chronos du poste.
                [[0, 0, 800, 900], "bg0"] call _box;
                systemTimeUTC params ["_yy", "_mo", "_dd", "_hh", "_mi"];
                private _two = { private _v = floor _this; [str _v, "0" + str _v] select (_v < 10) };
                private _mon = ["JAN", "FEB", "MAR", "APR", "MAY", "JUN", "JUL", "AUG", "SEP", "OCT", "NOV", "DEC"];
                ["zLbl" call _rg, "ZULU", "heading", "accent"] call _text;
                ["zDig" call _rg, _zulu, "display", "text", "L", true] call _text;
                ["dtg" call _rg, format ["DTG %1%2%3Z %4 %5", _dd call _two, _hh call _two, _mi call _two, _mon select ((_mo - 1) max 0), (_yy mod 100) call _two], "data", "text2", "L", true] call _text;
                ["lRule" call _rg, "line", 1, "b1"] call _box;
                ["lLbl" call _rg, "LOCAL (MISSION)", "heading", "muted"] call _text;
                ["lDig" call _rg, _local, "hero", "text2", "L", true] call _text;
                date params ["_my", "_mm", "_md"];
                ["lDate" call _rg, format ["date de la mission · %1 %2 %3", _md call _two, _mon select ((_mm - 1) max 0), _my], "body", "text2", "L", true] call _text;
                private _tm = _room select 1;
                ["", "CHRONOS", format ["%1 chronos du poste", count _tm], "t"] call _section;
                ("tArea" call _rg) params ["_ax", "_ay", "_aw", "_ah"];
                if ((count _tm) isEqualTo 0) exitWith { [[_ax, _ay, _aw, 40], "Aucun chrono : créez-en un au PC du poste (onglet CHRONOS)", "body", "muted"] call _text; };
                private _maxT = floor (_ah / 76);
                private _shown = [_maxT, _maxT - 1] select ((count _tm) > _maxT);
                private _tc = (_thm get "cols") get "TIMER";
                {
                    (["timerText", _x] call comspec_atak_native_fnc_tocRoom) params ["_txt", "", "_state"];
                    _x params ["", "_tn", ["_kind", "UP"]];
                    private _tok = switch (true) do { case (_state isEqualTo "OVER"): { "red" }; case (_state isNotEqualTo "RUN"): { "muted" }; case (_kind isEqualTo "AT"): { "amber" }; default { "accent" }; };
                    private _ry = _ay + _forEachIndex * 76;
                    [[_ax + ((_tc select 0) select 2), _ry + 16, (_tc select 0) select 3, 40], _tn, "heading", "text", "L", true] call _text;
                    [[_ax + _aw - ((_tc select 2) select 3), _ry + 10, (_tc select 2) select 3, 56], _txt, "num", _tok, "R", true] call _text;
                    [[_ax, _ry + 75, _aw, 1], "line", 0.7, "b1"] call _box;
                } forEach (_tm select [0, _shown]);
                if ((count _tm) > _shown) then { [[_ax, _ay + _shown * 76, _aw, 40], "+ %1 autres chronos au PC du poste", "caption", "muted"] call _text; };
            };
            case "COLB": {
                // Colonne flux : RÉSEAU (4 cases), GROUPES (tableau), FLUX (dernières lignes).
                [[0, 0, 800, 900], "bg0"] call _box;
                ["", "RÉSEAU", _netSub, "net"] call _section;
                ["tiles" call _rg, _netTiles] call _tiles;
                private _gs = [_nodes] call _groupsOf;
                ["", "GROUPES", format ["%1 éléments", count _gs], "grp"] call _section;
                ["GRP_S", "grpCols" call _rg, "grpArea" call _rg, _gs apply {
                    _x params ["_gn", "_n", "_best", "_p"];
                    [[_gn, ["text", "amber", "red", "muted"] select _best], [str _n, "text2"], [[_p] call _gridOf, "text2"]]
                }] call _table;
                ["", "FLUX", format ["%1 sur 20 min", { serverTime - (_x param [7, -1e9]) < 1200 } count _events], "flux"] call _section;
                private _ev = _events select [0 max ((count _events) - 6)];
                reverse _ev;
                if ((count _events) isEqualTo 0) then {
                    ["fluxArea" call _rg, "Aucun compte rendu ni message pour l'instant", "body", "muted"] call _text;
                } else {
                    ["FLUX_S", [], "fluxArea" call _rg, _ev apply {
                        _x params [["_time", ""], ["_kind", ""], ["_prio", ""], ["_who", ""]];
                        [[_time select [0, 5], "muted"], [_kind, [[_prio] call _prioTok, "text"] select ((toUpper _prio) in ["", "ROUTINE"])], [_who, "text2"]]
                    }, ""] call _table;
                };
            };
            case "COLC": {
                // Colonne caméras : deux vignettes 16:9 l'une sous l'autre (jamais étirées), mêmes sources et même réglage
                // que la mosaïque (COMSPEC_ATAK_TocMosaic : tout le camp, un groupe ou case par case) ; plus de deux
                // sources : pages de deux qui tournent. Plafond du poste : celui de la mosaïque (fn_tocMosaic « budget »).
                [[0, 0, 800, 900], "bg0"] call _box;
                (["cfg", _o] call comspec_atak_native_fnc_tocMosaic) params ["", "_mm", "_grp", "_man", "_rot"];
                private _src = ["sources"] call comspec_atak_native_fnc_tocCam;
                private _ids = [];
                if (_mm isEqualTo "MANUAL") then {
                    _ids = (_man select { _x isEqualType "" && {_x isNotEqualTo ""} }) apply { private _id = _x; [_id, ((_src select { (_x select 1) isEqualTo _id }) param [0, [""]]) select 0] };
                } else {
                    if (_mm isEqualTo "GROUP") then {
                        _src = _src select { private _u = objectFromNetId (_x select 1); !isNull _u && {(groupId group _u) isEqualTo _grp || {(groupId group (effectiveCommander _u)) isEqualTo _grp}} };
                    };
                    _src = [_src, [], { ["MAN", "UAV", "CAM"] find (_x select 2) }, "ASCEND"] call BIS_fnc_sortBy;
                    _ids = _src apply { [_x select 1, _x select 0] };
                };
                private _pages = (ceil ((count _ids) / 2)) max 1;
                private _pg = [0, (floor (time / (_rot max 5))) mod _pages] select (_pages > 1);
                private _page = _ids select [_pg * 2, 2];
                ["", "CAMÉRAS", format ["%1 sources", count _ids], "cam"] call _section;
                private _bud = ["budget"] call comspec_atak_native_fnc_tocMosaic;
                {
                    private _cr = _x call _rg;
                    (_page param [_forEachIndex, ["", ""]]) params ["_id", "_lbl"];
                    if (_id isEqualTo "") then { [_cr, "", "", "", "free"] call _camTile; continue };
                    (["info", _id] call comspec_atak_native_fnc_tocMosaic) params ["_cs", "_lk", "_res"];
                    private _tex = ["", ["tex", _id] call comspec_atak_native_fnc_tocMosaic] select (_res > 0 && {_forEachIndex < _bud});
                    if (_tex isNotEqualTo "") then { _anyLive = true; };
                    [_cr, _tex, [_cs, _lbl] select (_cs isEqualTo ""), _lk, [["wait", "nosig"] select (_res <= 0), "live"] select (_tex isNotEqualTo "")] call _camTile;
                } forEach ["cam1", "cam2"];
                // Image vivante : gardée dans la boucle « live » tant que la colonne montre une caméra.
                if (_anyLive) then { _o setVariable ["COMSPEC_ATAK_TocVideo", true]; };
            };
            case "COLD": {
                // Colonne main courante : entrée sur deux lignes (heure ZULU, type, auteur ; texte dessous), la plus récente en haut.
                [[0, 0, 800, 900], "bg0"] call _box;
                private _l = +(_room select 3);
                reverse _l;
                ["", "MAIN COURANTE", format ["%1 entrées · heures ZULU", count _l], "log"] call _section;
                ("logArea" call _rg) params ["_ax", "_ay", "_aw", "_ah"];
                if ((count _l) isEqualTo 0) exitWith { [[_ax, _ay, _aw, 40], "Main courante vide", "body", "muted"] call _text; };
                private _kTok = createHashMapFromArray [["FLASH", "red"], ["DEMANDE", "amber"], ["EXCHECK", "accent"], ["POSTE", "blue"], ["CHRONO", "text2"], ["CR", "amber"]];
                private _kLbl = createHashMapFromArray [["FLASH", "FLASH"], ["DEMANDE", "demande"], ["EXCHECK", "exécution"], ["POSTE", "poste"], ["CHRONO", "chrono"], ["CR", "compte rendu"]];
                private _lc = (_thm get "cols") get "LOG_C";
                private _cap = floor (_ah / 84);
                private _shown = [_cap, _cap - 1] select ((count _l) > _cap);
                {
                    _x params [["_lt", ""], ["_lk", ""], ["_lw", ""], ["_ltx", ""]];
                    private _ry = _ay + _forEachIndex * 84;
                    [[_ax + ((_lc select 0) select 2), _ry + 4, (_lc select 0) select 3, 36], _lt select [0, 8], "data", "text2", "L", true] call _text;
                    [[_ax + ((_lc select 1) select 2), _ry + 2, (_lc select 1) select 3, 40], _kLbl getOrDefault [_lk, toLower _lk], _kTok getOrDefault [_lk, "muted"]] call _pill;
                    [[_ax + ((_lc select 2) select 2), _ry + 4, (_lc select 2) select 3, 36], _lw, "body", "text2", "R"] call _text;
                    [[_ax, _ry + 42, _aw, 36], _ltx, "body", "text"] call _text;
                    [[_ax, _ry + 83, _aw, 1], "line", 0.7, "b1"] call _box;
                } forEach (_l select [0, _shown]);
                if ((count _l) > _shown) then {
                    [[_ax, _ay + _shown * 84, _aw, 40], format [("+ %1 plus anciennes au PC du poste") call comspec_atak_native_fnc_tr, (count _l) - _shown], "caption", "muted", "L", true] call _text;
                };
            };
            case "COLE": {
                // Colonne exécution : prochaine ligne (mot de code, événement, temps restant), puis la liste avec sa barre
                // d'avancement ; la fenêtre suit l'avancement comme sur l'affichage EXCHECK.
                [[0, 0, 800, 900], "bg0"] call _box;
                private _ex = _room select 4;
                private _two = { private _v = floor _this; [str _v, "0" + str _v] select (_v < 10) };
                private _nxt = _ex findIf { (_x param [6, ""]) isEqualTo "" };
                ["", "PROCHAINE LIGNE", "liste d'exécution", "nx"] call _section;
                if (_nxt < 0) then {
                    ["nxCode" call _rg, ["Aucune ligne à venir", "Liste d'exécution vide"] select ((count _ex) isEqualTo 0), "body", "muted"] call _text;
                } else {
                    (_ex select _nxt) params ["", "_n", "_ev", "_cw", "_el", "_at"];
                    ["nxCode" call _rg, format ["%1 · %2", _n, _cw], "title", "accent", "L", true] call _text;
                    ["nxEv" call _rg, format ["%1 · %2", _ev, _el], "body", "text", "L", true] call _text;
                    private _hm = (_at splitString ":") apply { parseNumber _x };
                    private _rest = "";
                    if ((count _hm) isEqualTo 2 && {(_at find ":") isEqualTo 2}) then {
                        systemTimeUTC params ["", "", "", "_h2", "_m2", "_s2"];
                        private _dsec = ((_hm select 0) * 3600 + (_hm select 1) * 60) - (_h2 * 3600 + _m2 * 60 + _s2);
                        if (_dsec > 43200) then { _dsec = _dsec - 86400; };
                        if (_dsec < -43200) then { _dsec = _dsec + 86400; };
                        _rest = [format [" · en retard de %1 min", round (abs _dsec / 60)], format [" · dans %1:%2", floor (_dsec / 60), (_dsec mod 60) call _two]] select (_dsec >= 0);
                    };
                    ["nxAt" call _rg, format ["prévu %1Z%2", _at, _rest], "data", "text2"] call _text;
                };
                private _done = { (_x param [6, ""]) isNotEqualTo "" } count _ex;
                ["", "LISTE D'EXÉCUTION", format ["%1 / %2 passés", _done, count _ex], "ex"] call _section;
                ("exProg" call _rg) params ["_px", "_py", "_pw", "_ph"];
                [[_px, _py, _pw, _ph], "line", 1, "b1"] call _box;
                if ((count _ex) > 0) then { [[_px, _py, _pw * _done / (count _ex), _ph], "accent", 1, "b1"] call _box; };
                ("exArea" call _rg) params ["_ax", "_ay", "_aw", "_ah"];
                if ((count _ex) isEqualTo 0) exitWith { [[_ax, _ay, _aw, 40], "Préparez la liste au PC du poste (onglet EXCHECK)", "body", "muted"] call _text; };
                private _cap = floor (_ah / 40);
                private _from = (((_nxt max 0) - 2) max 0) min (((count _ex) - _cap) max 0);
                ["EXCHECK_C", "exCols" call _rg, [_ax, _ay, _aw, _ah], (_ex select [_from]) apply {
                    _x params ["", "_n", "", "_cw", "", "_at", ["_dn", ""]];
                    private _ok = _dn isNotEqualTo "";
                    private _isN = (_ex find _x) isEqualTo _nxt;
                    [[str _n, "text2"], [_cw, ["text", "accent"] select _ok], [_at, "text2"], [[["prévu", "prochain"] select _isN, "passé"] select _ok, [["muted", "amber"] select _isN, "accent"] select _ok]]
                }, "+ %1 autres lignes", [-1, _nxt - _from] select (_nxt >= 0)] call _table;
            };
            case "COLF": {
                // Colonne demandes et pertes : dernières demandes des éléments (statut en pastille), puis pertes et
                // résultats (quatre cases de totaux, tableau par élément).
                [[0, 0, 800, 900], "bg0"] call _box;
                private _l = +(_room select 5);
                reverse _l;
                private _stl = createHashMapFromArray [["RECUE", ["reçue", "amber"]], ["PRISE", ["prise en compte", "blue"]], ["REFUSEE", ["refusée", "red"]], ["TERMINEE", ["terminée", "accent"]]];
                ["", "DEMANDES", format ["%1 reçues", { (_x select 7) isEqualTo "RECUE" } count _l], "req"] call _section;
                if ((count _l) isEqualTo 0) then {
                    ("reqArea" call _rg) params ["_qx", "_qy", "_qw"];
                    [[_qx, _qy, _qw, 40], "Aucune demande reçue des éléments", "body", "muted"] call _text;
                } else {
                    ["REQ_C", [], "reqArea" call _rg, _l apply {
                        _x params ["", "_rt", "_rk", "_by", "", "", "", "_stt"];
                        [[_rt, "text2"], [_rk, "text"], [_by, "text2"], _stl getOrDefault [_stt, [toLower _stt, "muted"]]]
                    }, "+ %1 plus anciennes au PC du poste"] call _table;
                };
                private _kl = _room select 8;
                private _tot = [0, 0, 0, 0];
                { for "_i" from 0 to 3 do { _tot set [_i, (_tot select _i) + ((_x select (_i + 1)) max 0)]; }; } forEach _kl;
                ["", "PERTES ET RÉSULTATS", format ["%1 éléments", count _kl], "kill"] call _section;
                ["killTiles" call _rg, [[_tot select 0, "amis blessés", "amber"], [_tot select 1, "amis tués", "red"], [_tot select 2, "ennemis neutralisés", "accent"], [_tot select 3, "ennemis blessés / pris", "text2"]]] call _tiles;
                ["KILLS_C", "killCols" call _rg, "killArea" call _rg, _kl apply {
                    _x params ["_el", "_wia", "_kia", "_ekia"];
                    [[_el, "text"], [str _wia, ["muted", "amber"] select (_wia > 0)], [str _kia, ["muted", "red"] select (_kia > 0)], [str _ekia, ["muted", "accent"] select (_ekia > 0)]]
                }] call _table;
            };
            case "STRIP": {
                // Bandeau 4:1 : ZULU, LOCAL, premier chrono du poste. Écrit en caractères à LED (fn_tocText « label », une
                // image par caractère dans une case au format de la face) : le texte du jeu y serait étiré 3 fois (une
                // texture d'interface est dessinée comme un écran 4:3) et coupé (« 20 1: » au lieu de l'heure). Fond noir.
                ["labelBegin", [_d, [[0, 0, 1600, 400], "bg0"] call _box]] call comspec_atak_native_fnc_tocText;
                private _led = {
                    params ["_k", "_s", "_tok", ["_raw", false], ["_min", 0.6]];
                    ["label", [_d, (_ui get "lays") get "T", "S_" + _k, (_k call _rg) call _toR, _texW / _texH, [_s call comspec_atak_native_fnc_tr, _s] select _raw, _tok, "L", _min]] call comspec_atak_native_fnc_tocText
                };
                systemTimeUTC params ["_yy", "_mo", "_dd", "_hh", "_mi"];
                private _two = { private _v = floor _this; [str _v, "0" + str _v] select (_v < 10) };
                private _mon = ["JAN", "FEB", "MAR", "APR", "MAY", "JUN", "JUL", "AUG", "SEP", "OCT", "NOV", "DEC"];
                ["zLbl", "ZULU", "accent"] call _led;
                ["zDig", _zulu, "text", true] call _led;
                ["zSub", format ["DTG %1%2%3Z %4 %5", _dd call _two, _hh call _two, _mi call _two, _mon select ((_mo - 1) max 0), (_yy mod 100) call _two], "text2", true] call _led;
                ["div1" call _rg, "line", 1, "b1"] call _box;
                ["lLbl", "LOCAL", "muted"] call _led;
                ["lDig", _local select [0, 5], "text2", true] call _led;
                date params ["_my", "_mm", "_md"];
                ["lSub", format ["%1 %2 %3", _md call _two, _mon select ((_mm - 1) max 0), _my], "text2", true] call _led;
                ["div2" call _rg, "line", 1, "b1"] call _box;
                private _t = (_room select 1) param [0, []];
                if ((count _t) isEqualTo 0) exitWith { ["cLbl", "AUCUN CHRONO", "muted"] call _led; };
                (["timerText", _t] call comspec_atak_native_fnc_tocRoom) params ["_x1", "", "_state"];
                ["cLbl", _t select 1, "text", true] call _led;
                ["cDig", _x1, switch (true) do { case (_state isEqualTo "OVER"): { "red" }; case (_state isNotEqualTo "RUN"): { "muted" }; case ((_t param [2, "UP"]) isEqualTo "AT"): { "amber" }; default { "accent" }; }, true] call _led;
                ["cSub", (createHashMapFromArray [["RUN", "en marche"], ["STOP", "arrêté"], ["OVER", "dépassé"]]) getOrDefault [_state, ""], "muted"] call _led;
            };
            case "PLATE": {
                // Plaque 4:1 : nom du groupe lié (écran d'escouade choisi), sinon le nom de l'écran ; état ATAK du groupe dessous.
                // Caractères à LED (fn_tocText « label ») au format de la face : le nom tient toujours : pleine taille,
                // sinon réduit jusqu'à la moitié, puis coupé avec « … » (le texte du jeu, étiré 3 fois, montrait « ÉLÉME »).
                ["labelBegin", [_d, [[0, 0, 1600, 400], "bg0"] call _box]] call comspec_atak_native_fnc_tocText;
                private _lay = (_ui get "lays") get "T";
                ["bar" call _rg, "accent", 1, "b1"] call _box;
                private _gname = _cfg param [9, ""];
                ["label", [_d, _lay, "P_name", ("name" call _rg) call _toR, _texW / _texH, toUpper ([_gname, _name] select (_gname isEqualTo "")), "text", "L", 0.5]] call comspec_atak_native_fnc_tocText;
                if (_gname isNotEqualTo "") then {
                    private _ns = _nodes select { (_x param [7, ""]) isEqualTo _gname };
                    ("sub" call _rg) params ["_qx", "_qy", "_qw", "_qh"];
                    [[_qx + 4, _qy + _qh / 2 - 14, 28, 28], ["lost", "accent"] select ((count _ns) > 0), 1, "b1"] call _box;
                    ["label", [_d, _lay, "P_sub", [_qx + 44, _qy, _qw - 44, _qh] call _toR, _texW / _texH,
                        ([format ["%1 nœud(s) ATAK · %2 en direct", count _ns, { (_x select 4) isEqualTo 0 } count _ns], "hors réseau"] select ((count _ns) isEqualTo 0)) call comspec_atak_native_fnc_tr, "text2", "L", 0.6]] call comspec_atak_native_fnc_tocText;
                };
            };
        };
        // Contrôles non repris à cette image : masqués.
        {
            _x params ["_list", "_used"];
            for "_i" from _used to ((count _list) - 1) do { (_list select _i) ctrlShow false; };
        } forEach (values _pools);
        if (_anyLive) then { ["live", [_o, _d]] call _fn; };
        true
    };
    case "boardText": {
        // Textes structurés LOG, EXCHECK, KILLS, REQ (le PC du poste lit KILLS) : [colonne gauche, colonne droite].
        _a params ["_view", "_room", ["_side", ""], ["_wall", false]];
        private _out = switch (_view) do {
            case "LOG": {
                private _l = (_room select 3) select [0 max ((count (_room select 3)) - 20)];
                reverse _l;
                private _rows = _l apply {
                    _x params ["_t", "_k", "_w", "_txt"];
                    private _c = (createHashMapFromArray [["FLASH", "red"], ["DEMANDE", "amber"], ["EXCHECK", "accent"], ["POSTE", "blue"], ["CHRONO", "text2"], ["CR", "amber"]]) getOrDefault [_k, "text"];
                    format ["<t size='1'><t color='@muted'>%1Z</t> <t color='@%2' font='RobotoCondensedBold'>%3</t> %4 <t color='@muted'>· %5</t></t>", (_t select [0, 8]), _c, [[_k, 10] call _cut] call _esc, [[_txt, 90] call _cut] call _esc, [[_w, 20] call _cut] call _esc]
                };
                if ((count _rows) isEqualTo 0) then { _rows = ["<t size='1.1' color='@muted'>Main courante vide : comptes rendus, prises de poste, FLASH et chronos s'y inscrivent seuls</t>"]; };
                [_rows joinString "<br/>", ""]
            };
            case "EXCHECK": {
                private _rows = ((_room select 4) select [0, 18]) apply {
                    _x params ["", "_n", "_ev", "_cw", "_el", "_at", "_done"];
                    private _ok = _done isNotEqualTo "";
                    format ["<t size='1.15'><t color='@%1'>%2</t> <t font='RobotoCondensedBold'>%3</t> %4 <t color='@amber'>%5</t> <t color='@muted'>· %6</t><t align='right' color='@muted'>%7 %8</t></t>",
                        ["text", "accent"] select _ok, [format ["%1.", _n], "✔"] select _ok, [[_cw, 16] call _cut] call _esc, [[_ev, 40] call _cut] call _esc, [[_el, 16] call _cut] call _esc, ["prévu", "passé"] select _ok,
                        [[_at] call _esc, ""] select _ok, [["", _done] select _ok] call _esc]
                };
                if ((count _rows) isEqualTo 0) then { _rows = ["<t size='1.1' color='@muted'>Aucune ligne : préparez la liste au PC du poste (onglet EXCHECK) ou dans le module Écran TOC</t>"]; };
                [_rows joinString "<br/>", ""]
            };
            case "KILLS": {
                private _rows = ["<t size='1.3' font='RobotoCondensedBold'>ÉLÉMENT<t align='right'>AMIS BLESSÉS · TUÉS   ENNEMIS NEUTRALISÉS · BLESSÉS</t></t>"];
                {
                    _x params ["_el", "_wia", "_kia", "_ekia", "_ewia"];
                    _rows pushBack format ["<t size='1.35'><t font='RobotoCondensedBold'>%1</t><t align='right'><t color='@amber'>%2</t> · <t color='@red'>%3</t>          <t color='@accent'>%4</t> · %5</t></t>", [[_el, 60] call _cut] call _esc, _wia, _kia, _ekia, _ewia];
                } forEach ((_room select 8) select [0, 22]);
                if ((count (_room select 8)) isEqualTo 0) then { _rows pushBack "<t size='1.2' color='@muted'>Rien de rapporté : seuls comptent les pertes amies connues du réseau ATAK et les ennemis déclarés par compte rendu</t>"; };
                [(_rows select [0, 22]) joinString "<br/>", ""]
            };
            case "REQ": {
                private _l = +(_room select 5);
                reverse _l;
                private _rows = (_l select [0, 16]) apply {
                    _x params ["", "_t", "_k", "_by", "", "_grid", "_txt", "_stt", "_ans"];
                    private _c = (createHashMapFromArray [["RECUE", "amber"], ["PRISE", "blue"], ["REFUSEE", "red"], ["TERMINEE", "accent"]]) getOrDefault [_stt, "text"];
                    private _lbl = (createHashMapFromArray [["RECUE", "REÇUE"], ["PRISE", "PRISE EN COMPTE"], ["REFUSEE", "REFUSÉE"], ["TERMINEE", "TERMINÉE"]]) getOrDefault [_stt, _stt];
                    format ["<t size='1.15'><t color='@muted'>%1Z</t> <t font='RobotoCondensedBold'>%2</t> %3%4<t align='right' color='@%5' font='RobotoCondensedBold'>%6</t></t><br/><t size='1' color='@muted'>   %7%8</t>",
                        _t, [[_k, 12] call _cut] call _esc, [[_by, 14] call _cut] call _esc, ["", " · grille " + _grid] select (_grid isNotEqualTo ""), _c, _lbl,
                        [[_txt, 44] call _cut] call _esc, ["", " · réponse : " + ([[_ans, 30] call _cut] call _esc)] select (_ans isNotEqualTo "")]
                };
                if ((count _rows) isEqualTo 0) then { _rows = ["<t size='1.1' color='@muted'>Aucune demande reçue des éléments</t>"]; };
                [(_rows select [0, 8]) joinString "<br/>", (_rows select [8, 8]) joinString "<br/>"]
            };
            default { ["", ""] };
        };
        // Couleurs du thème : le texte reste traduisible (fn_tr ne touche pas aux attributs color='@…').
        _out apply { _x call _ink }
    };
    default { false };
};
