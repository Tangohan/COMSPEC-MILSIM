/*
    Tracé collé aux routes entre deux points de la carte (option ROUTES de l'outil TRAIT, fn_markerStroke) : convoi, patrouille.
    Chaque extrémité est d'abord ramenée sur le segment de route le plus proche (projection reprise de MapTac,
    fnc_drawMapTool, © 2026 Smith and MapTac contributors, licence APL-SA), puis le chemin suit le réseau routier
    (A* sur roadsConnectedTo, comme le GPS routier fn_routeCompute). Pas de route proche ou chemin introuvable : ligne droite.
    À lancer en spawn (une grande carte peut prendre une seconde).
    Params : [point A, point B, rayon d'accroche en m (défaut 120)]
    Renvoie [points simplifiés (200 au plus), trouvé]
*/
params [["_a", [0, 0, 0]], ["_b", [0, 0, 0]], ["_snap", 120]];
// MapTac : projection du point sur le segment de route le plus proche dans le rayon.
private _project = {
    params ["_p"];
    private _best = _snap;
    private _out = +_p;
    {
        (getRoadInfo _x) params ["", "", "", "", "", "", ["_beg", []], ["_end", []]];
        if (_beg isEqualTo [] || {_end isEqualTo []}) then { continue };
        _beg params ["_bx", "_by"];
        private _sx = (_end select 0) - _bx;
        private _sy = (_end select 1) - _by;
        private _len = _sx ^ 2 + _sy ^ 2;
        if (_len == 0) then { continue };
        private _r = 0 max ((((_p select 0) - _bx) * _sx + ((_p select 1) - _by) * _sy) / _len min 1);
        private _q = [_bx + _sx * _r, _by + _sy * _r, 0];
        private _d = _p distance2D _q;
        if (_d < _best) then { _best = _d; _out = _q; };
    } forEach (_p nearRoads _snap);
    _out
};
private _nearest = {
    params ["_p"];
    private _rs = (_p nearRoads _snap) select { !isNull _x };
    if ((count _rs) isEqualTo 0) exitWith { objNull };
    private _k = _rs apply { [_p distance2D _x, _x] };
    _k sort true;
    (_k select 0) select 1
};
private _pa = [_a] call _project;
private _pb = [_b] call _project;
private _r0 = [_pa] call _nearest;
private _r1 = [_pb] call _nearest;
if (isNull _r0 || {isNull _r1}) exitWith { [[_a, _b], false] };
private _key = { str _this };
private _g = createHashMap; private _par = createHashMap; private _obj = createHashMap; private _closed = createHashMap;
private _heap = [];
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
private _k0 = _r0 call _key; private _k1 = _r1 call _key;
private _goal = getPosATL _r1;
_g set [_k0, 0]; _obj set [_k0, _r0];
[_r0 distance2D _goal, _k0] call _push;
private _found = false; private _n = 0;
while { (count _heap) > 0 && {_n < 40000} } do {
    private _cur = (call _pop) select 1;
    if (_cur in _closed) then { continue };
    _closed set [_cur, true];
    _n = _n + 1;
    if (_cur isEqualTo _k1) exitWith { _found = true; };
    private _ro = _obj get _cur;
    private _gc = _g get _cur;
    {
        private _k = _x call _key;
        if !(_k in _closed) then {
            private _ng = _gc + (_ro distance2D _x);
            if (_ng < (_g getOrDefault [_k, 1e12])) then {
                _g set [_k, _ng]; _par set [_k, _cur]; _obj set [_k, _x];
                [_ng + (_x distance2D _goal), _k] call _push;
            };
        };
    } forEach (roadsConnectedTo [_ro, true]);
};
if (!_found) exitWith { [[_pa, _pb], false] };
private _chain = [];
private _k = _k1;
while { _k isNotEqualTo "" } do { _chain pushBack getPosATL (_obj get _k); _k = _par getOrDefault [_k, ""]; };
reverse _chain;
private _pts = [_pa] + _chain + [_pb];
// Points trop proches ou alignés retirés (la polyligne d'un marqueur garde 200 points au plus).
private _simple = [_pts select 0];
for "_i" from 1 to ((count _pts) - 2) do {
    private _p = _simple select ((count _simple) - 1); private _q = _pts select _i; private _r = _pts select (_i + 1);
    private _turn = abs ((((_q getDir _r) - (_p getDir _q)) + 540) mod 360 - 180);
    if ((_p distance2D _q) > 10 && {_turn > 3 || {(_p distance2D _q) > 300}}) then { _simple pushBack _q; };
};
_simple pushBack (_pts select ((count _pts) - 1));
if ((count _simple) > 200) then {
    private _step = (count _simple) / 199;
    private _thin = [];
    for "_i" from 0 to 198 do { _thin pushBack (_simple select (floor (_i * _step))); };
    _thin pushBack (_simple select -1);
    _simple = _thin;
};
[_simple, true]
