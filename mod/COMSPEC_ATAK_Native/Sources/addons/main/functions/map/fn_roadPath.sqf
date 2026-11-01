/*
    Moteur d'itinéraire routier COMSPEC : option ROUTES de l'outil TRAIT (fn_markerStroke) et GPS routier (fn_routeCompute).
    A* sur le réseau routier d'Arma (roadsConnectedTo) :
      - coût PLUS RAPIDE (temps, vitesse estimée selon la voie) ou PLUS COURT (distance) ;
      - préférence de voies (toutes, grands axes, discret) et ponts évités au choix ;
      - raccords : une route en cul-de-sac est reliée aux routes à moins de 25 m de ses bouts (le réseau d'Arma est
        souvent coupé aux carrefours et aux ponts, l'ancien calcul échouait alors en entier) ;
      - repli partiel : extrémité loin de toute route, réseaux non reliés ou recherche trop longue -> le morceau
        manquant en ligne droite, le reste par la route (avant : tout le trait en ligne droite) ;
      - points de passage : [A, étape 1, ..., B], chaque tronçon calculé à la suite ;
      - jamais de gel : à lancer en spawn (cède la main toutes les 400 routes visitées), plafond de routes et de durée
        (30 s ; appel hors spawn : 3000 routes et 0,5 s au plus) ;
      - réseau gardé pour la session (COMSPEC_ATAK_RoadNet : position, voie, pont et voisins de chaque route visitée) :
        un deuxième tracé dans la même zone ne relit plus le réseau d'Arma.
    Params : [points [A, ..., B], options (HashMap, toutes facultatives)]
      mode "FAST" | "SHORT" ; pref "ALL" | "MAIN" | "QUIET" ; bridges (true : ponts évités) ; bias (HashMap voie -> facteur,
      remplace pref) ; snap (rayon d'accroche, m, 150) ; snapFar (rayon de repli, m, 1500) ; keepEnds (true : les points
      donnés restent dans le tracé) ; raw (true : points non simplifiés) ; maxNodes (40000) ; job (HashMap d'avancement :
      « nodes » = routes visitées).
    Renvoie [points, statut "ROAD" | "PARTIAL" | "DIRECT", infos HashMap : dist, road, off (m), veh, foot (s), failed,
    nodes, text (« 4.2 km · véhicule 6 min · à pied 52 min »)]
*/
params [["_wps", []], ["_opt", createHashMap]];
_wps = _wps apply { [_x select 0, _x select 1, 0] };
private _t0 = diag_tickTime;
private _fast = (_opt getOrDefault ["mode", "FAST"]) isEqualTo "FAST";
private _snap = _opt getOrDefault ["snap", 150];
private _snapFar = (_opt getOrDefault ["snapFar", 1500]) max _snap;
private _radii = [_snap, (600 max _snap) min _snapFar, _snapFar];
_radii = _radii arrayIntersect _radii;
private _keepEnds = _opt getOrDefault ["keepEnds", false];
private _noBridge = _opt getOrDefault ["bridges", false];
private _job = _opt getOrDefault ["job", createHashMap];
private _sched = canSuspend;
private _budget = (_opt getOrDefault ["maxNodes", 40000]) min ([3000, 1e7] select _sched);
private _deadline = _t0 + ([0.5, 30] select _sched);
// Vitesse estimée d'un véhicule par voie (km/h) ; à pied 5 km/h ; hors route 15 km/h en véhicule, 4 km/h à pied.
private _kmh = createHashMapFromArray [["MAIN ROAD", 60], ["ROAD", 45], ["TRACK", 25], ["TRAIL", 12]];
// Facteurs de préférence, jamais sous 1 (l'estimation restante de l'A* reste un minorant).
private _bias = _opt getOrDefault ["bias", switch (_opt getOrDefault ["pref", "ALL"]) do {
    case "MAIN": { createHashMapFromArray [["ROAD", 1.15], ["TRACK", 2.5], ["TRAIL", 4]] };
    case "QUIET": { createHashMapFromArray [["MAIN ROAD", 2.5], ["ROAD", 1.4], ["TRAIL", 1.2]] };
    default { createHashMap };
}];
// Estimation restante (vol d'oiseau) un peu gonflée : chemin à quelques % du meilleur au pire, bien moins de routes
// visitées sur une grande carte.
private _hk = [1.1, 1.3 * 3.6 / 60] select _fast;
private _failed = false;
private _visited = 0;

private _net = missionNamespace getVariable ["COMSPEC_ATAK_RoadNet", createHashMap];
if ((count _net) > 80000) then { _net = createHashMap; };
missionNamespace setVariable ["COMSPEC_ATAK_RoadNet", _net];
// Route -> clé du réseau : [objet, position, voie, pont, voisins (0 tant qu'ils ne sont pas lus)].
private _node = {
    private _k = str _this;
    if !(_k in _net) then {
        private _i = getRoadInfo _this;
        private _p = getPosATL _this;
        _net set [_k, [_this, [_p select 0, _p select 1, 0], toUpper (_i param [0, "ROAD"]), _i param [8, false], 0]];
    };
    _k
};
// Voisins [clé, raccord] d'une route, lus une fois par session.
private _nbrs = {
    private _e = _net get _this;
    private _l = _e select 4;
    if (_l isEqualType []) exitWith { _l };
    private _ro = _e select 0;
    private _cs = (roadsConnectedTo [_ro, true]) select { !isNull _x && {_x isNotEqualTo _ro} };
    _l = _cs apply { [_x call _node, false] };
    if ((count _cs) < 2) then {
        (getRoadInfo _ro) params ["", "", "", "", "", "", ["_b", []], ["_f", []]];
        private _near = [];
        { if ((count _x) >= 2) then { _near append (_x nearRoads 25); }; } forEach [_b, _f];
        if ((count _near) isEqualTo 0) then { _near = (_e select 1) nearRoads 30; };
        { if (!isNull _x && {_x isNotEqualTo _ro} && {!(_x in _cs)}) then { _l pushBack [_x call _node, true]; }; } forEach (_near arrayIntersect _near);
    };
    _e set [4, _l];
    _l
};
// Point le plus proche sur les segments de route du rayon (projection bornée aux bouts) : [point, route, écart] ou [].
private _project = {
    params ["_p", "_r"];
    private _best = _r;
    private _out = [];
    {
        if (isNull _x) then { continue };
        (getRoadInfo _x) params ["", "", "", "", "", "", ["_beg", []], ["_end", []]];
        private _q = getPosATL _x;
        _q = [_q select 0, _q select 1, 0];
        if ((count _beg) >= 2 && {(count _end) >= 2}) then {
            private _o = [_beg select 0, _beg select 1, 0];
            private _seg = [_end select 0, _end select 1, 0] vectorDiff _o;
            private _l2 = _seg vectorDotProduct _seg;
            if (_l2 > 0) then { _q = _o vectorAdd (_seg vectorMultiply (((((_p vectorDiff _o) vectorDotProduct _seg) / _l2) max 0) min 1)); };
        };
        private _d = _p distance2D _q;
        if (_d < _best) then { _best = _d; _out = [_q, _x, _d]; };
    } forEach (_p nearRoads _r);
    _out
};
// Rayon d'accroche élargi par paliers (150 m, 600 m, 1500 m) : nearRoads sur 1500 m n'est lu que loin de tout.
private _snapTo = {
    params ["_p"];
    private _res = [];
    { _res = [_p, _x] call _project; if ((count _res) > 0) exitWith {}; } forEach _radii;
    _res
};
// Tas binaire [f, clé].
private _push = {
    params ["_f", "_k"];
    _heap pushBack [_f, _k];
    private _i = (count _heap) - 1;
    while { _i > 0 } do {
        private _p = floor ((_i - 1) / 2);
        if (((_heap select _p) select 0) <= _f) exitWith {};
        private _t = _heap select _p; _heap set [_p, _heap select _i]; _heap set [_i, _t]; _i = _p;
    };
};
private _pop = {
    private _top = _heap select 0;
    private _last = _heap deleteAt ((count _heap) - 1);
    if ((count _heap) > 0) then {
        _heap set [0, _last];
        private _i = 0; private _n = count _heap;
        while { true } do {
            private _l = 2 * _i + 1; private _r = _l + 1; private _m = _i;
            if (_l < _n && {((_heap select _l) select 0) < ((_heap select _m) select 0)}) then { _m = _l; };
            if (_r < _n && {((_heap select _r) select 0) < ((_heap select _m) select 0)}) then { _m = _r; };
            if (_m isEqualTo _i) exitWith {};
            private _t = _heap select _m; _heap set [_m, _heap select _i]; _heap set [_i, _t]; _i = _m;
        };
    };
    _top
};
// A* de la route _k0 vers la route _k1 ("" : vers le point _goal seul). Renvoie [trouvé, clés de _k0 à la route
// atteinte] ; pas trouvé : chemin jusqu'à la route visitée la plus proche de _goal.
private _astar = {
    params ["_k0", "_k1", "_goal"];
    private _g = createHashMapFromArray [[_k0, 0]];
    private _par = createHashMap;
    private _closed = createHashMap;
    private _heap = [];
    private _bestK = _k0;
    private _bestD = ((_net get _k0) select 1) distance2D _goal;
    [_bestD * _hk, _k0] call _push;
    private _found = false;
    while { (count _heap) > 0 } do {
        if (_budget <= 0 || {diag_tickTime > _deadline}) exitWith {};
        private _cur = (call _pop) select 1;
        if (_cur in _closed) then { continue };
        _closed set [_cur, true];
        _budget = _budget - 1;
        _visited = _visited + 1;
        if (_cur isEqualTo _k1) exitWith { _found = true; _bestK = _cur; };
        private _e = _net get _cur;
        private _pc = _e select 1;
        private _dg = _pc distance2D _goal;
        if (_dg < _bestD) then { _bestD = _dg; _bestK = _cur; };
        if (_sched && {(_visited mod 400) isEqualTo 0}) then { _job set ["nodes", _visited]; uiSleep 0.001; };
        private _gc = _g get _cur;
        {
            _x params ["_k", "_jump"];
            if (_k in _closed) then { continue };
            private _f = _net get _k;
            private _t = _f select 2;
            private _c = (_pc distance2D (_f select 1)) * (_bias getOrDefault [_t, 1]);
            if (_fast) then { _c = _c * 3.6 / (_kmh getOrDefault [_t, 40]); };
            if (_jump) then { _c = _c * 1.5; };
            if (_noBridge && {_f select 3}) then { _c = _c * 5; };
            private _ng = _gc + _c;
            if (_ng < (_g getOrDefault [_k, 1e12])) then {
                _g set [_k, _ng];
                _par set [_k, _cur];
                [_ng + ((_f select 1) distance2D _goal) * _hk, _k] call _push;
            };
        } forEach (_cur call _nbrs);
    };
    private _chain = [];
    private _k = _bestK;
    while { _k isNotEqualTo "" } do { _chain pushBack _k; _k = _par getOrDefault [_k, ""]; };
    reverse _chain;
    [_found, _chain]
};
// Un tronçon P -> Q : [points, voie du morceau qui finit à chaque point ("OFF" : hors route, "" : premier point)].
private _leg = {
    params ["_p", "_q"];
    private _d = _p distance2D _q;
    private _sp = [_p] call _snapTo;
    private _sq = [_q] call _snapTo;
    // Pas de route, ou routes si loin que le détour n'a pas de sens : ligne droite.
    if ((count _sp) isEqualTo 0 || {(count _sq) isEqualTo 0} || {((_sp select 2) + (_sq select 2)) > ((2 * _snap) max (0.7 * _d))}) exitWith { [[_p, _q], ["", "OFF"]] };
    _sp params ["_pp", "_rp", "_dp"];
    _sq params ["_qq", "_rq", "_dq"];
    private _kp = _rp call _node;
    private _kq = _rq call _node;
    private _c1 = [];
    private _c2 = [];
    if (_kp isNotEqualTo _kq) then {
        ([_kp, _kq, (_net get _kq) select 1] call _astar) params ["_ok", "_chain"];
        _c1 = _chain;
        if (!_ok) then {
            _failed = true;
            // Réseaux non reliés ou recherche trop longue : depuis B, la route la plus proche du bout atteint ; le trou
            // entre les deux passe en ligne droite.
            _budget = _budget max 2000;
            _deadline = _deadline max (diag_tickTime + ([0.2, 3] select _sched));
            ([_kq, "", (_net get (_c1 select -1)) select 1] call _astar) params ["", "_back"];
            reverse _back;
            _c2 = _back;
        };
    };
    private _p1 = _c1 apply { (_net get _x) select 1 };
    private _t1 = _c1 apply { (_net get _x) select 2 };
    private _p2 = _c2 apply { (_net get _x) select 1 };
    private _t2 = _c2 apply { (_net get _x) select 2 };
    // Centre de la première / dernière route derrière le point accroché : retiré (pas d'aller-retour sur le segment).
    if ((count _p1) >= 2 && {(_pp distance2D (_p1 select 1)) < ((_p1 select 0) distance2D (_p1 select 1))}) then { _p1 deleteAt 0; _t1 deleteAt 0; };
    if ((count _p2) >= 2) then {
        if ((_qq distance2D (_p2 select -2)) < ((_p2 select -1) distance2D (_p2 select -2))) then { _p2 deleteAt ((count _p2) - 1); _t2 deleteAt ((count _t2) - 1); };
    } else {
        if ((count _p2) isEqualTo 0 && {(count _p1) >= 2} && {(_qq distance2D (_p1 select -2)) < ((_p1 select -1) distance2D (_p1 select -2))}) then { _p1 deleteAt ((count _p1) - 1); _t1 deleteAt ((count _t1) - 1); };
    };
    private _tp = (_net get _kp) select 2;
    private _tq = (_net get _kq) select 2;
    private _pts = [];
    private _ty = [];
    // Point donné gardé s'il est loin de la route (morceau hors route) ou si l'appelant le demande (GPS : ma position).
    if (_keepEnds || {_dp > _snap}) then { _pts pushBack _p; _ty pushBack ""; };
    _pts pushBack _pp; _ty pushBack ([_tp, "OFF"] select (_dp > _snap));
    _pts append _p1; _ty append _t1;
    if ((count _p2) > 0) then { _pts append _p2; _ty append (["OFF"] + (_t2 select [1])); };
    _pts pushBack _qq; _ty pushBack _tq;
    if (_keepEnds || {_dq > _snap}) then { _pts pushBack _q; _ty pushBack ([_tq, "OFF"] select (_dq > _snap)); };
    _ty set [0, ""];
    [_pts, _ty]
};

private _all = [];
private _types = [];
for "_i" from 0 to ((count _wps) - 2) do {
    ([_wps select _i, _wps select (_i + 1)] call _leg) params ["_lp", "_lt"];
    {
        if ((count _all) > 0 && {((_all select -1) distance2D _x) < 0.5}) then { continue };
        private _t = _lt select _forEachIndex;
        _all pushBack _x;
        _types pushBack ([_t, "OFF"] select (_t isEqualTo ""));
    } forEach _lp;
};
if ((count _all) < 2) then { _all = +_wps; _types = _wps apply { "OFF" }; };
// Longueurs et temps estimés.
private _road = 0; private _off = 0; private _veh = 0; private _foot = 0;
for "_i" from 1 to ((count _all) - 1) do {
    private _len = (_all select (_i - 1)) distance2D (_all select _i);
    private _t = _types select _i;
    if (_t isEqualTo "OFF") then { _off = _off + _len; _veh = _veh + _len * 3.6 / 15; _foot = _foot + _len * 3.6 / 4; } else {
        _road = _road + _len; _veh = _veh + _len * 3.6 / (_kmh getOrDefault [_t, 40]); _foot = _foot + _len * 3.6 / 5;
    };
};
private _status = ["ROAD", "PARTIAL"] select (_off > 1);
if (_road < 1) then { _status = "DIRECT"; };
// Tracé de carte : points alignés retirés, virages gardés (Douglas-Peucker, fn_strokeSimplify), 199 points au plus.
if !(_opt getOrDefault ["raw", false]) then {
    _all = [_all, 6, false, false, 199] call comspec_atak_native_fnc_strokeSimplify;
    if ((count _all) > 200) then {
        private _step = (count _all) / 199;
        private _thin = [];
        for "_i" from 0 to 198 do { _thin pushBack (_all select (floor (_i * _step))); };
        _thin pushBack (_all select -1);
        _all = _thin;
    };
};
private _fmtD = { if (_this >= 1000) then { format ["%1 km", (_this / 1000) toFixed 1] } else { format ["%1 m", round _this] } };
private _fmtT = {
    private _m = (ceil (_this / 60)) max 1;
    if (_m < 60) then { format ["%1 min", _m] } else { format ["%1 h %2", floor (_m / 60), [str (_m mod 60), "0" + str (_m mod 60)] select ((_m mod 60) < 10)] }
};
private _text = format ["%1 · véhicule %2 · à pied %3", (_road + _off) call _fmtD, _veh call _fmtT, _foot call _fmtT];
if (_status isNotEqualTo "ROAD" && {_off >= 20}) then { _text = _text + format [" · %1 hors route", _off call _fmtD]; };
_job set ["nodes", _visited];
diag_log format ["[COMSPEC ATAK NATIVE] Tracé routier : %1 point(s), %2, %3 routes visitées, %4 s, réseau gardé %5 routes", count _wps, _status, _visited, (diag_tickTime - _t0) toFixed 2, count _net];
[_all, _status, createHashMapFromArray [["dist", _road + _off], ["road", _road], ["off", _off], ["veh", _veh], ["foot", _foot], ["failed", _failed], ["nodes", _visited], ["text", _text]]]
