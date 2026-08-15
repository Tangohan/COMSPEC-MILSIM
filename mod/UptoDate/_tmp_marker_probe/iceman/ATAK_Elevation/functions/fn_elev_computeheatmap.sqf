#include "..\script_component.hpp"

params ["_center", "_sizeM", "_sampleM"];

private _size = 250 max (_sizeM min 5000);
private _sample = 25 max (_sampleM min 250);
private _half = _size / 2;
private _raw = [];
private _minH = 1e9;
private _maxH = -1e9;

for "_x" from -_half to _half step _sample do {
    for "_y" from -_half to _half step _sample do {
        private _pos = [(_center # 0) + _x, (_center # 1) + _y, 0];
        if ((_pos # 0) >= 0 && {(_pos # 1) >= 0} && {(_pos # 0) <= worldSize} && {(_pos # 1) <= worldSize} && {!surfaceIsWater _pos}) then {
            private _h = getTerrainHeightASL _pos;
            _minH = _minH min _h;
            _maxH = _maxH max _h;
            _raw pushBack [_pos, _h];
        };
    };
    if (canSuspend) then {
        uiSleep 0.001;
    };
};

private _range = (_maxH - _minH) max 1;
private _cells = [];
{
    _x params ["_pos", "_h"];
    private _t = ((_h - _minH) / _range) max 0 min 1;
    private _color = if (_t < 0.25) then {
        [0.05, 0.2 + (_t * 2.0), 1, 0.46]
    } else {
        if (_t < 0.5) then {
            [0.05, 0.75, 1 - ((_t - 0.25) * 2.6), 0.46]
        } else {
            if (_t < 0.75) then {
                [(_t - 0.5) * 3.2, 0.85, 0.08, 0.46]
            } else {
                [0.85 + ((_t - 0.75) * 0.6), 0.85 - ((_t - 0.75) * 2.8), 0.05, 0.48]
            };
        };
    };
    _cells pushBack [_pos, _color, _sample];
} forEach _raw;

[_cells, _minH, _maxH]
