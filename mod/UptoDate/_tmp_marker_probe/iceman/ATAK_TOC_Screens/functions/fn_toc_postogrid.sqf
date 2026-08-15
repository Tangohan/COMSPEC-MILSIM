params [["_pos", [0,0,0]], ["_gridSize", 8]];

private _gridSizes = [6,8,10];
private _divisors = [100,10,1];
private _idx = _gridSizes find _gridSize;
if (_idx < 0) then {
    _idx = 1;
};

private _divisor = _divisors # _idx;
private _gridResolution = _gridSize / 2;
private _posX = str (floor ((_pos # 0) / _divisor));
private _posY = str (floor ((_pos # 1) / _divisor));

while {count _posX < _gridResolution} do {
    _posX = "0" + _posX;
};
while {count _posY < _gridResolution} do {
    _posY = "0" + _posY;
};

format ["%1%2", _posX, _posY]
