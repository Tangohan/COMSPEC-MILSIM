/*
    Primitives de dessin sur une carte (événement Draw) : traits épais, tirets, pointes de flèche, polygones,
    étiquettes encadrées. Partagées par la carte de briefing (fn_planDraw) et le dessin libre de la carte (fn_strokeDraw).
    Les tailles sont en pixels écran (converties en mètres à l'échelle courante) : même rendu à tout zoom.
    Params : [opération, carte, arguments]
      "mpx"   []                                                        -> mètres par pixel à l'échelle courante
      "line"  [points 3D, couleur, épaisseur px, tirets, flèche, pointe pleine, m/px]   la pointe suit le dernier segment
      "poly"  [points 3D, couleur, épaisseur px, tirets, alpha du remplissage, m/px]    polygone fermé (rempli s'il est convexe)
      "head"  [pointe, cap, couleur, longueur px, pleine, épaisseur px, m/px]           pointe de flèche
      "label" [position, texte, couleur, taille du texte, m/px, [décalage px x, y], encadré]
*/
params ["_op", "_map", ["_a", []]];
private _tex = "#(rgb,8,8,3)color(1,1,1,1)";
// Segment épais : rectangle orienté (drawLine ne fait qu'un pixel) ; demi-largeur en mètres.
private _seg = {
    params ["_p", "_q", "_hw", "_c", "_mpx"];
    private _len = _p distance2D _q;
    if (_len < 0.01) exitWith {};
    if (_hw <= _mpx * 0.75) exitWith { _map drawLine [_p, _q, _c]; };
    _map drawRectangle [[((_p select 0) + (_q select 0)) / 2, ((_p select 1) + (_q select 1)) / 2, 0], _hw, _len / 2, _p getDir _q, _c, _tex];
};
private _head = {
    params ["_tip", "_h", "_c", "_lenPx", "_solid", "_wPx", "_mpx"];
    private _l = _lenPx * _mpx;
    private _base = _tip getPos [_l, _h + 180];
    private _wl = _base getPos [_l * 0.55, _h - 90];
    private _wr = _base getPos [_l * 0.55, _h + 90];
    if (_solid) then {
        _map drawTriangle [[_tip, _wl, _wr], _c, _tex];
    } else {
        [_tip, _wl, (_wPx max 1.5) * _mpx / 2, _c, _mpx] call _seg;
        [_tip, _wr, (_wPx max 1.5) * _mpx / 2, _c, _mpx] call _seg;
    };
};
// Polyligne pleine ou en tirets (motif continu d'un segment à l'autre) ; jointures arrondies au-delà de 2 px.
private _poly = {
    params ["_pts", "_c", "_wPx", "_dashed", "_mpx"];
    private _hw = _wPx * _mpx / 2;
    private _dOn = (7 + _wPx * 2) * _mpx;
    private _dOff = (5 + _wPx) * _mpx;
    private _on = true;
    private _ph = 0;
    for "_i" from 0 to ((count _pts) - 2) do {
        private _p = _pts select _i;
        private _q = _pts select (_i + 1);
        if (!_dashed) then {
            [_p, _q, _hw, _c, _mpx] call _seg;
            if (_wPx >= 2 && {_i > 0}) then { _map drawEllipse [_p, _hw, _hw, 0, _c, _tex]; };
        } else {
            private _len = _p distance2D _q;
            if (_len < 0.01) then { continue };
            private _u = (_q vectorDiff _p) vectorMultiply (1 / _len);
            private _t = 0;
            while { _t < _len } do {
                private _cur = [_dOff, _dOn] select _on;
                private _step = (_cur - _ph) min (_len - _t);
                if (_on) then { [_p vectorAdd (_u vectorMultiply _t), _p vectorAdd (_u vectorMultiply (_t + _step)), _hw, _c, _mpx] call _seg; };
                _t = _t + _step;
                _ph = _ph + _step;
                if (_ph >= _cur - 0.001) then { _ph = 0; _on = !_on; };
            };
        };
    };
};
switch (_op) do {
    case "mpx": { ((_map ctrlMapScreenToWorld [0.5, 0.5]) distance2D (_map ctrlMapScreenToWorld [0.5 + pixelW * 100, 0.5])) / 100 };
    case "line": {
        _a params ["_pts", "_c", ["_wPx", 2], ["_dashed", false], ["_arrow", false], ["_solid", false], ["_mpx", 1]];
        if ((count _pts) < 2) exitWith {};
        if (_arrow) then {
            private _n = count _pts;
            private _tip = _pts select (_n - 1);
            private _prev = _pts select (_n - 2);
            // Cap du dernier tronçon assez long (un dernier point posé tout près ne fausse pas la pointe).
            for "_j" from (_n - 2) to 0 step -1 do { _prev = _pts select _j; if ((_prev distance2D _tip) > 6 * _mpx) exitWith {}; };
            private _h = _prev getDir _tip;
            private _lenPx = 10 + _wPx * 3;
            private _body = +_pts;
            // Pointe pleine : le trait s'arrête à sa base.
            if (_solid && {((_pts select (_n - 2)) distance2D _tip) > _lenPx * _mpx}) then { _body set [_n - 1, _tip getPos [_lenPx * _mpx * 0.8, _h + 180]]; };
            [_body, _c, _wPx, _dashed, _mpx] call _poly;
            [_tip, _h, _c, _lenPx, _solid, _wPx, _mpx] call _head;
        } else {
            [_pts, _c, _wPx, _dashed, _mpx] call _poly;
        };
    };
    case "poly": {
        _a params ["_pts", "_c", ["_wPx", 2], ["_dashed", false], ["_fill", 0], ["_mpx", 1]];
        if ((count _pts) < 2) exitWith {};
        if (_fill > 0 && {(count _pts) >= 3}) then {
            // Remplissage léger en éventail, seulement pour un polygone convexe (sinon il déborderait).
            private _sgn = 0;
            private _convex = true;
            private _n = count _pts;
            for "_i" from 0 to (_n - 1) do {
                private _p0 = _pts select _i; private _p1 = _pts select ((_i + 1) mod _n); private _p2 = _pts select ((_i + 2) mod _n);
                private _cr = (((_p1 select 0) - (_p0 select 0)) * ((_p2 select 1) - (_p1 select 1))) - (((_p1 select 1) - (_p0 select 1)) * ((_p2 select 0) - (_p1 select 0)));
                if (abs _cr > 0.0001) then {
                    private _sc = [1, -1] select (_cr < 0);
                    if (_sgn isEqualTo 0) then { _sgn = _sc; } else { if (_sc isNotEqualTo _sgn) then { _convex = false; }; };
                };
            };
            if (_convex) then {
                private _tri = [];
                for "_i" from 1 to (_n - 2) do { _tri append [_pts select 0, _pts select _i, _pts select (_i + 1)]; };
                private _fc = +_c; _fc set [3, _fill];
                _map drawTriangle [_tri, _fc, _tex];
            };
        };
        [_pts + [_pts select 0], _c, _wPx, _dashed, _mpx] call _poly;
    };
    case "head": { _a call _head; };
    case "label": {
        _a params ["_pos", "_text", "_c", "_tsz", "_mpx", ["_off", [0, 0]], ["_boxed", true]];
        if (_text isEqualTo "") exitWith {};
        private _p = [(_pos select 0) + (_off select 0) * _mpx, (_pos select 1) - (_off select 1) * _mpx, 0];
        if (_boxed) then {
            // Largeur estimée : ~0,52 hauteur par caractère (COMSPEC_BarlowBold).
            private _hPx = _tsz / pixelH;
            private _wPx = (count _text) * 0.52 * _hPx;
            _map drawRectangle [_p, (_wPx / 2 + 5) * _mpx, (_hPx / 2 + 1.5) * _mpx, 0, [0.03, 0.04, 0.035, 0.80], _tex];
            private _bc = +_c; _bc set [3, (_c select 3) * 0.85];
            _map drawRectangle [_p, (_wPx / 2 + 5) * _mpx, (_hPx / 2 + 1.5) * _mpx, 0, _bc, ""];
        };
        _map drawIcon ["#(argb,8,8,3)color(0,0,0,0)", _c, _p, 0, 0, 0, _text, [0, 2] select !_boxed, _tsz, (COMSPEC_ATAK_FontSet select 1), "center"];
    };
};
