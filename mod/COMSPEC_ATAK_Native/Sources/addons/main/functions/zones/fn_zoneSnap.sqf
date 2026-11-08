/*
    App Zones, outil POLYGONE « Aimanté » : point d'accroche le plus proche du curseur, comme le lasso magnétique d'un
    logiciel de dessin. Accroches : coins puis bords des bâtiments (boundingBoxReal des nearestObjects), segments des
    routes (getRoadInfo début / fin, roadsConnectedTo n'est pas nécessaire : chaque morceau porte ses deux bouts), rives
    (passage terre / eau, surfaceIsWater, recherche par dichotomie sur 12 directions).
    Coût : rien hors de l'outil ; appelé au plus ~10 fois par seconde par fn_zoneMap « move » ; routes et bâtiments lus
    une fois par case de 64 m (cache uiNamespace COMSPEC_ATAK_ZnSnapC, vidé au-delà de 300 cases).
    Params : ["at", [position [x, y], rayon (m)]] -> [[x, y], genre CORNER | EDGE | ROAD | SHORE] ou [] (rien d'assez près)
             ["clear"] vide le cache
*/
params [["_op", "at"], ["_a", []]];
if (_op isEqualTo "clear") exitWith { uiNamespace setVariable ["COMSPEC_ATAK_ZnSnapC", createHashMap]; true };
_a params [["_p", []], ["_R", 30]];
// Données anciennes ou mal formées : pas d'accroche plutôt qu'une erreur par image.
if !(_p isEqualType [] && {(count _p) >= 2} && {(_p select 0) isEqualType 0} && {(_p select 1) isEqualType 0} && {_R isEqualType 0}) exitWith { [] };
private _cellM = 64;
private _cache = uiNamespace getVariable "COMSPEC_ATAK_ZnSnapC";
if (isNil "_cache" || {(count _cache) > 300}) then { _cache = createHashMap; uiNamespace setVariable ["COMSPEC_ATAK_ZnSnapC", _cache]; };
// Case de 64 m : [segments [[a, b, genre]...], coins [[p, genre]...]].
private _cell = {
    params ["_i", "_j"];
    private _k = format ["%1|%2", _i, _j];
    // Attention : SQF ne distingue pas la casse, _c ici et _C auraient été la même variable (d'où _cellM).
    private _c = _cache getOrDefault [_k, []];
    if ((count _c) isEqualTo 2) exitWith { _c };
    private _ctr = [(_i + 0.5) * _cellM, (_j + 0.5) * _cellM, 0];
    private _segs = [];
    private _cor = [];
    {
        private _inf = getRoadInfo _x;
        private _b = _inf param [6, []]; private _e = _inf param [7, []];
        if ((count _b) >= 2 && {(count _e) >= 2}) then { _segs pushBack [[_b select 0, _b select 1], [_e select 0, _e select 1], "ROAD"]; };
    } forEach (_ctr nearRoads (_cellM * 0.75));
    {
        if (!isObjectHidden _x) then {
            (boundingBoxReal _x) params ["_p1", "_p2"];
            private _x0 = (_p1 select 0) min (_p2 select 0); private _x1 = (_p1 select 0) max (_p2 select 0);
            private _y0 = (_p1 select 1) min (_p2 select 1); private _y1 = (_p1 select 1) max (_p2 select 1);
            if ((_x1 - _x0) > 2 && {(_y1 - _y0) > 2} && {(_x1 - _x0) < 200} && {(_y1 - _y0) < 200}) then {
                // Coins dans le monde (repère de l'objet : cap et position du bâtiment).
                private _q = [];
                private _o = _x;
                { _q pushBack ((_o modelToWorld _x) select [0, 2]); } forEach [[_x0, _y0, 0], [_x1, _y0, 0], [_x1, _y1, 0], [_x0, _y1, 0]];
                { _cor pushBack [_x, "CORNER"]; } forEach _q;
                for "_n" from 0 to 3 do { _segs pushBack [_q select _n, _q select ((_n + 1) mod 4), "EDGE"]; };
            };
        };
    } forEach (nearestObjects [_ctr, ["House", "Building"], _cellM * 0.75, true]);
    _c = [_segs, _cor];
    _cache set [_k, _c];
    _c
};
_p params ["_px", "_py"];
private _best = []; private _bd = _R;
// Coins d'abord (prioritaires dans 60 % du rayon), puis bords et routes.
private _cr = _R * 0.6;
private _bestC = []; private _bdc = _cr;
for "_i" from (floor ((_px - _R) / _cellM)) to (floor ((_px + _R) / _cellM)) do {
    for "_j" from (floor ((_py - _R) / _cellM)) to (floor ((_py + _R) / _cellM)) do {
        ([_i, _j] call _cell) params ["_segs", "_cor"];
        {
            private _d = (_x select 0) distance2D _p;
            if (_d < _bdc) then { _bdc = _d; _bestC = [_x select 0, "CORNER"]; };
        } forEach _cor;
        {
            _x params ["_a", "_b", "_k"];
            private _ab = [(_b select 0) - (_a select 0), (_b select 1) - (_a select 1)];
            private _l2 = ((_ab select 0) ^ 2) + ((_ab select 1) ^ 2);
            if (_l2 > 0.01) then {
                private _t = ((((_px - (_a select 0)) * (_ab select 0)) + ((_py - (_a select 1)) * (_ab select 1))) / _l2) max 0 min 1;
                private _q = [(_a select 0) + (_ab select 0) * _t, (_a select 1) + (_ab select 1) * _t];
                private _d = _q distance2D _p;
                if (_d < _bd) then { _bd = _d; _best = [_q, _k]; };
            };
        } forEach _segs;
    };
};
if ((count _bestC) > 0) exitWith { _bestC };
if ((count _best) > 0) exitWith { _best };
// Rive : la terre et l'eau changent entre le curseur et le rayon dans une des 12 directions.
private _w0 = surfaceIsWater [_px, _py];
private _sh = []; private _bs = _R;
for "_k" from 0 to 11 do {
    private _dir = _k * 30;
    private _far = [_px + _R * (sin _dir), _py + _R * (cos _dir)];
    if ((surfaceIsWater _far) isNotEqualTo _w0) then {
        private _lo = 0; private _hi = _R;
        for "_n" from 1 to 6 do {
            private _m = (_lo + _hi) / 2;
            if ((surfaceIsWater [_px + _m * (sin _dir), _py + _m * (cos _dir)]) isEqualTo _w0) then { _lo = _m; } else { _hi = _m; };
        };
        private _d = (_lo + _hi) / 2;
        if (_d < _bs) then { _bs = _d; _sh = [[_px + _d * (sin _dir), _py + _d * (cos _dir)], "SHORE"]; };
    };
};
_sh
