params [["_settings", []]];

private _x = _settings param [0, 0];
private _y = _settings param [1, 0];
private _z = _settings param [2, 0.05];

if (_settings isEqualTo []) exitWith {
    [_x, _y, _z, 2, 1, 1500, 0, 0, "surface", 0, "normal"]
};

if ((count _settings) <= 5) exitWith {
    private _size = (_settings param [3, 1]) max 0.05;
    private _pipDistance = (_settings param [4, 1500]) max 100;
    [_x, _y, _z, _size, _size, _pipDistance, 0, 0, "surface", 0, "normal"]
};

if ((count _settings) == 6) exitWith {
    private _width = (_settings param [3, 1]) max 0.05;
    private _height = (_settings param [4, 1]) max 0.05;
    private _pipDistance = (_settings param [5, 1500]) max 100;
    [_x, _y, _z, _width, _height, _pipDistance, 0, 0, "surface", 0, "normal"]
};

if ((count _settings) >= 10 && {typeName (_settings param [8, "surface"]) == "STRING"}) exitWith {
    private _width = (_settings param [3, 1]) max 0.05;
    private _height = (_settings param [4, 1]) max 0.05;
    private _pipDistance = (_settings param [5, 1500]) max 100;
    private _pitch = _settings param [6, 0];
    private _roll = _settings param [7, 0];
    private _mode = toLower (_settings param [8, "surface"]);
    private _surfaceIndex = (floor (_settings param [9, 0])) max 0;
    private _vision = toLower (_settings param [10, "normal"]);
    if !(_mode in ["surface", "panel"]) then {
        _mode = "surface";
    };
    if !(_vision in ["normal", "nv", "thermal", "thermal_whot", "thermal_bhot", "a3ti_whot", "a3ti_bhot", "a3ti_current"]) then {
        _vision = "normal";
    };
    [_x, _y, _z, _width, _height, _pipDistance, _pitch, _roll, _mode, _surfaceIndex, _vision]
};

private _scale = (_settings param [3, 2]) max 0.05;
private _aspectW = (_settings param [4, 16]) max 0.05;
private _aspectH = (_settings param [5, 9]) max 0.05;
private _pipDistance = (_settings param [6, 1500]) max 100;
private _width = _scale;
private _height = _scale * (_aspectH / _aspectW);
private _pitch = _settings param [8, 0];
private _roll = _settings param [9, 0];

[_x, _y, _z, _width, _height, _pipDistance, _pitch, _roll, "surface", 0, "normal"]
