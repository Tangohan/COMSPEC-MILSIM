#include "..\script_component.hpp"

params ["_grid"];

_grid = (_grid splitString " -_" joinString "");
private _length = count _grid;
if (!(_length in [6, 8, 10])) exitWith {[]};

private _digits = toArray _grid;
if (_digits findIf {_x < 48 || {_x > 57}} > -1) exitWith {[]};

private _half = _length / 2;
private _east = _grid select [0, _half];
private _north = _grid select [_half, _half];
while {count _east < 5} do {_east = _east + "5"};
while {count _north < 5} do {_north = _north + "5"};

private _x = parseNumber _east;
private _y = parseNumber _north;
if (_x < 0 || {_y < 0} || {_x > worldSize} || {_y > worldSize}) exitWith {[]};

[_x, _y, 0]
