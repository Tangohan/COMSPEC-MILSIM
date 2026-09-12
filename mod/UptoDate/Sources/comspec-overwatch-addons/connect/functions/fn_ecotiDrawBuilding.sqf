/*
    Silhouette du bâtiment marqué.
    Mode découpage (si activé) : coupe au plafond de l’étage choisi,
    dalles d’étage, marqueurs intérieurs — pas d’ouverture réelle des murs.
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

private _floors = (round (_h / 3)) max 1;
_floors = _floors min 8;
missionNamespace setVariable ["COMSPEC_EcotiCutawayFloorCount", _floors, false];

private _cutaway = missionNamespace getVariable ["comspec_overwatch_ecoti_building_cutaway", false];
if (!(_cutaway isEqualType true)) then { _cutaway = false; };
if (_cutaway && {!([] call comspec_overwatch_connect_fnc_ecotiIsAvailable)}) then {
    _cutaway = false;
};

private _sel = missionNamespace getVariable ["COMSPEC_EcotiCutawayFloor", 0];
if (!(_sel isEqualType 0)) then { _sel = 0; };
_sel = (round _sel) max 0 min (_floors - 1);

private _zTop = if (_cutaway) then {
    _z0 + (_h * ((_sel + 1) / _floors))
} else {
    _z1
};

private _corners = [
    [_x0, _y0, _z0], [_x1, _y0, _z0], [_x1, _y1, _z0], [_x0, _y1, _z0],
    [_x0, _y0, _zTop], [_x1, _y0, _zTop], [_x1, _y1, _zTop], [_x0, _y1, _zTop]
] apply { _building modelToWorldVisual _x };

private _col = if (_cutaway) then {
    [0.45, 1, 0.65, 0.95]
} else {
    [0.35, 0.95, 0.55, 0.85]
};
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

private _lastFloor = if (_cutaway) then { _sel } else { _floors - 1 };
if (_floors > 1 || {_cutaway}) then {
    for "_i" from 1 to _lastFloor do {
        private _t = _i / _floors;
        private _z = _z0 + (_h * _t);
        private _isSel = _cutaway && {_i == (_sel + 1)};
        private _floorCol = if (_isSel) then {
            [1, 0.92, 0.25, 0.9]
        } else {
            [0.35, 0.95, 0.55, 0.45]
        };
        private _ring = [
            [_x0, _y0, _z], [_x1, _y0, _z], [_x1, _y1, _z], [_x0, _y1, _z]
        ] apply { _building modelToWorldVisual _x };
        drawLine3D [_ring select 0, _ring select 1, _floorCol];
        drawLine3D [_ring select 1, _ring select 2, _floorCol];
        drawLine3D [_ring select 2, _ring select 3, _floorCol];
        drawLine3D [_ring select 3, _ring select 0, _floorCol];
        if (_isSel) then {
            drawLine3D [_ring select 0, _ring select 2, _floorCol];
            drawLine3D [_ring select 1, _ring select 3, _floorCol];
        };
    };
};

if (!_cutaway) exitWith {};

private _zBandLo = _z0 + (_h * (_sel / _floors));
private _zBandHi = _zTop;
private _allPos = _building buildingPos -1;
if (!(_allPos isEqualType [])) then { _allPos = []; };
private _icon = "\a3\ui_f\data\map\markers\military\dot_CA.paa";
private _drawn = 0;
{
    if (_drawn >= 12) exitWith {};
    private _wp = _x;
    if (!(_wp isEqualType []) || {(count _wp) < 3}) then { continue };
    private _local = _building worldToModel _wp;
    private _lz = _local select 2;
    if (_lz < (_zBandLo - 0.4) || {_lz > (_zBandHi + 0.6)}) then { continue };
    private _world = ASLToAGL (AGLToASL _wp);
    drawIcon3D [_icon, [1, 0.85, 0.2, 0.85], _world, 0.35, 0.35, 0, "", 0, 0.02, "PuristaMedium"];
    _drawn = _drawn + 1;
} forEach _allPos;

private _badgePos = _building modelToWorldVisual [0, 0, (_zBandLo + _zTop) / 2];
drawIcon3D [
    "\a3\ui_f\data\map\mapcontrol\Bunker_CA.paa",
    [1, 0.9, 0.3, 0.95],
    _badgePos,
    0.55,
    0.55,
    0,
    format ["Étage %1/%2", _sel + 1, _floors],
    1,
    0.028,
    "PuristaMedium"
];
