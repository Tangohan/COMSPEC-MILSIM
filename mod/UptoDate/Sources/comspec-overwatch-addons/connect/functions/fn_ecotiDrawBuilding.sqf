/*
    Wireframe du bâtiment marqué (BB orienté + plans d’étage estimés).
*/
params [["_building", objNull, [objNull]]];
if (isNull _building) exitWith {};

private _bb = boundingBoxReal _building;
if (!(_bb isEqualType []) || {(count _bb) < 2}) exitWith {};
private _min = _bb select 0;
private _max = _bb select 1;
if (!(_min isEqualType []) || {!(_max isEqualType [])}) exitWith {};
if ((count _min) < 3 || {(count _max) < 3}) exitWith {};

private _x0 = _min select 0;
private _y0 = _min select 1;
private _z0 = _min select 2;
private _x1 = _max select 0;
private _y1 = _max select 1;
private _z1 = _max select 2;
private _h = abs (_z1 - _z0);
if (_h < 1.5) exitWith {};

private _corners = [
    [_x0, _y0, _z0], [_x1, _y0, _z0], [_x1, _y1, _z0], [_x0, _y1, _z0],
    [_x0, _y0, _z1], [_x1, _y0, _z1], [_x1, _y1, _z1], [_x0, _y1, _z1]
] apply { _building modelToWorldVisual _x };

private _col = [0.35, 0.95, 0.55, 0.85];
drawLine3D [_corners select 0, _corners select 1, _col];
drawLine3D [_corners select 1, _corners select 2, _col];
drawLine3D [_corners select 2, _corners select 3, _col];
drawLine3D [_corners select 3, _corners select 0, _col];
drawLine3D [_corners select 4, _corners select 5, _col];
drawLine3D [_corners select 5, _corners select 6, _col];
drawLine3D [_corners select 6, _corners select 7, _col];
drawLine3D [_corners select 7, _corners select 4, _col];
drawLine3D [_corners select 0, _corners select 4, _col];
drawLine3D [_corners select 1, _corners select 5, _col];
drawLine3D [_corners select 2, _corners select 6, _col];
drawLine3D [_corners select 3, _corners select 7, _col];

private _floors = (round (_h / 3)) max 1;
_floors = _floors min 8;
if (_floors > 1) then {
    private _floorCol = [0.35, 0.95, 0.55, 0.45];
    for "_i" from 1 to (_floors - 1) do {
        private _t = _i / _floors;
        private _z = _z0 + (_h * _t);
        private _ring = [
            [_x0, _y0, _z], [_x1, _y0, _z], [_x1, _y1, _z], [_x0, _y1, _z]
        ] apply { _building modelToWorldVisual _x };
        drawLine3D [_ring select 0, _ring select 1, _floorCol];
        drawLine3D [_ring select 1, _ring select 2, _floorCol];
        drawLine3D [_ring select 2, _ring select 3, _floorCol];
        drawLine3D [_ring select 3, _ring select 0, _floorCol];
    };
};
