/*
    App Zones : calculs en arrière-plan (lancé par spawn depuis fn_zoneAction, jamais dans une image).
    Params : [opération, id du plan, jeton (un calcul plus récent annule celui-ci), argument]
      "ZONES" : découpage de la zone de travail du plan (réglages gp du plan) :
                GRID   grille régulière (case de 50 à 1000 m, calée sur le carroyage de la carte), noms GRG « C4 »
                       (colonnes en lettres d'ouest en est, rangées en chiffres du nord au sud) ou numéros en serpentin ;
                BLOCK  îlots / quartiers : les routes de la carte (getRoadInfo : début, fin) servent de frontières, sur
                       une trame de 8 m ou plus (14 000 cellules au plus) remplie par zones connexes, contour suivi puis
                       simplifié ; les îlots trop petits (entre deux routes) sont écartés ; numéros du nord-ouest au sud-est ;
                RADIAL secteurs autour du centre (4, 8 ou 16, nommés N, NE… ou numérotés), 1 à 3 anneaux ;
                SINGLE la zone de travail en une seule zone ;
                LINES  « découper par mes traits » : comme les îlots, mais les frontières sont les contours et traits
                       tracés du plan (POLYGONE ouvert ou fermé) au lieu des routes.
              Journal [ZONES] : méthode, nombre de zones, durée ; message clair si aucune zone ne sort.
              -> ["genDone", [id, jeton, zones, message]] de fn_zoneAction
      "AXES"  : grands axes routiers de la zone de travail : graphe des routes (nearRoads, roadsConnectedTo), plus long
                chemin de chaque morceau (deux parcours de distance), morceaux retirés puis recommencés ; classés par
                longueur et type de route (route principale d'abord) : le premier MSR, les suivants ASR ; carrefours sur ces
                axes (3 routes ou plus) en points de contrôle CP1… espacés de 250 m au moins.
              -> ["axesDone", [id, jeton, axes, points de contrôle, message]]
      "BLD"   : bâtiments d'une zone (argument : id de zone) numérotés du nord au sud puis d'ouest en est (rangées de
                25 m, comme l'outil BÂTIMENTS de la carte), étages d'après la hauteur (3 m par étage, 8 au plus, comme ECOTI).
              -> ["bldDone", [id, jeton, id de zone, bâtiments]]
*/
params [["_op", ""], ["_id", ""], ["_tok", 0], ["_arg", ""]];
if (!canSuspend) exitWith { _this spawn comspec_atak_native_fnc_zoneGen; };
private _ZA = comspec_atak_native_fnc_zoneAction;
private _GEO = comspec_atak_native_fnc_zoneGeom;
private _bp = ["get", _id] call _ZA;
if ((count _bp) isEqualTo 0) exitWith {};
private _alive = { (((["get", _id] call comspec_atak_native_fnc_zoneAction) getOrDefault ["genTok", -1]) isEqualTo _tok) };
private _gp = _bp getOrDefault ["gp", createHashMap];
(["areaPoly", _bp] call _ZA) params [["_apoly", []], ["_aKind", "POLY"], ["_aC", [0, 0]], ["_aR", 0]];
// axesDone : [id, jeton, axes, points de contrôle, message] (avant : message passé à la place des points de contrôle, jamais affiché).
if ((count _apoly) < 3) exitWith { if (_op isEqualTo "AXES") then { ["axesDone", [_id, _tok, [], [], "Zone de travail à tracer d'abord"]] call _ZA; } else { ["genDone", [_id, _tok, [], "Zone de travail à tracer d'abord"]] call _ZA; }; };
private _ap3 = _apoly apply { [_x select 0, _x select 1, 0] };
(["bbox", [_apoly]] call _GEO) params ["_bx0", "_by0", "_bx1", "_by1"];
private _pre = _gp getOrDefault ["prefix", ""];
private _lbl = { params ["_core"]; [_core, format ["%1-%2", _pre, _core]] select (_pre isNotEqualTo "") };
private _two = { params ["_n", ["_tot", 99]]; private _t = str _n; if (_tot > 9 && {_n < 10}) then { _t = "0" + _t; }; if (_tot > 99 && {_n < 100}) then { _t = "0" + _t; }; _t };
// Zone : [id (« z<calcul>_<n> » : un nouveau découpage ne reprend pas la situation de l'ancien), nom, points, centre, type, rendu, état, horodatage, auteur, signalements, bâtiments, rectangle]
private _mkZone = { params ["_i", "_name", "_pts", "_ctr", ["_rect", []]]; [format ["z%1_%2", _tok, _i], _name, _pts, _ctr, _bp getOrDefault ["aff", "GEN"], "", "UNREC", 0, "", [], [], _rect] };

private _t0 = diag_tickTime;
switch (_op) do {
    case "ZONES": {
        private _cut = _gp getOrDefault ["cut", "GRID"];
        private _zones = [];
        private _msg = "";
        switch (_cut) do {
            case "GRID": {
                private _S = ((_gp getOrDefault ["size", 200]) max 50) min 2000;
                private _x0 = _bx0; private _y0 = _by0;
                if (_gp getOrDefault ["snap", true]) then { _x0 = (floor (_bx0 / _S)) * _S; _y0 = (floor (_by0 / _S)) * _S; };
                private _nx = ceil ((_bx1 - _x0) / _S) max 1;
                private _ny = ceil ((_by1 - _y0) / _S) max 1;
                private _cap = (missionNamespace getVariable ["comspec_atak_native_zones_maxcells", 200]) max 20 min 600;
                if ((_nx * _ny) > (_cap * 4)) exitWith { _msg = format ["Grille trop fine : %1 cases. Prenez des cases plus grandes", _nx * _ny]; };
                private _cells = [];
                for "_j" from (_ny - 1) to 0 step -1 do {
                    private _row = _ny - 1 - _j;
                    for "_i" from 0 to (_nx - 1) do {
                        private _c = [_x0 + (_i + 0.5) * _S, _y0 + (_j + 0.5) * _S, 0];
                        if (_c inPolygon _ap3) then { _cells pushBack [_row, _i, _c]; };
                    };
                };
                if ((count _cells) > _cap) exitWith { _msg = format ["%1 cases : %2 au plus (réglage serveur). Prenez des cases plus grandes ou une zone plus petite", count _cells, _cap]; };
                if ((count _cells) isEqualTo 0) exitWith {
                    private _smaller = [50, 100, 200, 250, 500, 1000] select { _x < _S };
                    _msg = format ["Zone de travail trop petite pour des cases de %1 m : choisissez %2 m", _S, _smaller param [((count _smaller) - 1) max 0, 50]];
                };
                // Colonnes en lettres (A… Z, AA…), rangées du nord au sud.
                private _col = { params ["_i"]; private _t = ""; private _v = _i; while { true } do { _t = ("ABCDEFGHIJKLMNOPQRSTUVWXYZ" select [_v mod 26, 1]) + _t; _v = floor (_v / 26) - 1; if (_v < 0) exitWith {}; }; _t };
                private _num = (_gp getOrDefault ["fmt", "GRG"]) isEqualTo "NUM";
                if (_num) then {
                    // Serpentin : rangée 1 d'ouest en est, rangée 2 d'est en ouest…
                    _cells = _cells apply { [_x select 0, [_x select 1, -(_x select 1)] select (((_x select 0) mod 2) isEqualTo 1), _x] };
                    _cells sort true;
                    _cells = _cells apply { _x select 2 };
                };
                private _tot = count _cells;
                // Lettres et chiffres comptés depuis la première colonne et la première rangée gardées.
                private _iMin = 1e9; private _rMin = 1e9;
                { _iMin = _iMin min (_x select 1); _rMin = _rMin min (_x select 0); } forEach _cells;
                {
                    _x params ["_row", "_i", "_c"];
                    private _h = _S / 2;
                    private _cx = _c select 0; private _cy = _c select 1;
                    private _core = if (_num) then { [_forEachIndex + 1, _tot] call _two } else { format ["%1%2", [_i - _iMin] call _col, _row - _rMin + 1] };
                    _zones pushBack ([_forEachIndex + 1, [_core] call _lbl, [[_cx - _h, _cy - _h], [_cx + _h, _cy - _h], [_cx + _h, _cy + _h], [_cx - _h, _cy + _h]], [_cx, _cy], [_cx, _cy, _h, _h]] call _mkZone);
                } forEach _cells;
            };
            case "RADIAL": {
                private _k = _gp getOrDefault ["sec", 8];
                if !(_k in [4, 8, 16]) then { _k = 8; };
                private _m = ((_gp getOrDefault ["rings", 1]) max 1) min 3;
                private _c = _aC; private _rad0 = _aR;
                if (_aKind isNotEqualTo "CIRCLE") then {
                    _c = ["centroid", [_apoly]] call _GEO;
                    _rad0 = (((_bx1 - _bx0) max (_by1 - _by0)) / 2) max 50;
                };
                private _card = (_gp getOrDefault ["secFmt", "CARD"]) isEqualTo "CARD";
                private _n = 0;
                for "_r" from 1 to _m do {
                    for "_i" from 0 to (_k - 1) do {
                        _n = _n + 1;
                        private _a = _i * 360 / _k;
                        private _pts = ["ring", [_c, _rad0 * (_r - 1) / _m, _rad0 * _r / _m, _a - 180 / _k, _a + 180 / _k]] call _GEO;
                        // Point cardinal dans la langue du téléphone (fn_cardinal), jamais repassé par la traduction.
                        private _core = if (_card) then {
                            format ["%1%2", [_a, [8, 16] select (_k isEqualTo 16)] call comspec_atak_native_fnc_cardinal, ["", str _r] select (_m > 1)]
                        } else { [_n, _k * _m] call _two };
                        private _lp = ([_c select 0, _c select 1, 0] getPos [_rad0 * (_r - 0.5) / _m, _a]) select [0, 2];
                        if (_r isEqualTo 1 && {_m isEqualTo 1}) then { _lp = ([_c select 0, _c select 1, 0] getPos [_rad0 * 0.6, _a]) select [0, 2]; };
                        _zones pushBack ([_n, [_core] call _lbl, _pts, _lp] call _mkZone);
                    };
                };
            };
            case "SINGLE": {
                // MIL-STD : abréviation du type et nom de code du plan (« OBJ DELTA », « AA TAMPA ») ; sinon le préfixe.
                private _sName = [_pre, "1"] select (_pre isEqualTo "");
                if ((_gp getOrDefault ["theme", "CITY"]) isEqualTo "MIL") then {
                    private _sCode = ((_bp get "name") splitString " ") param [1, ""];
                    if (_sCode isNotEqualTo "") then { _sName = (format ["%1 %2", ["abbrOf", _bp getOrDefault ["aff", "GEN"]] call _ZA, _sCode]) select [0, 16]; };
                };
                _zones pushBack ([1, _sName, _apoly select [0, 120], ["label", [_apoly]] call _GEO] call _mkZone);
            };
            case "BLOCK";
            case "LINES": {
                private _W = _bx1 - _bx0; private _H = _by1 - _by0;
                private _s = ceil ((sqrt (_W * _H / 14000)) max 8);
                private _nx = ceil (_W / _s) max 1; private _ny = ceil (_H / _s) max 1;
                private _nCells = _nx * _ny;
                private _g = [];
                _g resize _nCells;
                private _isRect = _aKind isEqualTo "RECT";
                for "_j" from 0 to (_ny - 1) do {
                    for "_i" from 0 to (_nx - 1) do {
                        private _c = [_bx0 + (_i + 0.5) * _s, _by0 + (_j + 0.5) * _s, 0];
                        _g set [_j * _nx + _i, [-1, 0] select ((_isRect || {_c inPolygon _ap3}) && {!(surfaceIsWater _c)})];
                    };
                };
                if !(call _alive) exitWith {};
                // Routes : frontières des îlots (cellule traversée = barrière ; pas d'un demi-côté : trait continu en 8-connexité,
                // infranchissable pour le remplissage en 4-connexité).
                // Segments frontières : routes de la carte (îlots) ou contours et traits du plan (découper par mes traits).
                private _segs = [];
                if (_cut isEqualTo "LINES") then {
                    {
                        _x params ["", "", "_lp", "_closed"];
                        private _n = count _lp;
                        for "_i" from 0 to (_n - ([2, 1] select _closed)) do { _segs pushBack [_lp select _i, _lp select ((_i + 1) mod _n)]; };
                    } forEach (_bp getOrDefault ["lines", []]);
                } else {
                    private _types = ["MAIN ROAD", "ROAD"];
                    if (_gp getOrDefault ["tracks", false]) then { _types pushBack "TRACK"; };
                    private _ctr = [(_bx0 + _bx1) / 2, (_by0 + _by1) / 2, 0];
                    private _roads = _ctr nearRoads ((sqrt (_W * _W + _H * _H)) / 2 + 30);
                    if ((count _roads) > 12000) then { _roads resize 12000; };
                    {
                        private _inf = getRoadInfo _x;
                        if ((toUpper (_inf param [0, ""])) in _types) then {
                            private _b = _inf param [6, []]; private _e = _inf param [7, []];
                            if ((count _b) >= 2 && {(count _e) >= 2}) then { _segs pushBack [_b, _e]; };
                        };
                    } forEach _roads;
                };
                private _nr = 0;
                {
                    _x params ["_b", "_e"];
                    _nr = _nr + 1;
                    private _len = _b distance2D _e;
                    private _st = (ceil (_len / (_s * 0.5))) max 1;
                    for "_t" from 0 to _st do {
                        private _px = (_b select 0) + ((_e select 0) - (_b select 0)) * _t / _st;
                        private _py = (_b select 1) + ((_e select 1) - (_b select 1)) * _t / _st;
                        private _i = floor ((_px - _bx0) / _s); private _j = floor ((_py - _by0) / _s);
                        if (_i >= 0 && {_i < _nx} && {_j >= 0} && {_j < _ny}) then { _g set [_j * _nx + _i, -1]; };
                    };
                    if ((_forEachIndex mod 400) isEqualTo 399) then { sleep 0.005; };
                } forEach _segs;
                if (_nr isEqualTo 0) exitWith { _msg = ["Aucune route dans la zone : choisissez la grille ou les secteurs", "Aucun trait dans le plan : tracez d'abord des traits (POLYGONE, Ouvert) à travers la zone de travail"] select (_cut isEqualTo "LINES"); };
                if !(call _alive) exitWith {};
                // Remplissage : numéro d'îlot de chaque cellule.
                private _rid = [];
                _rid resize _nCells;
                for "_k" from 0 to (_nCells - 1) do { _rid set [_k, 0]; };
                private _regions = [];
                private _next = 0;
                for "_k0" from 0 to (_nCells - 1) do {
                    if ((_g select _k0) isEqualTo 0 && {(_rid select _k0) isEqualTo 0}) then {
                        _next = _next + 1;
                        private _q = [_k0];
                        _rid set [_k0, _next];
                        private _h = 0;
                        while { _h < (count _q) } do {
                            private _k = _q select _h;
                            _h = _h + 1;
                            private _i = _k mod _nx; private _j = floor (_k / _nx);
                            {
                                _x params ["_ok", "_kk"];
                                if (_ok && {(_g select _kk) isEqualTo 0} && {(_rid select _kk) isEqualTo 0}) then { _rid set [_kk, _next]; _q pushBack _kk; };
                            } forEach [[_i > 0, _k - 1], [_i < (_nx - 1), _k + 1], [_j > 0, _k - _nx], [_j < (_ny - 1), _k + _nx]];
                        };
                        _regions pushBack [count _q, _next, _q];
                    };
                };
                if !(call _alive) exitWith {};
                private _minA = ((_gp getOrDefault ["minA", 2500]) max 400) min 100000;
                _regions = _regions select { ((_x select 0) * _s * _s) >= _minA };
                _regions sort false;
                private _cap = (missionNamespace getVariable ["comspec_atak_native_zones_maxblocks", 80]) max 10 min 200;
                if ((count _regions) > _cap) then { _msg = format ["%1 îlots trouvés : les %2 plus grands gardés", count _regions, _cap]; _regions resize _cap; };
                private _out = [];
                {
                    _x params ["_cnt", "_r", "_cells"];
                    // Contour : côtés de cellule bordés par une autre zone, orientés (l'îlot à gauche), enchaînés en boucles.
                    private _edges = createHashMap;
                    private _add = { params ["_a", "_b"]; private _l = _edges getOrDefault [_a, []]; _l pushBack _b; _edges set [_a, _l]; };
                    private _W1 = _nx + 1;
                    private _sx = 0; private _sy = 0;
                    {
                        private _i = _x mod _nx; private _j = floor (_x / _nx);
                        _sx = _sx + _i; _sy = _sy + _j;
                        if (_j isEqualTo 0 || {(_rid select (_x - _nx)) isNotEqualTo _r}) then { [_j * _W1 + _i, _j * _W1 + _i + 1] call _add; };
                        if (_i isEqualTo (_nx - 1) || {(_rid select (_x + 1)) isNotEqualTo _r}) then { [_j * _W1 + _i + 1, (_j + 1) * _W1 + _i + 1] call _add; };
                        if (_j isEqualTo (_ny - 1) || {(_rid select (_x + _nx)) isNotEqualTo _r}) then { [(_j + 1) * _W1 + _i + 1, (_j + 1) * _W1 + _i] call _add; };
                        if (_i isEqualTo 0 || {(_rid select (_x - 1)) isNotEqualTo _r}) then { [(_j + 1) * _W1 + _i, _j * _W1 + _i] call _add; };
                    } forEach _cells;
                    private _best = []; private _bestA = 0;
                    private _guard = 0;
                    while { (count _edges) > 0 && {_guard < 50} } do {
                        _guard = _guard + 1;
                        private _start = (keys _edges) select 0;
                        private _loop = [];
                        private _cur = _start;
                        private _steps = 0;
                        while { _steps < 20000 } do {
                            _steps = _steps + 1;
                            _loop pushBack [_bx0 + (_cur mod _W1) * _s, _by0 + (floor (_cur / _W1)) * _s];
                            private _l = _edges getOrDefault [_cur, []];
                            if ((count _l) isEqualTo 0) exitWith {};
                            private _nxt = _l deleteAt 0;
                            if ((count _l) isEqualTo 0) then { _edges deleteAt _cur; };
                            _cur = _nxt;
                            if (_cur isEqualTo _start) exitWith {};
                        };
                        private _ar = abs (["area", [_loop]] call _GEO);
                        if (_ar > _bestA) then { _bestA = _ar; _best = _loop; };
                    };
                    // Sommets alignés retirés, puis simplification (Douglas-Peucker) à ~ une cellule.
                    private _red = [];
                    private _n = count _best;
                    for "_i" from 0 to (_n - 1) do {
                        private _p0 = _best select ((_i + _n - 1) mod _n); private _p1 = _best select _i; private _p2 = _best select ((_i + 1) mod _n);
                        if ((abs (((_p1 select 0) - (_p0 select 0)) * ((_p2 select 1) - (_p1 select 1)) - ((_p1 select 1) - (_p0 select 1)) * ((_p2 select 0) - (_p1 select 0)))) > 0.01) then { _red pushBack _p1; };
                    };
                    private _pts = _red;
                    if ((count _red) > 8) then {
                        private _sim = [_red apply { [_x select 0, _x select 1, 0] }, _s * 0.85, false, true, 48] call comspec_atak_native_fnc_strokeSimplify;
                        if ((count _sim) > 3 && {((_sim select 0) distance2D (_sim select -1)) < 0.5}) then { _sim deleteAt ((count _sim) - 1); };
                        if ((count _sim) >= 4) then { _pts = _sim apply { [_x select 0, _x select 1] }; };
                    };
                    if ((count _pts) >= 3) then {
                        // Étiquette : cellule de l'îlot la plus proche de la moyenne des cellules.
                        private _mi = _sx / _cnt; private _mj = _sy / _cnt;
                        private _bk = _cells select 0; private _bd = 1e9;
                        { private _d = (((_x mod _nx) - _mi) ^ 2) + (((floor (_x / _nx)) - _mj) ^ 2); if (_d < _bd) then { _bd = _d; _bk = _x; }; } forEach _cells;
                        private _lp = [_bx0 + ((_bk mod _nx) + 0.5) * _s, _by0 + ((floor (_bk / _nx)) + 0.5) * _s];
                        _out pushBack [_pts, _lp];
                    };
                } forEach _regions;
                if !(call _alive) exitWith {};
                // Numéros : rangées du nord au sud (bandes de 150 m), puis d'ouest en est.
                private _band = 150 max (_s * 8);
                _out = _out apply { [-(round (((_x select 1) select 1) / _band)), (_x select 1) select 0, _x] };
                _out sort true;
                private _tot = count _out;
                { (_x select 2) params ["_pts", "_lp"]; _zones pushBack ([_forEachIndex + 1, [[_forEachIndex + 1, _tot] call _two] call _lbl, _pts, _lp] call _mkZone); } forEach _out;
                if ((count _zones) isEqualTo 0 && {_msg isEqualTo ""}) then { _msg = "Aucun îlot assez grand : baissez la surface minimale ou agrandissez la zone"; };
            };
        };
        if !(call _alive) exitWith {};
        if ((count _zones) isEqualTo 0 && {_msg isEqualTo ""}) then { _msg = "Aucune zone : zone de travail trop petite pour ce découpage"; };
        ["INFO", "ZONES", format ["Découpage %1 du plan %2 : %3 zone(s) en %4 s%5", _cut, _id, count _zones, (diag_tickTime - _t0) toFixed 2, ["", " · " + _msg] select (_msg isNotEqualTo "")]] call comspec_atak_native_fnc_log;
        ["genDone", [_id, _tok, _zones, _msg]] call _ZA;
    };
    case "AXES": {
        private _ctr = [(_bx0 + _bx1) / 2, (_by0 + _by1) / 2, 0];
        private _rad = (sqrt (((_bx1 - _bx0) ^ 2) + ((_by1 - _by0) ^ 2))) / 2 + 20;
        private _all = (_ctr nearRoads _rad) select { (getPosATL _x) inPolygon _ap3 };
        if ((count _all) > 9000) then { _all resize 9000; };
        // Nœuds : [route, position, longueur, classe] ; pistes seulement s'il n'y a presque rien d'autre.
        private _raw = _all apply { private _i = getRoadInfo _x; [_x, toUpper (_i param [0, ""]), _i param [6, []], _i param [7, []]] };
        private _lenOf = { params ["_b", "_e"]; if ((count _b) >= 2 && {(count _e) >= 2}) then { (_b distance2D _e) max 1 } else { 10 } };
        private _mainLen = 0;
        { if ((_x select 1) in ["MAIN ROAD", "ROAD"]) then { _mainLen = _mainLen + ([_x select 2, _x select 3] call _lenOf); }; } forEach _raw;
        private _types = ["MAIN ROAD", "ROAD"];
        if (_mainLen < 400 || {_gp getOrDefault ["tracks", false]}) then { _types pushBack "TRACK"; };
        private _nodes = [];
        private _ix = createHashMap;
        {
            _x params ["_ro", "_t", "_b", "_e"];
            if (_t in _types) then { _ix set [str _ro, count _nodes]; _nodes pushBack [_ro, (getPosATL _ro) select [0, 2], [_b, _e] call _lenOf, _t]; };
        } forEach _raw;
        private _n = count _nodes;
        if (_n < 3) exitWith { ["axesDone", [_id, _tok, [], [], "Aucune route dans la zone de travail"]] call _ZA; };
        private _adj = _nodes apply { [] };
        {
            private _u = _forEachIndex;
            {
                private _v = _ix getOrDefault [str _x, -1];
                if (_v >= 0 && {_v isNotEqualTo _u}) then { (_adj select _u) pushBackUnique _v; (_adj select _v) pushBackUnique _u; };
            } forEach ((roadsConnectedTo [_x select 0, false]) call {
                // Raccord « proche » seulement pour un bout de route sans suite : pris partout, il faisait sauter l'axe sur une
                // route parallèle (angle sec, trait qui quitte la route).
                if ((count _this) >= 2) exitWith { _this };
                roadsConnectedTo [_x select 0, true]
            });
        } forEach _nodes;
        private _deg = _adj apply { count _x };
        if !(call _alive) exitWith {};
        private _live = _nodes apply { true };
        // Distances depuis un nœud (parcours qui corrige les distances, graphe routier presque en arbre).
        private _far = {
            params ["_src"];
            private _d = _nodes apply { -1 };
            private _par = _nodes apply { -1 };
            _d set [_src, 0];
            private _q = [_src]; private _h = 0;
            while { _h < (count _q) && {_h < 300000} } do {
                private _u = _q select _h; _h = _h + 1;
                private _du = _d select _u;
                private _lu = (_nodes select _u) select 2;
                {
                    if (_live select _x) then {
                        private _nd = _du + (_lu + ((_nodes select _x) select 2)) / 2;
                        private _dv = _d select _x;
                        if (_dv < 0 || {_nd < _dv - 0.5}) then { _d set [_x, _nd]; _par set [_x, _u]; _q pushBack _x; };
                    };
                } forEach (_adj select _u);
            };
            private _best = _src; private _bd = 0;
            { if (_x > _bd) then { _bd = _x; _best = _forEachIndex; }; } forEach _d;
            [_best, _bd, _d, _par]
        };
        private _minLen = ((_gp getOrDefault ["axMin", 300]) max 100) min 5000;
        private _cand = [];
        private _it = 0;
        while { _it < 40 && {(count _cand) < 12} } do {
            _it = _it + 1;
            // Départ : un nœud vivant, route principale d'abord.
            private _s = _live findIf { _x };
            if (_s < 0) exitWith {};
            private _sm = -1;
            { if ((_live select _forEachIndex) && {((_x select 3) isEqualTo "MAIN ROAD")}) exitWith { _sm = _forEachIndex; }; } forEach _nodes;
            if (_sm >= 0) then { _s = _sm; };
            ([_s] call _far) params ["_a", "", "_d0"];
            ([_a] call _far) params ["_b", "_L", "", "_par"];
            private _path = [_b];
            private _c = _b;
            while { _c isNotEqualTo _a && {(_par select _c) >= 0} && {(count _path) < 20000} } do { _c = _par select _c; _path pushBack _c; };
            if (_L < _minLen) then {
                // Morceau trop court : tout le morceau retiré.
                { if (_x >= 0) then { _live set [_forEachIndex, false]; }; } forEach _d0;
            } else {
                private _cls = createHashMap;
                { private _t = (_nodes select _x) select 3; _cls set [_t, (_cls getOrDefault [_t, 0]) + ((_nodes select _x) select 2)]; } forEach _path;
                private _w = (_cls getOrDefault ["MAIN ROAD", 0]) * 1.6 + (_cls getOrDefault ["ROAD", 0]) + (_cls getOrDefault ["TRACK", 0]) * 0.5;
                _cand pushBack [_w, _L, _path];
                { _live set [_x, false]; { _live set [_x, false]; } forEach (_adj select _x); } forEach _path;
            };
            if !(call _alive) exitWith {};
        };
        if !(call _alive) exitWith {};
        if ((count _cand) isEqualTo 0) exitWith { ["axesDone", [_id, _tok, [], [], format ["Aucun axe de plus de %1 m dans la zone", _minLen]]] call _ZA; };
        // Axe déjà tracé par un autre plan (mêmes routes à 20 m près, sur 12 points) : écarté, pas de trait en double.
        private _others = [];
        { if ((_x get "id") isNotEqualTo _id) then { { _others pushBack (_x select 3); } forEach (_x getOrDefault ["axes", []]); }; } forEach ((values (["mine"] call _ZA)) + (values (["rx"] call _ZA)));
        private _dup = 0;
        if ((count _others) > 0) then {
            _cand = _cand select {
                private _path = _x select 2;
                private _stp = ceil ((count _path) / 12) max 1;
                private _near = 0; private _tot = 0;
                for "_i" from 0 to ((count _path) - 1) step _stp do {
                    private _p = (_nodes select (_path select _i)) select 1;
                    _tot = _tot + 1;
                    if ((_others findIf { (_x findIf { (_x distance2D _p) < 20 }) >= 0 }) >= 0) then { _near = _near + 1; };
                };
                private _keep = (_near / (_tot max 1)) < 0.7;
                if (!_keep) then { _dup = _dup + 1; };
                _keep
            };
        };
        if ((count _cand) isEqualTo 0) exitWith { ["axesDone", [_id, _tok, [], [], "Ces axes sont déjà tracés : rien de plus à nommer ici"]] call _ZA; };
        _cand sort false;
        private _nAsr = ((_gp getOrDefault ["asr", 3]) max 0) min 6;
        _cand resize ((count _cand) min (1 + _nAsr));
        private _names = ["axisNames", [_id, count _cand]] call _ZA;
        private _axes = [];
        private _cps = [];
        private _space = 250;
        {
            _x params ["", "_L", "_path"];
            private _cls = ["ASR", "MSR"] select (_forEachIndex isEqualTo 0);
            private _name = format ["%1 %2", _cls, _names param [_forEachIndex, str (_forEachIndex + 1)]];
            private _pts = _path apply { private _p = (_nodes select _x) select 1; [_p select 0, _p select 1, 0] };
            private _sim = [_pts, 4, false, false, 150] call comspec_atak_native_fnc_strokeSimplify;
            _axes pushBack [format ["a%1", _forEachIndex + 1], _name, _cls, _sim apply { [_x select 0, _x select 1] }, round _L, ""];
            {
                if ((_deg select _x) >= 3 && {(count _cps) < 16}) then {
                    private _p = (_nodes select _x) select 1;
                    if ((_cps findIf { ((_x select 2) distance2D _p) < _space }) < 0) then { _cps pushBack [(count _cps) + 1, format ["CP%1", (count _cps) + 1], _p, _name]; };
                };
            } forEach _path;
        } forEach _cand;
        ["axesDone", [_id, _tok, _axes, _cps, ""]] call _ZA;
    };
    case "BLD": {
        private _z = ["zone", [_id, _arg]] call _ZA;
        if ((count _z) isEqualTo 0) exitWith {};
        private _pts = (_z select 2) apply { [_x select 0, _x select 1, 0] };
        (["bbox", [_pts]] call _GEO) params ["_x0", "_y0", "_x1", "_y1"];
        private _c = [(_x0 + _x1) / 2, (_y0 + _y1) / 2, 0];
        private _r = (sqrt (((_x1 - _x0) ^ 2) + ((_y1 - _y0) ^ 2))) / 2 + 5;
        private _hs = (nearestObjects [_c, ["House", "Building"], _r min 1500]) select { (count (_x buildingPos -1)) > 0 && {!isObjectHidden _x} && {(getPosATL _x) inPolygon _pts} };
        private _keyed = _hs apply { private _p = getPosASL _x; [round ((_p select 1) / -25), _p select 0, _x] };
        _keyed sort true;
        if ((count _keyed) > 99) then { _keyed resize 99; };
        private _out = _keyed apply {
            private _b = _x select 2;
            (boundingBoxReal _b) params ["_p1", "_p2"];
            private _h = abs ((_p2 select 2) - (_p1 select 2));
            private _p = getPosASL _b;
            [round (_p select 0), round (_p select 1), (round ((_p2 select 0) - (_p1 select 0))) / 2, (round ((_p2 select 1) - (_p1 select 1))) / 2, round (getDir _b), (((round (_h / 3)) max 1) min 8)]
        };
        ["bldDone", [_id, _tok, _arg, _out]] call _ZA;
    };
};
