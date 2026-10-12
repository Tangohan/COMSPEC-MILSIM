/*
    Lisse et allège un tracé à main levée (dessin libre de la carte, crayon de l'app Plan).
    1. Douglas-Peucker : retire les points alignés (tremblement de la souris) à la tolérance donnée ;
    2. Chaikin (2 passes) : arrondit les angles du tracé ;
    3. Douglas-Peucker plus fin, puis plafond de points (tolérance relevée tant qu'il y en a trop).
    Params : [points 3D, tolérance en mètres (≈ 2 px à l'échelle du dessin), lisser, fermé, nombre maximal de points]
    Renvoie les points 3D (fermé : le premier point est répété à la fin).
*/
params [["_pts", []], ["_tol", 1], ["_smooth", true], ["_closed", false], ["_max", 150]];
private _in = _pts apply { [_x select 0, _x select 1, 0] };
if ((count _in) < 2) exitWith { _in };
private _rdp = {
    params ["_p", "_t"];
    private _n = count _p;
    if (_n < 3) exitWith { +_p };
    private _keep = _p apply { false };
    _keep set [0, true];
    _keep set [_n - 1, true];
    private _stack = [[0, _n - 1]];
    while { (count _stack) > 0 } do {
        (_stack deleteAt ((count _stack) - 1)) params ["_i0", "_i1"];
        private _a = _p select _i0;
        private _ab = (_p select _i1) vectorDiff _a;
        private _l2 = _ab vectorDotProduct _ab;
        private _dMax = -1;
        private _iMax = -1;
        for "_i" from (_i0 + 1) to (_i1 - 1) do {
            private _q = _p select _i;
            private _u = if (_l2 > 0) then { ((((_q vectorDiff _a) vectorDotProduct _ab) / _l2) max 0) min 1 } else { 0 };
            private _d = _q distance2D (_a vectorAdd (_ab vectorMultiply _u));
            if (_d > _dMax) then { _dMax = _d; _iMax = _i; };
        };
        if (_dMax > _t) then { _keep set [_iMax, true]; _stack pushBack [_i0, _iMax]; _stack pushBack [_iMax, _i1]; };
    };
    private _out = [];
    { if (_keep select _forEachIndex) then { _out pushBack _x; }; } forEach _p;
    _out
};
private _chaikin = {
    params ["_p"];
    private _n = count _p;
    if (_n < 3) exitWith { +_p };
    private _out = [];
    if (!_closed) then { _out pushBack (_p select 0); };
    private _last = [_n - 2, _n - 1] select _closed;
    for "_i" from 0 to _last do {
        private _a = _p select _i;
        private _b = _p select ((_i + 1) mod _n);
        _out pushBack ((_a vectorMultiply 0.75) vectorAdd (_b vectorMultiply 0.25));
        _out pushBack ((_a vectorMultiply 0.25) vectorAdd (_b vectorMultiply 0.75));
    };
    if (!_closed) then { _out pushBack (_p select (_n - 1)); };
    _out
};
// Fermé : on travaille sans le point de fermeture (Chaikin fait le tour), il est remis à la fin.
if (_closed && {(count _in) > 2} && {((_in select 0) distance2D (_in select -1)) < _tol * 3}) then { _in deleteAt ((count _in) - 1); };
private _p = [_in, _tol * 0.6] call _rdp;
if (_smooth) then { for "_k" from 1 to 2 do { _p = [_p] call _chaikin; }; };
private _t = _tol * 0.35;
private _out = [_p, _t] call _rdp;
while { (count _out) > _max && {_t < _tol * 50} } do { _t = _t * 1.6; _out = [_p, _t] call _rdp; };
if (_closed && {(count _out) > 2}) then { _out pushBack (_out select 0); };
_out apply { [(round ((_x select 0) * 10)) / 10, (round ((_x select 1) * 10)) / 10, 0] }
