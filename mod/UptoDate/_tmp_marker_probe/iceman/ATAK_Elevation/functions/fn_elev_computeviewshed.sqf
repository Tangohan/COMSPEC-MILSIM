#include "..\script_component.hpp"

params ["_center", "_heightFt", "_radiusM"];

private _heightM = (1 max _heightFt) * 0.3048;
private _radius = 100 max (_radiusM min 3000);
private _bearingStep = [8, 5] select (_radius <= 800);
private _rangeStep = ((_radius / 36) max 15) min 40;
private _observerASL = (getTerrainHeightASL _center) + _heightM;
private _segments = [];
private _visibleCount = 0;
private _deadCount = 0;

for "_bearing" from 0 to 359 step _bearingStep do {
    private _maxAngle = -1e9;
    private _halfBearing = _bearingStep / 2;
    for "_dist" from _rangeStep to _radius step _rangeStep do {
        private _pos = _center getPos [_dist, _bearing];
        private _inner = 0 max (_dist - _rangeStep);
        private _outer = _radius min _dist;
        private _targetASL = (getTerrainHeightASL _pos) + 1.5;
        private _angle = (_targetASL - _observerASL) / _dist;
        private _visible = _angle >= (_maxAngle - 0.002);
        if (_angle > _maxAngle) then {_maxAngle = _angle};

        if (_visible) then {
            _visibleCount = _visibleCount + 1;
        } else {
            _deadCount = _deadCount + 1;
        };

        if (!surfaceIsWater _pos) then {
            private _left = _bearing - _halfBearing;
            private _right = _bearing + _halfBearing;
            if (_visible) then {
                private _flatten = {
                    params ["_p"];
                    [_p # 0, _p # 1, 0]
                };
                private _points = if (_inner <= 0) then {
                    [
                        [_center # 0, _center # 1, 0],
                        [_center getPos [_outer, _left]] call _flatten,
                        [_center getPos [_outer, _right]] call _flatten
                    ]
                } else {
                    [
                        [_center getPos [_inner, _left]] call _flatten,
                        [_center getPos [_outer, _left]] call _flatten,
                        [_center getPos [_outer, _right]] call _flatten,
                        [_center getPos [_inner, _right]] call _flatten
                    ]
                };
                _segments pushBack _points;
            };
        };
    };
    if (canSuspend && {(_bearing mod 48) == 0}) then {
        uiSleep 0.001;
    };
};

[_center, _radius, _segments, _visibleCount, _deadCount]
