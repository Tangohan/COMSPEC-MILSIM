/*
    App Relief : ce que je vois depuis ici, et ce qui me voit. Même famille que l'app Reco (en-tête sombre, pastilles,
    tuiles à icône, cartes), en turquoise. Quatre outils, choisis par les tuiles du haut :
      - CHAMP DE VISION : grille autour de moi, vue ou cachée (relief seul, ou relief + bâtiments et arbres),
        part visible, vues par secteur (barres), point haut ;
      - HEATMAP : la même grille en couleurs d'altitude, absolue ou par rapport à moi (ce qui me domine), légende ;
      - LIGNE DE VUE : de mes yeux vers un carroyage, le point visé ou un point touché sur la carte ;
        verdict, nature de l'obstacle, profil du terrain ;
      - BÂTIMENTS VUS : bâtiments à portée de vue dans un rayon, liste des plus proches.
    Calculs : fn_reliefAction. Calques sur l'app Carte : fn_reliefDraw (et la ligne de vue des outils de la carte).
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _font = _l get "font";
private _fs = _l get "fontSmall";
private _pad = (_l get "pad") * 2;
private _ratio = (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]);
private _dir = "\z\comspec_atak_native\addons\main\data\";
private _form = uiNamespace getVariable ["COMSPEC_ATAK_Form", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_Form", _form];
private _tool = _s getOrDefault ["rlTool", ["VIS", "ALT"] select ((_s getOrDefault ["rlMode", "VIS"]) isEqualTo "ALT")];
private _show = missionNamespace getVariable ["COMSPEC_ATAK_ViewshedShow", true];

private _cBg = [0.026, 0.031, 0.028, 1];
private _cHdr = [0.012, 0.015, 0.013, 1];
private _cTile = [0.065, 0.078, 0.071, 1];
private _cField = [0.014, 0.017, 0.015, 1];
private _cLine = [0.16, 0.20, 0.17, 1];
private _cAcc = [0.26, 0.80, 0.76, 1];
private _cAccDim = [0.06, 0.22, 0.21, 1];
private _cText = [0.92, 0.95, 0.93, 1];
private _cDim = [0.55, 0.61, 0.58, 1];
private _cInk = [0.02, 0.06, 0.06, 1];
private _cGreen = [0.36, 0.78, 0.42, 1];
private _cRed = [0.90, 0.28, 0.22, 1];
private _cAmber = [0.95, 0.67, 0.20, 1];

private _mk = { params ["_c", "_p", ["_t", ""]]; [_c, _p, _t] call comspec_atak_native_fnc_pageCtrl };
private _box = { params ["_rect", "_rgba"]; private _c = ["COMSPEC_RscText", _rect] call _mk; _c ctrlSetBackgroundColor _rgba; _c };
private _lbl = {
    params ["_cls", "_rect", "_t", ["_fh", _fs], ["_col", _cText], ["_bold", false]];
    private _c = [_cls, _rect, _t] call _mk;
    _c ctrlSetFontHeight _fh;
    _c ctrlSetTextColor _col;
    if (_bold) then { _c ctrlSetFont "RobotoCondensedBold"; };
    _c
};
private _hit = {
    params ["_rect", "_code", ["_tip", ""]];
    private _b = ["COMSPEC_RscButtonInvisible", _rect] call _mk;
    _b ctrlAddEventHandler ["ButtonClick", _code];
    if (_tip isNotEqualTo "") then { _b ctrlSetTooltip ((_tip) call comspec_atak_native_fnc_tr); };
    _b
};
private _html = { params ["_rgba"]; (_rgba select [0, 3]) call BIS_fnc_colorRGBtoHTML };
private _sec = {
    params ["_x0", "_y0", "_w", "_t", ["_right", ""], ["_rightCol", _cDim]];
    ["COMSPEC_RscText", [_x0, _y0, _w * 0.6, _fs * 1.3], _t, _fs * 0.8, _cDim, true] call _lbl;
    if (_right isNotEqualTo "") then { ["COMSPEC_RscTextRight", [_x0 + _w * 0.2, _y0, _w * 0.8, _fs * 1.3], _right, _fs * 0.8, _rightCol, true] call _lbl; };
    [[_x0, _y0 + _fs * 1.3, _w, pixelH], _cLine] call _box;
    _y0 + _fs * 1.3 + _pad * 0.6
};
private _fit = {
    params ["_c", "_w", "_fh", ["_min", 0.6]];
    private _f = _fh;
    while { (ctrlTextWidth _c) > _w && {_f > _fh * _min} } do { _f = _f * 0.92; _c ctrlSetFontHeight _f; };
};
// Bouton plein : [rect, texte, code, accent ?, aide].
private _btn = {
    params ["_rect", "_t", "_code", ["_main", false], ["_tip", ""]];
    _rect params ["_bx", "_by", "_bww", "_bhh"];
    [_rect, [_cTile, _cAcc] select _main] call _box;
    private _c = ["COMSPEC_RscTextCenter", _rect, _t, _fs * 0.95, [_cText, _cInk] select _main, true] call _lbl;
    [_c, _bww * 0.92, _fs * 0.95] call _fit;
    [_rect, _code, _tip] call _hit;
};
// Choix en ligne : libellé à gauche, options à droite ; renvoie le y suivant.
private _seg = {
    params ["_x0", "_y0", "_w", "_label", "_key", "_opts", "_cur"];
    private _rh = _font * 1.45;
    private _lw = _w * 0.28;
    ["COMSPEC_RscText", [_x0, _y0, _lw, _rh], _label, _fs * 0.9, _cText, true] call _lbl;
    private _ow = (_w - _lw - _pad * 0.3 * ((count _opts) - 1)) / (count _opts);
    {
        _x params ["_t", "_v"];
        private _on = _v isEqualTo _cur;
        private _ox = _x0 + _lw + _forEachIndex * (_ow + _pad * 0.3);
        [[_ox, _y0, _ow, _rh], [_cTile, _cAccDim] select _on] call _box;
        [[_ox, _y0 + _rh - pixelH * 2, _ow, pixelH * 2], [[1, 1, 1, 0.05], _cAcc] select _on] call _box;
        private _c = ["COMSPEC_RscTextCenter", [_ox, _y0, _ow, _rh], _t, _fs * 0.85, [_cDim, _cText] select _on, _on] call _lbl;
        [_c, _ow * 0.92, _fs * 0.85] call _fit;
        [[_ox, _y0, _ow, _rh], compile format ["['set', '%1', %2] call comspec_atak_native_fnc_reliefAction;", _key, str _v]] call _hit;
    } forEach _opts;
    _y0 + _rh + _pad * 0.5
};
// Tuiles de chiffres : [[libellé, valeur, couleur]...] sur une rangée ; renvoie le y suivant.
private _stats = {
    params ["_x0", "_y0", "_w", "_items"];
    private _n = count _items;
    private _sw = (_w - _pad * 0.4 * (_n - 1)) / _n;
    private _sh = _font * 2.3;
    {
        _x params ["_t", "_v", ["_col", _cText]];
        private _sx = _x0 + _forEachIndex * (_sw + _pad * 0.4);
        [[_sx, _y0, _sw, _sh], _cTile] call _box;
        private _c = ["COMSPEC_RscTextCenter", [_sx, _y0 + _sh * 0.06, _sw, _sh * 0.56], _v, _font * 1.05, _col, true] call _lbl;
        [_c, _sw * 0.9, _font * 1.05] call _fit;
        private _c2 = ["COMSPEC_RscTextCenter", [_sx, _y0 + _sh * 0.6, _sw, _sh * 0.34], _t, _fs * 0.72, _cDim] call _lbl;
        [_c2, _sw * 0.92, _fs * 0.72] call _fit;
    } forEach _items;
    _y0 + _sh + _pad * 0.6
};
private _dist = { params ["_d"]; [format ["%1 m", round _d], format ["%1 km", (_d / 1000) toFixed 1]] select (_d >= 1000) };
private _card = { params ["_deg"]; ["N", "NE", "E", "SE", "S", "SO", "O", "NO"] select ((round (_deg / 45)) mod 8) };
private _pctCol = { params ["_p"]; [_cRed, _cAmber, _cGreen] select ((floor (_p / 34)) min 2) };
// Barre d'avancement d'un calcul en cours.
private _progress = {
    params ["_x0", "_y0", "_w", "_t", "_p"];
    ["COMSPEC_RscText", [_x0, _y0, _w, _fs * 1.3], format ["%1 · %2 %%", _t, round (100 * _p)], _fs * 0.85, _cAmber, true] call _lbl;
    [[_x0, _y0 + _fs * 1.4, _w, _fs * 0.5], _cTile] call _box;
    [[_x0, _y0 + _fs * 1.4, (_w * _p) max pixelW, _fs * 0.5], _cAmber] call _box;
    private _a = _font * 1.5;
    [[_x0, _y0 + _fs * 2.3, _w, _a], "ACTUALISER", { [{ ['RELIEF'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }] call _btn;
    _y0 + _fs * 2.3 + _a + _pad * 0.6
};

private _bg = [[0, 0, _bw, _bh], _cBg] call _box;
private _w = _bw - 2 * _pad;

// ---------- En-tête ----------
private _hdrH = _font * 2.7;
[[0, 0, _bw, _hdrH], _cHdr] call _box;
[[0, 0, pixelW * 4, _hdrH], _cAcc] call _box;
[[0, _hdrH - pixelH * 2, _bw, pixelH * 2], [_cAcc select 0, _cAcc select 1, _cAcc select 2, 0.7]] call _box;
["COMSPEC_RscText", [_pad, _hdrH * 0.08, _bw * 0.5, _font * 1.3], "RELIEF", _font * 1.2, _cAcc, true] call _lbl;
["COMSPEC_RscText", [_pad, _hdrH * 0.08 + _font * 1.25, _bw * 0.5, _fs * 1.2], format ["CE QUE JE VOIS, CE QUI ME VOIT · %1", [dayTime, "HH:MM"] call BIS_fnc_timeToString], _fs * 0.8, _cDim] call _lbl;
["COMSPEC_RscTextRight", [_bw * 0.4, _hdrH * 0.08, _bw * 0.6 - _pad, _font * 1.3], format ["ALT %1 m · %2", round ((getPosASL player) select 2), [getPosASL player] call comspec_atak_native_fnc_gridRef], _fs, _cText, true] call _lbl;
// Pastille cliquable : calques Relief sur la carte.
private _chip = ["COMSPEC_RscTextCenter", [0, _hdrH * 0.08 + _font * 1.3, 0.1, _fs * 1.15], ["CALQUE CARTE : MASQUÉ", "CALQUE CARTE : AFFICHÉ"] select _show, _fs * 0.72, [_cDim, _cInk] select _show, true] call _lbl;
private _cw = (ctrlTextWidth _chip) + _pad;
private _chipRect = [_bw - _pad - _cw, _hdrH * 0.08 + _font * 1.3, _cw, _fs * 1.15];
_chip ctrlSetPosition _chipRect;
_chip ctrlSetBackgroundColor ([[0.13, 0.16, 0.14, 1], _cAcc] select _show);
_chip ctrlCommit 0;
[_chipRect, { ["show"] call comspec_atak_native_fnc_reliefAction; }, "Afficher ou masquer les calques Relief sur la carte"] call _hit;

// ---------- Outils ----------
private _y = _hdrH + _pad;
private _tools = [
    ["VIS", "CHAMP DE VISION", "vu / caché autour de moi", "reco_eye"],
    ["ALT", "HEATMAP", "altitudes en couleurs", "relief_heat"],
    ["LOS", "LIGNE DE VUE", "vers un point précis", "relief_los"],
    ["BLD", "BÂTIMENTS VUS", "à portée de vue", "relief_bld"]
];
private _gap = _pad * 0.5;
private _tw = (_w - 3 * _gap) / 4;
private _th = (_font * 3.2) min ((_tw * _ratio * 0.62) max (_font * 2.5));
{
    _x params ["_k", "_t", "_sub", "_icon"];
    private _on = _k isEqualTo _tool;
    private _tx = _pad + _forEachIndex * (_tw + _gap);
    [[_tx, _y, _tw, _th], [_cTile, _cAccDim] select _on] call _box;
    [[_tx, _y, _tw, pixelH * ([1, 3] select _on)], [[1, 1, 1, 0.07], _cAcc] select _on] call _box;
    private _ih = _th * 0.36;
    private _ic = ["COMSPEC_RscPicture", [_tx + (_tw - _ih / _ratio) / 2, _y + _th * 0.1, _ih / _ratio, _ih], _dir + _icon + ".paa"] call _mk;
    _ic ctrlSetTextColor ([[0.70, 0.76, 0.73, 1], _cAcc] select _on);
    private _c = ["COMSPEC_RscTextCenter", [_tx, _y + _th * 0.5, _tw, _th * 0.26], _t, _fs * 0.85, [_cDim, _cText] select _on, true] call _lbl;
    [_c, _tw * 0.94, _fs * 0.85, 0.55] call _fit;
    private _c2 = ["COMSPEC_RscTextCenter", [_tx, _y + _th * 0.74, _tw, _th * 0.22], _sub, _fs * 0.68, _cDim] call _lbl;
    [_c2, _tw * 0.94, _fs * 0.68, 0.55] call _fit;
    [[_tx, _y, _tw, _th], compile format ["['set', 'rlTool', '%1'] call comspec_atak_native_fnc_reliefAction;", _k], _sub] call _hit;
} forEach _tools;
_y = _y + _th + _pad;

private _bh2 = _font * 1.7;
private _half = (_w - _gap) / 2;
private _vs = missionNamespace getVariable ["COMSPEC_ATAK_Viewshed", []];
private _busy = missionNamespace getVariable ["COMSPEC_ATAK_ViewshedBusy", false];
private _radius = _s getOrDefault ["rlRadius", 1000];

switch (_tool) do {
    // ---------- CHAMP DE VISION ----------
    case "VIS": {
        _y = [_pad, _y, _w, "Réglages", format ["grille de 41 x 41, case de %1 m, cible à 1,7 m du sol", round ((2 * _radius) / 40)]] call _sec;
        _y = [_pad, _y, _w, "Rayon", "rlRadius", [["500 m", 500], ["1 km", 1000], ["2 km", 2000]], _radius] call _seg;
        _y = [_pad, _y, _w, "Obstacles", "rlObst", [["RELIEF SEUL", "TER"], ["+ BÂTIMENTS ET ARBRES", "OBJ"]], _s getOrDefault ["rlObst", "TER"]] call _seg;
        if (_busy) then {
            _y = [_pad, _y, _w, "Calcul en cours", missionNamespace getVariable ["COMSPEC_ATAK_ViewshedProgress", 0]] call _progress;
        } else {
            [[_pad, _y, _half, _bh2], "CALCULER", { ["compute"] call comspec_atak_native_fnc_reliefAction; }, true, "Quelques secondes selon le rayon (plus long avec les bâtiments)"] call _btn;
            [[_pad + _half + _gap, _y, _half, _bh2], "EFFACER", { ["clear"] call comspec_atak_native_fnc_reliefAction; }] call _btn;
            _y = _y + _bh2 + _pad;
        };
        if ((count _vs) > 8) then {
            _vs params ["_center", "_cell", "_cells", "_r", "_minH", "_maxH", "_pctVis", "_best", "_when"];
            private _obst = _vs param [9, false];
            _y = [_pad, _y, _w, "Résultat", format ["%1 · %2 · %3", [_when, "HH:MM"] call BIS_fnc_timeToString, [_r] call _dist, ["relief seul", "relief, bâtiments et arbres"] select _obst]] call _sec;
            _y = [_pad, _y, _w, [
                ["terrain vu", format ["%1 %%", _pctVis], [_pctVis] call _pctCol],
                ["terrain caché", format ["%1 %%", 100 - _pctVis]],
                ["dénivelé", format ["%1 m", round (_maxH - _minH)]],
                ["point haut", format ["%1 m", round _maxH], _cAmber]
            ]] call _stats;
            // Vues par secteur : barres horizontales, de N à NO.
            private _sect = [0, 0, 0, 0, 0, 0, 0, 0];
            private _sectN = [0, 0, 0, 0, 0, 0, 0, 0];
            {
                _x params ["_p", "_seen"];
                if ((_p distance2D _center) < _cell) then { continue };
                private _k = (round ((_center getDir _p) / 45)) mod 8;
                _sectN set [_k, (_sectN select _k) + 1];
                if (_seen) then { _sect set [_k, (_sect select _k) + 1]; };
            } forEach _cells;
            _y = [_pad, _y, _w, "Vues par secteur", "part du terrain vu dans chaque direction"] call _sec;
            private _colW = (_w - _gap) / 2;
            private _rowH = _fs * 1.35;
            {
                private _p = round (100 * (_sect select _forEachIndex) / ((_sectN select _forEachIndex) max 1));
                private _cx = _pad + (floor (_forEachIndex / 4)) * (_colW + _gap);
                private _cy = _y + (_forEachIndex mod 4) * (_rowH + pixelH * 3);
                private _lw = _colW * 0.22;
                ["COMSPEC_RscText", [_cx, _cy, _lw, _rowH], _x, _fs * 0.85, _cText, true] call _lbl;
                private _barW = _colW - _lw - _font * 2.4;
                [[_cx + _lw, _cy + _rowH * 0.2, _barW, _rowH * 0.6], _cTile] call _box;
                [[_cx + _lw, _cy + _rowH * 0.2, (_barW * _p / 100) max pixelW, _rowH * 0.6], [_p] call _pctCol] call _box;
                ["COMSPEC_RscTextRight", [_cx + _lw + _barW, _cy, _font * 2.4, _rowH], format ["%1 %%", _p], _fs * 0.85, [_p] call _pctCol, true] call _lbl;
            } forEach ["N", "NE", "E", "SE", "S", "SO", "O", "NO"];
            _y = _y + 4 * (_rowH + pixelH * 3) + _pad * 0.6;
            private _moved = player distance2D _center;
            if (_moved > _cell * 2) then {
                ["COMSPEC_RscText", [_pad, _y, _w, _fs * 1.3], format ["Vous avez bougé de %1 depuis le calcul : relancez-le pour une vue à jour.", [_moved] call _dist], _fs * 0.8, _cAmber] call _lbl;
                _y = _y + _fs * 1.3 + _pad * 0.4;
            };
            [[_pad, _y, _half, _bh2], "VOIR SUR LA CARTE", { ["locate", "center"] call comspec_atak_native_fnc_reliefAction; }] call _btn;
            if (_best >= 0) then { [[_pad + _half + _gap, _y, _half, _bh2], "POINT HAUT SUR LA CARTE", { ["locate"] call comspec_atak_native_fnc_reliefAction; }] call _btn; };
            _y = _y + _bh2 + _pad;
        } else {
            if !(_busy) then {
                ["COMSPEC_RscText", [_pad, _y, _w, _fs * 1.4], "Aucun calcul. Touchez CALCULER : vert vu, rouge caché sur la carte.", _fs * 0.85, _cDim] call _lbl;
                _y = _y + _fs * 1.4 + _pad;
            };
        };
    };
    // ---------- HEATMAP ----------
    case "ALT": {
        private _heat = _s getOrDefault ["rlHeat", "ABS"];
        _y = [_pad, _y, _w, "Réglages", "même grille que le champ de vision"] call _sec;
        _y = [_pad, _y, _w, "Rayon", "rlRadius", [["500 m", 500], ["1 km", 1000], ["2 km", 2000]], _radius] call _seg;
        _y = [_pad, _y, _w, "Couleurs", "rlHeat", [["ALTITUDE", "ABS"], ["PAR RAPPORT À MOI", "REL"]], _heat] call _seg;
        if (_busy) then {
            _y = [_pad, _y, _w, "Calcul en cours", missionNamespace getVariable ["COMSPEC_ATAK_ViewshedProgress", 0]] call _progress;
        } else {
            [[_pad, _y, _half, _bh2], "CALCULER", { ["compute"] call comspec_atak_native_fnc_reliefAction; }, true] call _btn;
            [[_pad + _half + _gap, _y, _half, _bh2], "EFFACER", { ["clear"] call comspec_atak_native_fnc_reliefAction; }] call _btn;
            _y = _y + _bh2 + _pad;
        };
        // Légende : dégradé et bornes.
        _y = [_pad, _y, _w, "Légende", ["bas → haut", "en dessous de moi → au-dessus de moi"] select (_heat isEqualTo "REL")] call _sec;
        private _ramp = [[[0.10, 0.18, 0.55, 1], [0.10, 0.65, 0.85, 1], [0.25, 0.80, 0.35, 1], [0.95, 0.85, 0.20, 1], [0.92, 0.22, 0.15, 1]],
            [[0.15, 0.35, 0.95, 1], [0.15, 0.50, 0.95, 1], [0.70, 0.75, 0.72, 1], [0.95, 0.55, 0.15, 1], [0.95, 0.20, 0.15, 1]]] select (_heat isEqualTo "REL");
        private _lh = _fs * 0.9;
        private _sw = _w / 5;
        { [[_pad + _forEachIndex * _sw, _y, _sw + pixelW, _lh], _x] call _box; } forEach _ramp;
        _y = _y + _lh + pixelH * 4;
        private _labs = if (_heat isEqualTo "REL") then { ["40 m plus bas", "à mon niveau", "40 m plus haut"] } else {
            if ((count _vs) > 8) then { [format ["%1 m", round (_vs select 4)], format ["%1 m", round (((_vs select 4) + (_vs select 5)) / 2)], format ["%1 m", round (_vs select 5)]] } else { ["bas", "milieu", "haut"] }
        };
        ["COMSPEC_RscText", [_pad, _y, _w / 3, _fs * 1.2], _labs select 0, _fs * 0.8, _cDim] call _lbl;
        ["COMSPEC_RscTextCenter", [_pad + _w / 3, _y, _w / 3, _fs * 1.2], _labs select 1, _fs * 0.8, _cDim] call _lbl;
        ["COMSPEC_RscTextRight", [_pad + 2 * _w / 3, _y, _w / 3, _fs * 1.2], _labs select 2, _fs * 0.8, _cDim] call _lbl;
        _y = _y + _fs * 1.2 + _pad * 0.6;
        if ((count _vs) > 8) then {
            _vs params ["_center", "_cell", "_cells", "_r", "_minH", "_maxH", "", "_best", "_when"];
            private _myH = _vs param [10, getTerrainHeightASL _center];
            private _above = { ((_x select 2) - _myH) > 10 } count _cells;
            _y = [_pad, _y, _w, "Résultat", format ["%1 · %2 de rayon", [_when, "HH:MM"] call BIS_fnc_timeToString, [_r] call _dist]] call _sec;
            _y = [_pad, _y, _w, [
                ["mini", format ["%1 m", round _minH]],
                ["maxi", format ["%1 m", round _maxH], _cAmber],
                ["dénivelé", format ["%1 m", round (_maxH - _minH)]],
                ["plus haut que moi", format ["%1 %%", round (100 * _above / ((count _cells) max 1))], [_cGreen, _cRed] select (_above > (count _cells) / 4)]
            ]] call _stats;
            if (_best >= 0) then {
                private _bp = (_cells select _best) select 0;
                private _ch = _fs * 2.9;
                [[_pad, _y, _w, _ch], _cTile] call _box;
                [[_pad, _y, pixelW * 4, _ch], _cAmber] call _box;
                private _c = ["COMSPEC_RscStructuredText", [_pad * 2, _y + _ch * 0.08, _w * 0.7, _ch * 0.9]] call _mk;
                _c ctrlSetStructuredText parseText ((format ["<t font='RobotoCondensedBold' color='#f2ab33'>POINT HAUT %1 m</t><br/><t size='0.85' color='#c9d2cd'>%2 · %3 %4 (%5°)</t>",
                    round (_bp select 2), [_bp, 8] call comspec_atak_native_fnc_gridRef, [player distance2D _bp] call _dist, [player getDir _bp] call _card, round (player getDir _bp)]) call comspec_atak_native_fnc_tr);
                _c ctrlSetFontHeight _fs;
                private _mw = (_font * 3.6) min (_w * 0.25);
                [[_pad + _w - _mw - _pad * 0.4, _y + _ch * 0.2, _mw, _ch * 0.6], "CARTE", { ["locate"] call comspec_atak_native_fnc_reliefAction; }] call _btn;
                _y = _y + _ch + _pad;
            };
        } else {
            if !(_busy) then {
                ["COMSPEC_RscText", [_pad, _y, _w, _fs * 1.4], "Aucun calcul. Touchez CALCULER : la carte se colore selon l'altitude.", _fs * 0.85, _cDim] call _lbl;
                _y = _y + _fs * 1.4 + _pad;
            };
        };
    };
    // ---------- LIGNE DE VUE ----------
    case "LOS": {
        _y = [_pad, _y, _w, "Cible", "depuis mes yeux, cible à 1,7 m du sol"] call _sec;
        private _rowH = _font * 1.6;
        private _ew = _w * 0.42;
        [[_pad, _y, _ew, _rowH], _cField] call _box;
        [[_pad, _y + _rowH - pixelH, _ew, pixelH], [_cAcc select 0, _cAcc select 1, _cAcc select 2, 0.6]] call _box;
        private _gE = ["COMSPEC_RscEdit", [_pad, _y, _ew, _rowH], _s getOrDefault ["rlGrid", ""]] call _mk;
        _gE ctrlSetBackgroundColor [0, 0, 0, 0];
        _gE ctrlSetFont "EtelkaMonospacePro";
        _gE ctrlSetFontHeight (_font * 0.95);
        _gE ctrlSetTooltip (("Carroyage de 6, 8 ou 10 chiffres, ex. 1518 1730") call comspec_atak_native_fnc_tr);
        _form set ["rlGrid", _gE];
        private _qb = (_w - _ew - 2 * _gap) / 2;
        {
            _x params ["_t", "_icon", "_code", "_tip"];
            private _bx = _pad + _ew + _gap + _forEachIndex * (_qb + _gap);
            [[_bx, _y, _qb, _rowH], _cTile] call _box;
            private _ih = _rowH * 0.55;
            private _ic = ["COMSPEC_RscPicture", [_bx + _pad * 0.5, _y + _rowH * 0.22, _ih / _ratio, _ih], _dir + _icon + ".paa"] call _mk;
            _ic ctrlSetTextColor _cAcc;
            private _c = ["COMSPEC_RscText", [_bx + _pad + _ih / _ratio, _y, _qb - _pad * 1.5 - _ih / _ratio, _rowH], _t, _fs * 0.85, _cText, true] call _lbl;
            [_c, _qb - _pad * 1.5 - _ih / _ratio, _fs * 0.85] call _fit;
            [[_bx, _y, _qb, _rowH], _code, _tip] call _hit;
        } forEach [
            ["SOUS MON REGARD", "reco_eye", { ["los", "look"] call comspec_atak_native_fnc_reliefAction; }, "Point visé (où je regarde)"],
            ["SUR LA CARTE", "app_map", { ["losMap"] call comspec_atak_native_fnc_reliefAction; }, "Touchez un point de la carte"]
        ];
        _y = _y + _rowH + _pad * 0.6;
        [[_pad, _y, _half, _bh2], "TRACER", { ["los", "grid"] call comspec_atak_native_fnc_reliefAction; }, true, "Ligne de vue vers le carroyage saisi"] call _btn;
        [[_pad + _half + _gap, _y, _half, _bh2], "EFFACER", { ["losClear"] call comspec_atak_native_fnc_reliefAction; }] call _btn;
        _y = _y + _bh2 + _pad;
        private _los = missionNamespace getVariable ["COMSPEC_ATAK_ReliefLos", []];
        if ((count _los) > 5) then {
            _los params ["_from", "_to", "_block", "_what", "_prof", "_when"];
            private _len = _from distance2D _to;
            private _ok = (count _block) isEqualTo 0;
            private _bd = [_from distance2D _block, _len] select _ok;
            // Verdict.
            private _vh = _font * 2.2;
            private _vc = [_cRed, _cGreen] select _ok;
            [[_pad, _y, _w, _vh], [[0.22, 0.05, 0.04, 1], [0.05, 0.18, 0.08, 1]] select _ok] call _box;
            [[_pad, _y, pixelW * 4, _vh], _vc] call _box;
            private _vt = ["COMSPEC_RscStructuredText", [_pad * 2, _y + _vh * 0.1, _w - _pad * 2, _vh * 0.9]] call _mk;
            _vt ctrlSetStructuredText parseText ((format ["<t font='RobotoCondensedBold' size='1.25' color='%1'>%2</t><br/><t size='0.85' color='#c9d2cd'>%3</t>", [_vc] call _html,
                ["VUE BLOQUÉE", "VUE DÉGAGÉE"] select _ok,
                [format ["Obstacle : %1, à %2 de moi (%3 avant la cible)", _what, [_bd] call _dist, [_len - _bd] call _dist], "Rien ne coupe la ligne entre mes yeux et la cible."] select _ok]) call comspec_atak_native_fnc_tr);
            _vt ctrlSetFontHeight _fs;
            _y = _y + _vh + _pad * 0.6;
            private _az = (_from getDir _to);
            _y = [_pad, _y, _w, [
                ["distance", [_len] call _dist],
                ["azimut", format ["%1° %2", round _az, [_az] call _card]],
                ["dénivelé", format ["%1%2 m", ["", "+"] select (((_to select 2) - (_from select 2)) >= 0), round ((_to select 2) - (_from select 2))]],
                ["cible", [ASLToAGL _to, 8] call comspec_atak_native_fnc_gridRef]
            ]] call _stats;
            // Profil : sol en barres, ligne de visée en points (vert avant l'obstacle, rouge après).
            _y = [_pad, _y, _w, "Profil", format ["calculé à %1", [_when, "HH:MM"] call BIS_fnc_timeToString]] call _sec;
            private _ph = _font * 4.2;
            [[_pad, _y, _w, _ph], _cField] call _box;
            private _zMin = 1e9;
            private _zMax = -1e9;
            { _zMin = _zMin min ((_x select 1) min (_x select 2)); _zMax = _zMax max ((_x select 1) max (_x select 2)); } forEach _prof;
            _zMin = _zMin - 5;
            private _zs = (_zMax - _zMin) max 10;
            private _np = count _prof;
            private _cwid = _w / (_np max 1);
            {
                _x params ["_d", "_g", "_z"];
                private _bx = _pad + _forEachIndex * _cwid;
                private _gh = _ph * ((_g - _zMin) / _zs);
                [[_bx, _y + _ph - _gh, _cwid + pixelW, _gh], [0.20, 0.27, 0.23, 1]] call _box;
                private _zy = _y + _ph - _ph * ((_z - _zMin) / _zs);
                [[_bx + _cwid * 0.25, _zy - pixelH, _cwid * 0.5 max pixelW * 2, pixelH * 3], [_cRed, _cGreen] select (_ok || {_d <= _bd})] call _box;
            } forEach _prof;
            if (!_ok) then {
                private _bx = _pad + _w * (_bd / (_len max 1));
                [[_bx, _y, pixelW * 2, _ph], _cRed] call _box;
            };
            ["COMSPEC_RscText", [_pad + _pad * 0.3, _y, _w * 0.3, _fs * 1.1], "MOI", _fs * 0.7, _cAcc, true] call _lbl;
            ["COMSPEC_RscTextRight", [_pad + _w * 0.7 - _pad * 0.3, _y, _w * 0.3, _fs * 1.1], "CIBLE", _fs * 0.7, _cAcc, true] call _lbl;
            _y = _y + _ph + _pad * 0.6;
            [[_pad, _y, _w, _bh2], "VOIR SUR LA CARTE", compile format ["['focus', %1] call comspec_atak_native_fnc_reliefAction;", str [((_from select 0) + (_to select 0)) / 2, ((_from select 1) + (_to select 1)) / 2]]] call _btn;
            _y = _y + _bh2 + _pad;
        } else {
            ["COMSPEC_RscText", [_pad, _y, _w, _fs * 1.4], "Saisissez un carroyage, visez un point ou touchez la carte.", _fs * 0.85, _cDim] call _lbl;
            _y = _y + _fs * 1.4 + _pad;
        };
    };
    // ---------- BÂTIMENTS VUS ----------
    case "BLD": {
        private _br = _s getOrDefault ["rlBldRadius", 500];
        private _bBusy = missionNamespace getVariable ["COMSPEC_ATAK_BldBusy", false];
        _y = [_pad, _y, _w, "Réglages", "bâtiments de plus de 4 m, depuis mes yeux"] call _sec;
        _y = [_pad, _y, _w, "Rayon", "rlBldRadius", [["200 m", 200], ["500 m", 500], ["1 km", 1000]], _br] call _seg;
        if (_bBusy) then {
            _y = [_pad, _y, _w, "Recherche en cours", missionNamespace getVariable ["COMSPEC_ATAK_BldProgress", 0]] call _progress;
        } else {
            [[_pad, _y, _half, _bh2], "CHERCHER", { ["bld"] call comspec_atak_native_fnc_reliefAction; }, true, "Vert sur la carte : vu · rouge : caché"] call _btn;
            [[_pad + _half + _gap, _y, _half, _bh2], "EFFACER", { ["bldClear"] call comspec_atak_native_fnc_reliefAction; }] call _btn;
            _y = _y + _bh2 + _pad;
        };
        private _bld = missionNamespace getVariable ["COMSPEC_ATAK_ReliefBld", []];
        if ((count _bld) > 3) then {
            _bld params ["_center", "_r", "_list", "_when"];
            private _seenL = _list select { (_x select 1) && {!isNull (_x select 0)} };
            private _n = count _list;
            private _pv = round (100 * (count _seenL) / (_n max 1));
            private _near = "—";
            if ((count _seenL) > 0) then { _near = [selectMin (_seenL apply { _x select 2 })] call _dist; };
            _y = [_pad, _y, _w, "Résultat", format ["%1 · %2 de rayon", [_when, "HH:MM"] call BIS_fnc_timeToString, [_r] call _dist]] call _sec;
            _y = [_pad, _y, _w, [
                ["bâtiments vus", str (count _seenL), _cGreen],
                ["cachés", str (_n - (count _seenL))],
                ["part vue", format ["%1 %%", _pv], [_pv] call _pctCol],
                ["le plus proche", _near]
            ]] call _stats;
            _y = [_pad, _y, _w, "Les plus proches", "vus depuis ici, donc d'où l'on peut me voir"] call _sec;
            private _sorted = _seenL apply { [_x select 2, _x] };
            _sorted sort true;
            if ((count _sorted) isEqualTo 0) then {
                ["COMSPEC_RscText", [_pad, _y, _w, _fs * 1.4], "Aucun bâtiment en vue dans ce rayon.", _fs * 0.85, _cDim] call _lbl;
                _y = _y + _fs * 1.4 + _pad;
            };
            private _ch = _fs * 2.6;
            private _mw = (_font * 3.6) min (_w * 0.25);
            {
                (_x select 1) params ["_b", "", "_d", "_az"];
                private _name = getText ((configOf _b) >> "displayName");
                if (_name isEqualTo "") then { _name = "Bâtiment"; };
                [[_pad, _y, _w, _ch], _cTile] call _box;
                [[_pad, _y, pixelW * 4, _ch], _cGreen] call _box;
                private _ic = ["COMSPEC_RscPicture", [_pad * 1.6, _y + _ch * 0.2, _ch * 0.6 / _ratio, _ch * 0.6], _dir + "relief_bld.paa"] call _mk;
                _ic ctrlSetTextColor _cGreen;
                private _cx = _pad * 2.2 + _ch * 0.6 / _ratio;
                private _c = ["COMSPEC_RscStructuredText", [_cx, _y + _ch * 0.08, _w - (_cx - _pad) - _mw - _pad, _ch * 0.9]] call _mk;
                _c ctrlSetStructuredText parseText ((format ["<t font='RobotoCondensedBold'>%1</t><br/><t size='0.85' color='#8a9a93'>%2 · %3° %4 · %5</t>",
                    _name, [_d] call _dist, _az, [_az] call _card, [getPosASL _b, 8] call comspec_atak_native_fnc_gridRef]) call comspec_atak_native_fnc_tr);
                _c ctrlSetFontHeight _fs;
                [[_pad + _w - _mw - _pad * 0.4, _y + _ch * 0.2, _mw, _ch * 0.6], "CARTE", compile format ["['focus', %1] call comspec_atak_native_fnc_reliefAction;", str ((getPosASL _b) select [0, 2])]] call _btn;
                _y = _y + _ch + _pad * 0.4;
            } forEach (_sorted select [0, 10]);
            _y = _y + _pad * 0.6;
        } else {
            if !(_bBusy) then {
                ["COMSPEC_RscText", [_pad, _y, _w, _fs * 1.4], "Touchez CHERCHER : les bâtiments vus passent en vert sur la carte.", _fs * 0.85, _cDim] call _lbl;
                _y = _y + _fs * 1.4 + _pad;
            };
        };
    };
};
_y = _y + _pad;
_bg ctrlSetPosition [0, 0, _bw, _bh max _y];
_bg ctrlCommit 0;
true
