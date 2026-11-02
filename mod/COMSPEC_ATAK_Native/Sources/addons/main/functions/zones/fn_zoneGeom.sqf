/*
    App Zones : géométrie des polygones (points [x, y] ou [x, y, z], sans point de fermeture).
    Params : [opération, arguments]
      "area"     [points]            -> surface signée (m², positive dans le sens trigonométrique)
      "centroid" [points]            -> centre de gravité [x, y] (moyenne des sommets si la surface est nulle)
      "bbox"     [points]            -> [x0, y0, x1, y1]
      "label"    [points]            -> point d'étiquette dans le polygone : centre de gravité, sinon le point intérieur
                                        le plus proche trouvé sur une petite grille
      "tri"      [points]            -> triangles [p1, p2, p3, p4...] pour drawTriangle (découpage en oreilles ; un
                                        polygone qui ne se découpe pas finit en éventail depuis son centre)
      "circle"   [centre, rayon, n]  -> polygone de n sommets
      "ring"     [centre, r0, r1, a0, a1]  -> secteur d'anneau (r0 = 0 : part de tarte), angles en degrés (cap)
    Appelé hors image (génération, préparation du dessin) : le découpage en oreilles coûte n² à n³.
*/
params [["_op", ""], ["_a", []]];
switch (_op) do {
    case "area": {
        _a params [["_p", []]];
        private _n = count _p;
        private _s = 0;
        for "_i" from 0 to (_n - 1) do {
            private _u = _p select _i; private _v = _p select ((_i + 1) mod _n);
            _s = _s + ((_u select 0) * (_v select 1) - (_v select 0) * (_u select 1));
        };
        _s / 2
    };
    case "centroid": {
        _a params [["_p", []]];
        private _n = count _p;
        if (_n isEqualTo 0) exitWith { [0, 0] };
        private _s = 0; private _cx = 0; private _cy = 0;
        for "_i" from 0 to (_n - 1) do {
            private _u = _p select _i; private _v = _p select ((_i + 1) mod _n);
            private _c = (_u select 0) * (_v select 1) - (_v select 0) * (_u select 1);
            _s = _s + _c;
            _cx = _cx + ((_u select 0) + (_v select 0)) * _c;
            _cy = _cy + ((_u select 1) + (_v select 1)) * _c;
        };
        if ((abs _s) < 0.01) exitWith {
            private _mx = 0; private _my = 0;
            { _mx = _mx + (_x select 0); _my = _my + (_x select 1); } forEach _p;
            [_mx / _n, _my / _n]
        };
        [_cx / (3 * _s), _cy / (3 * _s)]
    };
    case "bbox": {
        _a params [["_p", []]];
        private _x0 = 1e9; private _y0 = 1e9; private _x1 = -1e9; private _y1 = -1e9;
        { _x0 = _x0 min (_x select 0); _y0 = _y0 min (_x select 1); _x1 = _x1 max (_x select 0); _y1 = _y1 max (_x select 1); } forEach _p;
        [_x0, _y0, _x1, _y1]
    };
    case "label": {
        _a params [["_p", []]];
        private _c = ["centroid", [_p]] call comspec_atak_native_fnc_zoneGeom;
        private _poly = _p apply { [_x select 0, _x select 1, 0] };
        if (([_c select 0, _c select 1, 0] inPolygon _poly) || {(count _p) < 3}) exitWith { _c };
        (["bbox", [_p]] call comspec_atak_native_fnc_zoneGeom) params ["_x0", "_y0", "_x1", "_y1"];
        private _best = []; private _bd = 1e12;
        for "_i" from 1 to 9 do {
            for "_j" from 1 to 9 do {
                private _q = [_x0 + (_x1 - _x0) * _i / 10, _y0 + (_y1 - _y0) * _j / 10, 0];
                if (_q inPolygon _poly) then {
                    private _d = _q distance2D _c;
                    if (_d < _bd) then { _bd = _d; _best = [_q select 0, _q select 1]; };
                };
            };
        };
        [_c, _best] select ((count _best) > 0)
    };
    case "tri": {
        _a params [["_p", []]];
        private _pts = _p apply { [_x select 0, _x select 1, 0] };
        private _n = count _pts;
        if (_n < 3) exitWith { [] };
        if ((["area", [_pts]] call comspec_atak_native_fnc_zoneGeom) < 0) then { reverse _pts; };
        if (_n isEqualTo 3) exitWith { _pts };
        private _cross = { params ["_o", "_u", "_v"]; ((_u select 0) - (_o select 0)) * ((_v select 1) - (_o select 1)) - ((_u select 1) - (_o select 1)) * ((_v select 0) - (_o select 0)) };
        private _idx = [];
        for "_i" from 0 to (_n - 1) do { _idx pushBack _i; };
        private _out = [];
        private _guard = 0;
        private _fail = false;
        while { (count _idx) > 3 && {!_fail} } do {
            _guard = _guard + 1;
            if (_guard > (_n * 3)) exitWith { _fail = true; };
            private _m = count _idx;
            private _cut = -1;
            // exitWith seulement au premier niveau de la boucle (dans un if, il ne quitte que le bloc du if).
            for "_i" from 0 to (_m - 1) do {
                if (_cut >= 0) exitWith {};
                private _pa = _pts select (_idx select ((_i + _m - 1) mod _m));
                private _pb = _pts select (_idx select _i);
                private _pc = _pts select (_idx select ((_i + 1) mod _m));
                if (([_pa, _pb, _pc] call _cross) > 0.01) then {
                    // Oreille : aucun autre sommet dans le triangle.
                    private _in = false;
                    private _skip = [(_i + _m - 1) mod _m, _i, (_i + 1) mod _m];
                    for "_j" from 0 to (_m - 1) do {
                        if (_in) exitWith {};
                        if !(_j in _skip) then {
                            private _q = _pts select (_idx select _j);
                            if (([_pa, _pb, _q] call _cross) >= 0 && {([_pb, _pc, _q] call _cross) >= 0} && {([_pc, _pa, _q] call _cross) >= 0}) then { _in = true; };
                        };
                    };
                    if (!_in) then { _cut = _i; _out append [_pa, _pb, _pc]; };
                };
            };
            if (_cut < 0) then { _fail = true; } else { _idx deleteAt _cut; };
        };
        if (_fail) exitWith {
            // Polygone tordu (simplification) : éventail depuis le centre, approché mais sans trou.
            private _c = ["centroid", [_pts]] call comspec_atak_native_fnc_zoneGeom;
            private _c3 = [_c select 0, _c select 1, 0];
            private _f = [];
            for "_i" from 0 to (_n - 1) do { _f append [_c3, _pts select _i, _pts select ((_i + 1) mod _n)]; };
            _f
        };
        _out append (_idx apply { _pts select _x });
        _out
    };
    case "circle": {
        _a params ["_c", "_r", ["_n", 36]];
        private _o = [];
        for "_i" from 0 to (_n - 1) do { _o pushBack (([_c select 0, _c select 1, 0] getPos [_r, _i * 360 / _n]) select [0, 2]); };
        _o
    };
    case "ring": {
        _a params ["_c", "_r0", "_r1", "_a0", "_a1"];
        private _c3 = [_c select 0, _c select 1, 0];
        private _span = _a1 - _a0;
        private _k = ceil (_span / 7.5) max 2;
        private _o = [];
        for "_i" from 0 to _k do { _o pushBack ((_c3 getPos [_r1, _a0 + _span * _i / _k]) select [0, 2]); };
        if (_r0 <= 0.5) then { _o pushBack [_c select 0, _c select 1]; } else {
            for "_i" from _k to 0 step -1 do { _o pushBack ((_c3 getPos [_r0, _a0 + _span * _i / _k]) select [0, 2]); };
        };
        _o
    };
    default { [] };
};
