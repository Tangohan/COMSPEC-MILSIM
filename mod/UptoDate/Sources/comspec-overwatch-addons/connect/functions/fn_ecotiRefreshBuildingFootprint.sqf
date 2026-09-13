/*
    Calcule un polygone d’empreinte plus collé à la géométrie (échantillonnage radial)
    qu’un simple cadre AABB Eden. Cache missionNamespace.
    Params: [_building, _force]
*/
params [["_building", objNull, [objNull]], ["_force", false, [true]]];
if (isNull _building) exitWith { [] };

private _cachedObj = missionNamespace getVariable ["COMSPEC_EcotiFootprintObj", objNull];
private _cachedAt = missionNamespace getVariable ["COMSPEC_EcotiFootprintAt", -1];
private _cached = missionNamespace getVariable ["COMSPEC_EcotiFootprintRing", []];
if (
    !_force
    && {_cachedObj isEqualTo _building}
    && {_cached isEqualType []}
    && {(count _cached) >= 6}
    && {(time - _cachedAt) < 2.5}
) exitWith { _cached };

private _bb = boundingBoxReal _building;
if (!(_bb isEqualType []) || {(count _bb) < 2}) exitWith { [] };
private _min = _bb select 0;
private _max = _bb select 1;
if ((count _min) < 3 || {(count _max) < 3}) exitWith { [] };

private _x0 = _min select 0;
private _y0 = _min select 1;
private _z0 = _min select 2;
private _x1 = _max select 0;
private _y1 = _max select 1;
private _z1 = _max select 2;
private _cx = (_x0 + _x1) / 2;
private _cy = (_y0 + _y1) / 2;
private _zx = abs (_x1 - _x0);
private _zy = abs (_y1 - _y0);
private _radius = ((_zx max _zy) * 0.72) + 1.5;
private _sampleZ = _z0 + (((_z1 - _z0) max 1.2) * 0.35);

private _ringLocal = [];
private _sectors = 20;
for "_i" from 0 to (_sectors - 1) do {
    private _a = (_i / _sectors) * 360;
    private _dx = (cos _a) * _radius;
    private _dy = (sin _a) * _radius;
    private _fromLocal = [_cx + _dx, _cy + _dy, _sampleZ];
    private _toLocal = [_cx, _cy, _sampleZ];
    private _from = AGLToASL (_building modelToWorldVisual _fromLocal);
    private _to = AGLToASL (_building modelToWorldVisual _toLocal);
    private _hits = lineIntersectsSurfaces [_from, _to, player, objNull, true, 3, "GEOM", "NONE"];
    private _hitLocal = [];
    {
        private _obj = _x select 2;
        if (!isNull _obj && {_obj isEqualTo _building || {_building in (objectParent _obj)}}) exitWith {
            _hitLocal = _building worldToModel (ASLToAGL (_x select 0));
        };
        if (!isNull _obj && {_obj isKindOf "House" || {_obj isKindOf "Building"}}) then {
            if ((objectParent _obj) isEqualTo _building || {_obj == _building}) then {
                _hitLocal = _building worldToModel (ASLToAGL (_x select 0));
            };
        };
    } forEach _hits;

    if ((count _hitLocal) >= 2) then {
        _ringLocal pushBack [_hitLocal select 0, _hitLocal select 1, 0];
    } else {
        // Repli : point sur le cadre AABB (mieux que rien).
        private _fx = _cx + ((cos _a) * (_zx * 0.48));
        private _fy = _cy + ((sin _a) * (_zy * 0.48));
        _ringLocal pushBack [_fx, _fy, 0];
    };
};

// Enrichit avec buildingPos (coin intérieur souvent plus juste).
private _bps = _building buildingPos -1;
if (_bps isEqualType []) then {
    {
        if (_x isEqualType [] && {(count _x) >= 2}) then {
            private _lp = _building worldToModel _x;
            _ringLocal pushBack [_lp select 0, _lp select 1, 0];
        };
    } forEach _bps;
};

// Convex hull 2D approximatif : pour chaque angle, point le plus éloigné du centre.
private _hull = [];
for "_i" from 0 to (_sectors - 1) do {
    private _a = (_i / _sectors) * 360;
    private _best = [];
    private _bestD = -1;
    {
        private _px = _x select 0;
        private _py = _x select 1;
        private _ang = (_py - _cy) atan2 (_px - _cx);
        if (_ang < 0) then { _ang = _ang + 360 };
        private _diff = abs (_ang - _a);
        if (_diff > 180) then { _diff = 360 - _diff };
        if (_diff <= (180 / _sectors) + 2) then {
            private _d = (_px - _cx) * (_px - _cx) + (_py - _cy) * (_py - _cy);
            if (_d > _bestD) then {
                _bestD = _d;
                _best = [_px, _py, 0];
            };
        };
    } forEach _ringLocal;
    if ((count _best) >= 2) then {
        _hull pushBack _best;
    };
};

if ((count _hull) < 4) then {
    _hull = [
        [_x0, _y0, 0], [_x1, _y0, 0], [_x1, _y1, 0], [_x0, _y1, 0]
    ];
};

missionNamespace setVariable ["COMSPEC_EcotiFootprintObj", _building, false];
missionNamespace setVariable ["COMSPEC_EcotiFootprintAt", time, false];
missionNamespace setVariable ["COMSPEC_EcotiFootprintRing", _hull, false];
missionNamespace setVariable ["COMSPEC_EcotiFootprintZ0", _z0, false];
missionNamespace setVariable ["COMSPEC_EcotiFootprintZ1", _z1, false];
_hull
