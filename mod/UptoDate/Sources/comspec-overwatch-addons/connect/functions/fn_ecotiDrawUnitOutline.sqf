/*
    Contour / surbrillance d’une personne (capsule approximative, pas un mesh).
    Params: [_unit, _color]
*/
params [["_unit", objNull, [objNull]], ["_color", [0.8, 1, 0.95, 0.95], [[]]]];
if (isNull _unit || {!alive _unit}) exitWith {};

_color = [_color] call comspec_overwatch_connect_fnc_ecotiNormalizeColor;

private _bb = boundingBoxReal _unit;
if (!(_bb isEqualType []) || {(count _bb) < 2}) exitWith {};
private _min = _bb select 0;
private _max = _bb select 1;
if ((count _min) < 3 || {(count _max) < 3}) exitWith {};

// Boîte un peu resserrée pour coller au corps plutôt qu’à l’armement.
private _x0 = (_min select 0) * 0.55;
private _y0 = (_min select 1) * 0.55;
private _z0 = (_min select 2) max -0.1;
private _x1 = (_max select 0) * 0.55;
private _y1 = (_max select 1) * 0.55;
private _z1 = (_max select 2) min 1.95;

private _corners = [
    [_x0, _y0, _z0], [_x1, _y0, _z0], [_x1, _y1, _z0], [_x0, _y1, _z0],
    [_x0, _y0, _z1], [_x1, _y0, _z1], [_x1, _y1, _z1], [_x0, _y1, _z1]
] apply { _unit modelToWorldVisual _x };

private _soft = +_color;
_soft set [3, ((_color select 3) * 0.55) min 0.7];

drawLine3D [_corners select 0, _corners select 1, _color];
drawLine3D [_corners select 1, _corners select 2, _color];
drawLine3D [_corners select 2, _corners select 3, _color];
drawLine3D [_corners select 3, _corners select 0, _color];
drawLine3D [_corners select 4, _corners select 5, _color];
drawLine3D [_corners select 5, _corners select 6, _color];
drawLine3D [_corners select 6, _corners select 7, _color];
drawLine3D [_corners select 7, _corners select 4, _color];
drawLine3D [_corners select 0, _corners select 4, _soft];
drawLine3D [_corners select 1, _corners select 5, _soft];
drawLine3D [_corners select 2, _corners select 6, _soft];
drawLine3D [_corners select 3, _corners select 7, _soft];

// Croix verticale centrale (surbrillance silhouette).
private _midLo = _unit modelToWorldVisual [0, 0, _z0 + 0.05];
private _midHi = _unit modelToWorldVisual [0, 0, _z1];
drawLine3D [_midLo, _midHi, _color];
