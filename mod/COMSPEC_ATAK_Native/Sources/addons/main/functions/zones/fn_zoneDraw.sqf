/*
    Plans de zones sur une carte (événement Draw) : carte de l'app Carte (fn_mapOnDraw, si « Zones sur la carte ATAK »
    est allumé) et carte de l'app Zones (fn_zoneMap). Rien n'est recalculé à l'image : boîtes, surfaces et triangles sont
    gardés par révision (fn_zoneAction "geo" / "tri") ; seules les zones dans la vue sont dessinées.
      - rendu SURLIGNÉ : remplissage léger (couleur de l'état, sinon du type) et contour à la couleur du type ;
        CONTOUR : contour seul (couleur de l'état, sinon du type) ; POINT CENTRÉ : point et nom seulement ;
        une zone qui ferait moins de 6 pixels passe en point ;
      - style tactique (fn_mapColors « tac ») : rectangles hachurés (brosse du moteur), contour d'au moins 2 px ; les polygones
        gardent leur remplissage léger ;
      - nom de la zone et état abrégé quand la zone est assez grande à l'écran (ou en point), nombre de signalements ;
      - nom du plan au-dessus de sa boîte, s'il a des zones (carte de l'app : toujours) : jamais d'étiquette seule, un plan
        sans zones n'est qu'un conteneur d'axes ou de traits, qui portent leur nom ;
      - axes MSR / ASR : bande claire et trait (surligné), trait seul (contour) ou nom seul (point) ; nom sur le trait, plaque
        sombre, texte blanc, liseré à la couleur de l'axe ; points de contrôle ;
      - lignes de phase et traits : nom à chaque bout (PL, LD, LOA…), comme sur un calque MIL-STD-2525 / APP-6 ;
      - hachures (style du plan) : brosse du moteur sur les cases ; cercle autour du point d'étiquette ;
      - bâtiments numérotés : zone choisie ou zoom rapproché ;
      - carte de l'app : zone choisie cerclée de blanc, zone de travail en tirets ;
      - couleurs et style (2.9.38) : thème du plan (OTAN, contraste élevé, vision nocturne) et couleurs choisies par type
        et par état (fn_zoneAction "pal", gardées par révision), style propre d'une zone (couleur, opacité, épaisseur,
        trait), étiquette (taille, fond, position, nom / numéro / état) ; contours et traits tracés du plan (lines) ;
        épaisseur 1 px et trait plein : drawPolygon / drawRectangle (le moins coûteux), sinon fn_planGfx.
    Params : [carte, échelle des symboles, échelle des textes, plan modifié (id, "" : aucun), zone choisie (id),
              trait choisi (id)]
*/
params ["_map", ["_sc", 1], ["_ts", 1], ["_edit", ""], ["_sel", ""], ["_lsel", ""]];
if (isNil {missionNamespace getVariable "COMSPEC_ATAK_ZnMine"}) then { ["mine"] call comspec_atak_native_fnc_zoneAction; };
private _mine = missionNamespace getVariable ["COMSPEC_ATAK_ZnMine", createHashMap];
private _rx = missionNamespace getVariable ["COMSPEC_ATAK_ZnRx", createHashMap];
if (((count _mine) + (count _rx)) isEqualTo 0) exitWith {};
private _list = ((values _mine) + (values _rx)) select { (_x get "vis") || {(_x get "id") isEqualTo _edit} };
if ((count _list) isEqualTo 0) exitWith {};
(ctrlPosition _map) params ["_cx", "_cy", "_cw", "_ch"];
private _tl = _map ctrlMapScreenToWorld [_cx, _cy];
private _br = _map ctrlMapScreenToWorld [_cx + _cw, _cy + _ch];
private _mpx = ["mpx", _map] call comspec_atak_native_fnc_planGfx;
if (_mpx <= 0) exitWith {};
private _mg = 40 * _mpx;
private _vx0 = ((_tl select 0) min (_br select 0)) - _mg; private _vx1 = ((_tl select 0) max (_br select 0)) + _mg;
private _vy0 = ((_tl select 1) min (_br select 1)) - _mg; private _vy1 = ((_tl select 1) max (_br select 1)) + _mg;
private _font = COMSPEC_ATAK_FontSet select 1;
private _clear = "#(argb,8,8,3)color(0,0,0,0)";
private _tex = "#(rgb,8,8,3)color(1,1,1,1)";
private _dot = "\A3\ui_f\data\map\markers\military\dot_CA.paa";
private _ring = "\A3\ui_f\data\map\markers\military\circle_CA.paa";
private _tri = "\A3\ui_f\data\map\markers\military\triangle_CA.paa";
private _fr = (missionNamespace getVariable ["COMSPEC_ATAK_Lang", "en"]) isEqualTo "fr";
// Abrégés d'état : table fixe, faite une fois par langue (les couleurs viennent du plan, fn_zoneAction "pal").
private _tab = uiNamespace getVariable ["COMSPEC_ATAK_ZnTab", []];
if ((_tab param [0, ""]) isNotEqualTo (["en", "fr"] select _fr)) then {
    private _s = createHashMap;
    { _s set [_x select 0, _x select ([3, 2] select _fr)]; } forEach (["states"] call comspec_atak_native_fnc_zoneAction);
    _tab = [["en", "fr"] select _fr, _s];
    uiNamespace setVariable ["COMSPEC_ATAK_ZnTab", _tab];
};
_tab params ["", "_stAb"];
private _scale = ctrlMapScale _map;
private _tsz = 0.024 * _ts;
private _palM = [] call comspec_atak_native_fnc_mapColors;
private _zTac = _palM get "tac";
private _rectTex = [_tex, _palM get "hatch"] select _zTac;
// Couleur d'une classe CfgMarkerColors (palette de l'app Routes), gardée.
private _ccache = uiNamespace getVariable "COMSPEC_ATAK_ZnCol";
if (isNil "_ccache") then { _ccache = createHashMap; uiNamespace setVariable ["COMSPEC_ATAK_ZnCol", _ccache]; };
private _rgb = { params ["_k"]; private _v = _ccache getOrDefault [_k, []]; if ((count _v) isEqualTo 0) then { _v = ["rgba", _k] call comspec_atak_native_fnc_routeAction; _ccache set [_k, _v]; }; +_v };
// Contour d'une forme : 1 px et plein, commande directe ; sinon trait épais, tirets ou pointillé (fn_planGfx).
private _outline = {
    params ["_pts3", "_col", "_w", "_dash", ["_rect", []]];
    if (_w <= 1 && {_dash in ["SOLID", "", "ARROW"]}) exitWith {
        if ((count _rect) isEqualTo 4) then { _map drawRectangle [[_rect select 0, _rect select 1, 0], _rect select 2, _rect select 3, 0, _col, ""]; } else { _map drawPolygon [_pts3, _col]; };
    };
    ["poly", _map, [_pts3, _col, [_w, 1] select (_dash isEqualTo "DOT"), _dash in ["DASH", "DOT"], 0, _mpx]] call comspec_atak_native_fnc_planGfx;
};
{
    private _b = _x;
    private _id = _b get "id";
    private _g = ["geo", _b] call comspec_atak_native_fnc_zoneAction;
    (_g select 1) params ["_X0", "_Y0", "_X1", "_Y1"];
    if (_X1 < _vx0 || {_X0 > _vx1} || {_Y1 < _vy0} || {_Y0 > _vy1}) then { continue };
    private _isEdit = _id isEqualTo _edit;
    private _a = [[0.85, 1] select (_id in _mine), 1] select _isEdit;
    private _render = _b get "render";
    private _zbb = _g select 2;
    private _p3 = _g select 4;
    private _pal = ["pal", _b] call comspec_atak_native_fnc_zoneAction;
    private _tyTab = _pal get "type";
    private _stTab = _pal get "state";
    private _fA = _pal get "fillA";
    private _wDef = _pal get "width";
    private _dDef = _pal get "dash";
    private _lMode = _pal get "lblMode";
    private _lSz = _tsz * (_pal get "lblSize");
    private _lBg = _pal get "lblBg";
    private _lN = (_pal get "lblPos") isEqualTo "N";
    private _texR = [_rectTex, _palM get "hatch"] select (_pal get "hatch");
    private _hatched = _zTac || {_pal get "hatch"};
    private _ringOn = _pal get "ring";
    private _tris = createHashMap;
    if (_render isEqualTo "FILL" || {((_b get "zones") findIf { (_x select 5) isEqualTo "FILL" }) >= 0}) then { _tris = ["tri", _b] call comspec_atak_native_fnc_zoneAction; };
    // Zone de travail (carte de l'app) : tirets blancs.
    if (_isEdit && {(count (_g select 5)) >= 3}) then {
        ["poly", _map, [_g select 5, [1, 1, 1, 0.7], 2, true, 0, _mpx]] call comspec_atak_native_fnc_planGfx;
    };
    private _showBld = _scale < 0.012;
    {
        _x params ["_zid", "_name", "_pts", "_lp", "_ty", "_rm", "_st", "", "", "_reps", "_bld", ["_rect", []], ["_zs", []]];
        (_zbb select _forEachIndex) params ["_x0", "_y0", "_x1", "_y1"];
        if (_x1 < _vx0 || {_x0 > _vx1} || {_y1 < _vy0} || {_y0 > _vy1}) then { continue };
        if (_rm isEqualTo "") then { _rm = _render; };
        private _sizePx = (((_x1 - _x0) max (_y1 - _y0))) / _mpx;
        if (_sizePx < 6) then { _rm = "POINT"; };
        _zs params [["_zc", ""], ["_zfa", -1], ["_zw", -1], ["_zd", ""]];
        private _tc = if (_zc isNotEqualTo "") then { [_zc] call _rgb } else { +(_tyTab getOrDefault [_ty, [1, 1, 1, 1]]) };
        // « Repère » du thème OTAN (presque noir) illisible sur fond sombre : clair sur satellite, ardoise, nuit.
        if (_ty isEqualTo "GEN" && {_zc isEqualTo ""} && {_palM get "dark"} && {((_tc select 0) + (_tc select 1) + (_tc select 2)) < 0.9}) then { _tc = [0.92, 0.93, 0.92, 1]; };
        private _scol = _stTab getOrDefault [_st, [0.62, 0.66, 0.64, 1]];
        private _sab = _stAb getOrDefault [_st, ""];
        private _hasSt = _st isNotEqualTo "UNREC";
        private _isSel = _isEdit && {_zid isEqualTo _sel};
        private _lc = +([_tc, _scol] select _hasSt);
        private _w = [_wDef, _zw] select (_zw > 0);
        if (_zTac) then { _w = _w max 2; };
        private _dash = [_dDef, _zd] select (_zd isNotEqualTo "");
        _tc set [3, 0.9 * _a];
        _lc set [3, _a];
        private _c3 = [_lp select 0, _lp select 1, 0];
        switch (_rm) do {
            case "FILL": {
                private _fc = +([_tc, _scol] select _hasSt);
                _fc set [3, ([_fA, _zfa] select (_zfa >= 0)) * ([1, 1.8] select _hasSt) min 0.85];
                if ((count _rect) isEqualTo 4) then {
                    if (_hatched) then { _fc set [3, 0.8]; };
                    _map drawRectangle [[_rect select 0, _rect select 1, 0], _rect select 2, _rect select 3, 0, _fc, _texR];
                } else {
                    private _t = _tris getOrDefault [_zid, []];
                    if ((count _t) >= 3) then { _map drawTriangle [_t, _fc, _tex]; };
                };
                [_p3 select _forEachIndex, _tc, _w, _dash, _rect] call _outline;
            };
            case "LINE": {
                [_p3 select _forEachIndex, _lc, [_w, _w max 2] select _hasSt, _dash, _rect] call _outline;
            };
            default {
                _map drawIcon [_dot, [0, 0, 0, 0.55 * _a], _c3, 15 * _sc, 15 * _sc, 0, "", 0];
                _map drawIcon [_dot, _lc, _c3, 11 * _sc, 11 * _sc, 0, "", 0];
            };
        };
        if (_isSel) then {
            ["poly", _map, [_p3 select _forEachIndex, [1, 1, 1, 0.95], 3, false, 0, _mpx]] call comspec_atak_native_fnc_planGfx;
        };
        if (_ringOn && {_sizePx > 34}) then { _map drawIcon ["\z\comspec_atak_native\addons\main\data\map_keyring.paa", _lc, _c3, 44 * _sc, 44 * _sc, 0, "", 0]; };
        // Étiquette : assez de place à l'écran, ou point centré ; contenu, taille, fond et place selon le style du plan.
        if (_lMode isNotEqualTo "NONE" && {_rm isEqualTo "POINT" || {_sizePx > 34} || {_isSel}}) then {
            private _t = if (_lMode isEqualTo "NUM") then { str (_forEachIndex + 1) } else { _name };
            if (_hasSt && {_lMode isEqualTo "NAME_ST"}) then { _t = format ["%1 · %2", _t, _sab]; };
            if ((count _reps) > 0 && {_lMode isEqualTo "NAME_ST"}) then { _t = format ["%1 (%2)", _t, count _reps]; };
            private _lpos = if (_lN && {_rm isNotEqualTo "POINT"}) then { [(_x0 + _x1) / 2, _y1 + 6 * _mpx, 0] } else { _c3 };
            if (_lBg && {_rm isNotEqualTo "POINT"}) then {
                ["label", _map, [_lpos, _t, [1, 1, 1, _a], _lSz * ([1, 1.15] select (_sizePx > 120)), _mpx, [0, 0], true]] call comspec_atak_native_fnc_planGfx;
            } else {
                if (_rm isEqualTo "POINT") then {
                    _map drawIcon [_clear, [1, 1, 1, _a], _c3, 0, 0, 0, " " + _t, 2, _lSz, _font, "right"];
                } else {
                    _map drawIcon [_clear, [1, 1, 1, _a], _lpos, 0, 0, 0, _t, 2, _lSz * ([1, 1.15] select (_sizePx > 120)), _font, "center"];
                };
            };
        };
        // Bâtiments numérotés.
        if ((count _bld) > 0 && {_showBld || {_isSel}}) then {
            {
                _x params ["_bx", "_by", "_ba", "_bb", "_bd", ["_fl", 1]];
                private _bp = [_bx, _by, 0];
                _map drawRectangle [_bp, _ba, _bb, _bd, [0.95, 0.67, 0.20, 0.85 * _a], ""];
                _map drawIcon [_clear, [1, 1, 1, _a], _bp, 0, 0, 0, [str (_forEachIndex + 1), format ["%1·%2", _forEachIndex + 1, _fl]] select (_fl > 1 && {_scale < 0.005}), 2, _tsz * 0.85, _font, "center"];
            } forEach _bld;
        };
    } forEach (_b get "zones");
    // Axes et points de contrôle.
    {
        _x params ["", "_name", "_cls", "_pts", "", ["_rm", ""]];
        if (_rm isEqualTo "") then { _rm = _render; };
        private _col = [[0.35, 0.85, 0.40, _a], [0.30, 0.58, 1, _a]] select (_cls isEqualTo "MSR");
        private _p3a = _pts apply { [_x select 0, _x select 1, 0] };
        if (_rm isNotEqualTo "POINT") then {
            if (_rm isEqualTo "FILL") then { ["line", _map, [_p3a, [_col select 0, _col select 1, _col select 2, 0.28 * _a], 11, false, false, false, _mpx]] call comspec_atak_native_fnc_planGfx; };
            ["line", _map, [_p3a, _col, [3, 4] select (_cls isEqualTo "MSR"), false, false, false, _mpx]] call comspec_atak_native_fnc_planGfx;
        };
        private _mid = _p3a select (floor ((count _p3a) / 2));
        ["label", _map, [_mid, _name, [1, 1, 1, _a], _tsz, _mpx, [0, 0], true, _col]] call comspec_atak_native_fnc_planGfx;
    } forEach (_b get "axes");
    {
        _x params ["", "_lbl", "_p"];
        private _q = [_p select 0, _p select 1, 0];
        _map drawIcon [_tri, [0, 0, 0, 0.6 * _a], _q, 19 * _sc, 19 * _sc, 0, "", 0];
        _map drawIcon [_tri, [1, 0.82, 0.2, _a], _q, 15 * _sc, 15 * _sc, 0, " " + _lbl, 2, _tsz, _font, "right"];
    } forEach (_b get "cps");
    // Contours et traits tracés : couleur et style propres, sinon blanc (carte de l'app) / type du plan ; choisi : sommets.
    {
        _x params ["_lid", "_ln", "_lpts", "_closed", ["_ls", []]];
        _ls params [["_lc0", ""], ["_lw", -1], ["_ld", ""]];
        private _col = if (_lc0 isNotEqualTo "") then { [_lc0] call _rgb } else { +(_tyTab getOrDefault [_b get "aff", [1, 1, 1, 1]]) };
        _col set [3, _a];
        private _q = _lpts apply { [_x select 0, _x select 1, 0] };
        private _wl = [[_wDef max 2, 2] select (_wDef < 2), _lw] select (_lw > 0);
        private _dl = [_dDef, _ld] select (_ld isNotEqualTo "");
        if (_closed) then {
            ["poly", _map, [_q, _col, _wl, _dl in ["DASH", "DOT"], 0, _mpx]] call comspec_atak_native_fnc_planGfx;
        } else {
            ["line", _map, [_q, _col, _wl, _dl in ["DASH", "DOT"], _dl isEqualTo "ARROW", true, _mpx]] call comspec_atak_native_fnc_planGfx;
        };
        private _isL = _isEdit && {_lid isEqualTo _lsel};
        if (_isL) then {
            { _map drawIcon [_dot, [1, 1, 1, 1], _x, 12 * _sc, 12 * _sc, 0, "", 0]; _map drawIcon [_dot, _col, _x, 8 * _sc, 8 * _sc, 0, "", 0]; } forEach _q;
            // Milieux des segments : un clic y ajoute un sommet.
            private _n = count _q;
            for "_i" from 0 to (_n - ([2, 1] select _closed)) do {
                private _m = ((_q select _i) vectorAdd (_q select ((_i + 1) mod _n))) vectorMultiply 0.5;
                _map drawIcon [_ring, [1, 1, 1, 0.7], _m, 9 * _sc, 9 * _sc, 0, "", 0];
            };
        };
        // Nom du trait dès le zoom moyen ; ligne de contrôle ouverte (PL, LD, LOA…) : à chaque bout.
        if (_isL || {_isEdit} || {_scale < 0.12}) then {
            if (!_closed && {(count _q) >= 2} && {((_ln splitString " ") param [0, ""]) in ["PL", "LD", "LOA", "FSCL", "CFL"]}) then {
                { ["label", _map, [_x, _ln, [1, 1, 1, _a], _tsz * 0.9, _mpx, [0, 0], true, _col]] call comspec_atak_native_fnc_planGfx; } forEach [_q select 0, _q select -1];
            } else {
                ["label", _map, [_q select (floor ((count _q) / 2)), _ln, [1, 1, 1, _a], _tsz * 0.9, _mpx, [0, 0], true, _col]] call comspec_atak_native_fnc_planGfx;
            };
        };
    } forEach (_b getOrDefault ["lines", []]);
    // Nom du plan au-dessus de sa boîte.
    if (((_X1 - _X0) / _mpx) > 40 && {_isEdit || {(count (_b get "zones")) > 0}}) then {
        ["label", _map, [[(_X0 + _X1) / 2, _Y1, 0], _b get "name", [1, 1, 1, 0.95 * _a], _tsz * 1.05, _mpx, [0, -14], true]] call comspec_atak_native_fnc_planGfx;
    };
} forEach _list;
