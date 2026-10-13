/*
    Dessine des éléments de plan de briefing sur une carte (événement Draw), en coordonnées monde : le même plan
    se dessine à l'identique chez tous les joueurs, quel que soit le zoom (tailles en pixels écran).
    Params : [carte, éléments, opacité (aperçu < 1), id sélectionné]
    Élément : [id, type (fn_planCatalog), points [[x, y]...], texte, couleur, tirets, flèche, épaisseur (1-3), numéro, auteur]
*/
params ["_map", ["_items", []], ["_alpha", 1], ["_sel", ""]];
if (isNull _map || {(count _items) isEqualTo 0}) exitWith {};
private _cat = [] call comspec_atak_native_fnc_planCatalog;
private _kinds = _cat get "kinds";
private _colors = _cat get "colors";
private _mpx = ["mpx", _map] call comspec_atak_native_fnc_planGfx;
if (_mpx <= 0) exitWith {};
// Symboles proportionnels à l'écran de l'ATAK ; textes jamais sous une taille lisible.
private _kRaw = ((ctrlPosition _map) select 3) / (safeZoneH * 0.7);
private _k = (_kRaw max 0.35) min 1.35;
private _ts = ((profileNamespace getVariable ["COMSPEC_ATAK_MapTextScale", 1.25]) max 0.8) min 2.2;
private _tsz = 0.026 * ((_kRaw max 0.8) min 1.25) * _ts;
private _gfx = comspec_atak_native_fnc_planGfx;
private _white = [1, 1, 1, 0.9 * _alpha];
{
    _x params [["_id", ""], ["_kind", ""], ["_pts", []], ["_text", ""], ["_ck", "WHITE"], ["_dashed", false], ["_arrow", false], ["_w", 2], ["_num", 0]];
    private _def = _kinds getOrDefault [_kind, []];
    if ((count _def) isEqualTo 0 || {(count _pts) isEqualTo 0}) then { continue };
    private _geom = _def select 3;
    private _style = _def select 8;
    private _c = +(_colors getOrDefault [_ck, [1, 1, 1, 1]]);
    _c set [3, (_c select 3) * _alpha];
    private _p3 = _pts apply { [_x select 0, _x select 1, 0] };
    private _isSel = _id isEqualTo _sel && {_sel isNotEqualTo ""};
    private _wPx = (_w max 1 min 3) * 1.2;
    switch (_geom) do {
        case "AREA": {
            if ((count _p3) isEqualTo 2) then {
                // Deux coins opposés : rectangle nord-sud.
                _p3 params ["_a", "_b"];
                _p3 = [_a, [_b select 0, _a select 1, 0], _b, [_a select 0, _b select 1, 0]];
            };
            ["poly", _map, [_p3, _c, _wPx, _dashed, [0.07, 0.12] select (_kind in ["ENY", "EA", "NFA"]), _mpx]] call _gfx;
            if (_kind isEqualTo "NFA") then {
                // Tirs interdits : croix dans la zone (hachures APP-6 simplifiées).
                private _n = count _p3;
                if (_n >= 4) then { _map drawLine [_p3 select 0, _p3 select (floor (_n / 2)), _c]; _map drawLine [_p3 select 1, _p3 select (((floor (_n / 2)) + 1) mod _n), _c]; };
            };
            private _cx = 0; private _cy = 0;
            { _cx = _cx + (_x select 0); _cy = _cy + (_x select 1); } forEach _p3;
            private _lbl = [_text, (_def select 5)] select (_text isEqualTo "");
            ["label", _map, [[_cx / (count _p3), _cy / (count _p3), 0], toUpper _lbl, _c, _tsz, _mpx]] call _gfx;
            if (_isSel) then { { _map drawEllipse [_x, 3 * _mpx, 3 * _mpx, 0, _white, "#(rgb,8,8,3)color(1,1,1,1)"]; } forEach _p3; };
        };
        case "LINE": {
            switch (_style) do {
                case "AXIS": {
                    // Axe de progression : double contour décalé de part et d'autre du tracé, pointe ouverte au bout.
                    private _hw = (7 + _w * 2) * _mpx;
                    private _n = count _p3;
                    if (_n < 2) exitWith {};
                    private _tip = _p3 select (_n - 1);
                    private _hEnd = (_p3 select (_n - 2)) getDir _tip;
                    private _lHead = _hw * 2.2;
                    private _body = +_p3;
                    _body set [_n - 1, _tip getPos [_lHead, _hEnd + 180]];
                    private _left = []; private _right = [];
                    {
                        private _i = _forEachIndex;
                        private _hIn = if (_i > 0) then { (_body select (_i - 1)) getDir _x } else { _x getDir (_body select 1) };
                        private _hOut = if (_i < (count _body) - 1) then { _x getDir (_body select (_i + 1)) } else { _hIn };
                        // Cap moyen (par les vecteurs : pas d'erreur au passage 359° / 0°).
                        private _h = ((sin _hIn) + (sin _hOut)) atan2 ((cos _hIn) + (cos _hOut));
                        _left pushBack (_x getPos [_hw, _h - 90]);
                        _right pushBack (_x getPos [_hw, _h + 90]);
                    } forEach _body;
                    private _base = _body select (_n - 1);
                    private _back = +_right;
                    reverse _back;
                    private _outline = _left + [_base getPos [_hw * 2, _hEnd - 90], _tip, _base getPos [_hw * 2, _hEnd + 90]] + _back;
                    ["line", _map, [_outline, _c, _wPx, _dashed, false, false, _mpx]] call _gfx;
                    _map drawLine [_left select 0, _right select 0, _c];
                };
                case "ATK": { ["line", _map, [_p3, _c, _wPx + 1.5, _dashed, true, true, _mpx]] call _gfx; };
                default { ["line", _map, [_p3, _c, _wPx, _dashed, _arrow, false, _mpx]] call _gfx; };
            };
            private _lbl = [_text, (_def select 5)] select (_text isEqualTo "");
            if (_style isEqualTo "PHASE") then {
                // Ligne de phase : nom aux deux extrémités, un peu au-delà du tracé.
                private _n = count _p3;
                if (_n >= 2) then {
                    private _a = _p3 select 0; private _b = _p3 select (_n - 1);
                    ["label", _map, [_a getPos [16 * _mpx, ((_p3 select 1) getDir _a)], toUpper _lbl, _c, _tsz, _mpx]] call _gfx;
                    ["label", _map, [_b getPos [16 * _mpx, ((_p3 select (_n - 2)) getDir _b)], toUpper _lbl, _c, _tsz, _mpx]] call _gfx;
                };
            } else {
                if (_lbl isNotEqualTo "") then {
                    // Notation au milieu du tracé (point médian en longueur).
                    private _tot = 0;
                    for "_i" from 0 to ((count _p3) - 2) do { _tot = _tot + ((_p3 select _i) distance2D (_p3 select (_i + 1))); };
                    private _acc = 0; private _mid = _p3 select 0;
                    for "_i" from 0 to ((count _p3) - 2) do {
                        private _d = (_p3 select _i) distance2D (_p3 select (_i + 1));
                        if (_acc + _d >= _tot / 2 && {_d > 0}) exitWith { _mid = (_p3 select _i) vectorAdd (((_p3 select (_i + 1)) vectorDiff (_p3 select _i)) vectorMultiply ((_tot / 2 - _acc) / _d)); };
                        _acc = _acc + _d;
                    };
                    ["label", _map, [_mid, _lbl, _c, _tsz * 0.9, _mpx, [0, -12]]] call _gfx;
                };
            };
            if (_isSel) then { { _map drawEllipse [_x, 3 * _mpx, 3 * _mpx, 0, _white, "#(rgb,8,8,3)color(1,1,1,1)"]; } forEach [_p3 select 0, _p3 select ((count _p3) - 1)]; };
        };
        case "POINT": {
            private _p = _p3 select 0;
            private _lbl = [_text, (_def select 5)] select (_text isEqualTo "");
            if (_num > 0 && {_kind in ["CLEARED", "UNCLEARED", "CP", "RP", "TRP"]}) then { _lbl = format ["%1 %2", _lbl, _num]; };
            private _isz = 20 * _k;
            switch (true) do {
                case (_style in ["CHECK", "WARN"]): {
                    // Bâtiment : case à la couleur (coche verte ou point d'exclamation jaune), libellé numéroté en dessous à droite.
                    private _h = (_isz / 2) * _mpx;
                    _map drawRectangle [_p, _h, _h, 0, [0.03, 0.04, 0.035, 0.75 * _alpha], "#(rgb,8,8,3)color(1,1,1,1)"];
                    _map drawRectangle [_p, _h, _h, 0, _c, ""];
                    if (_style isEqualTo "CHECK") then {
                        _map drawIcon ["\z\comspec_atak_native\addons\main\data\ui_check.paa", _c, _p, _isz * 0.8, _isz * 0.8, 0, "", 0];
                    } else {
                        _map drawIcon ["#(argb,8,8,3)color(0,0,0,0)", _c, _p, 0, 0, 0, "!", 0, _tsz * 1.05, (COMSPEC_ATAK_FontSet select 1), "center"];
                    };
                    ["label", _map, [_p, toUpper _lbl, _c, _tsz * 0.9, _mpx, [_isz * 0.7 + (count _lbl) * 0.26 * (_tsz * 0.9 / pixelH), _isz * 0.9]]] call _gfx;
                };
                case (_style in ["NATO_B", "NATO_O"]): {
                    private _tex = format ["\A3\ui_f\data\map\markers\nato\%1_inf.paa", ["b", "o"] select (_style isEqualTo "NATO_O")];
                    _map drawIcon [_tex, _c, _p, _isz * 1.4, _isz * 1.4, 0, "", 0];
                    if (_lbl isNotEqualTo "") then { ["label", _map, [_p, _lbl, _c, _tsz * 0.9, _mpx, [0, _isz * 1.1]]] call _gfx; };
                };
                case (_style isEqualTo "NOTE"): {
                    _map drawIcon ["\A3\ui_f\data\map\markers\military\dot_CA.paa", _c, _p, _isz * 0.7, _isz * 0.7, 0, "", 0];
                    ["label", _map, [_p, _lbl, _c, _tsz * 0.9, _mpx, [(count _lbl) * 0.26 * (_tsz * 0.9 / pixelH) + 8, -10]]] call _gfx;
                };
                default {
                    private _tex = (_style select [5]);
                    _map drawIcon [_tex, _c, _p, _isz, _isz, 0, "", 0];
                    ["label", _map, [_p, toUpper _lbl, _c, _tsz * 0.9, _mpx, [0, _isz * 0.95]]] call _gfx;
                };
            };
            if (_isSel) then { _map drawEllipse [_p, _isz * 0.9 * _mpx, _isz * 0.9 * _mpx, 0, _white, ""]; };
        };
        case "DIR": {
            // Appui / attaque par le feu : base perpendiculaire à la visée, deux flèches vers l'objectif.
            private _p = _p3 select 0;
            private _aim = [_p3 select 1, _p getPos [60 * _mpx, 0]] select ((count _p3) < 2);
            private _h = _p getDir _aim;
            private _d = ((_p distance2D _aim) * 0.85) max (30 * _mpx);
            private _bh = 14 * _mpx;
            private _l = _p getPos [_bh, _h - 90];
            private _r = _p getPos [_bh, _h + 90];
            ["line", _map, [[_l, _r], _c, _wPx + 0.5, false, false, false, _mpx]] call _gfx;
            {
                ["line", _map, [[_x, _x getPos [_d, _h]], _c, _wPx, _dashed, true, _style isEqualTo "ABF", _mpx]] call _gfx;
            } forEach [_l, _r];
            private _lbl = [_text, (_def select 5)] select (_text isEqualTo "");
            ["label", _map, [_p getPos [12 * _mpx, _h + 180], toUpper _lbl, _c, _tsz * 0.9, _mpx]] call _gfx;
            if (_isSel) then { _map drawEllipse [_p, 18 * _mpx, 18 * _mpx, 0, _white, ""]; };
        };
    };
} forEach _items;
