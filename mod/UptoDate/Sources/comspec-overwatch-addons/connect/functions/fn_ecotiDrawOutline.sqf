/*
    Contour (cadre orienté) d’un objet sous le regard — approximation ECOTI.
*/
params [["_obj", objNull, [objNull]], ["_col", [0.95, 0.85, 0.2, 0.75], [[]]]];
if (isNull _obj) exitWith {};

private _bb = boundingBoxReal _obj;
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

private _corners = [
    [_x0, _y0, _z0], [_x1, _y0, _z0], [_x1, _y1, _z0], [_x0, _y1, _z0],
    [_x0, _y0, _z1], [_x1, _y0, _z1], [_x1, _y1, _z1], [_x0, _y1, _z1]
] apply { _obj modelToWorldVisual _x };

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
