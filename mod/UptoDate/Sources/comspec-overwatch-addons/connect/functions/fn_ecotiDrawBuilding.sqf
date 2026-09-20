/*
    Silhouette du bâtiment marqué — empreinte échantillonnée + étages.
    Mode découpage : coupe au plafond de l’étage choisi (pas d’ouverture réelle des murs).
    Params: [_building, _drawFloorLabel] — le libellé « Étage » est en général dessiné
    une seule fois par fn_ecotiDraw (badge unique) ; laisser false pour éviter le doublon.
*/
params [
    ["_building", objNull, [objNull]],
    ["_drawFloorLabel", false, [true]]
];
if (isNull _building) exitWith {};

private _bb = boundingBoxReal _building;
if (!(_bb isEqualType []) || {(count _bb) < 2}) exitWith {};
private _min = _bb select 0;
private _max = _bb select 1;
if (!(_min isEqualType []) || {!(_max isEqualType [])}) exitWith {};
if ((count _min) < 3 || {(count _max) < 3}) exitWith {};

private _z0 = _min select 2;
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

private _theme = [] call comspec_overwatch_connect_fnc_ecotiThemeColors;
private _col = if (_cutaway) then {
    _theme getOrDefault ["building", [0.45, 1, 0.65, 0.95]]
} else {
    private _c = _theme getOrDefault ["building", [0.35, 0.95, 0.55, 0.85]];
    private _soft = +_c;
    _soft set [3, ((_c select 3) * 0.85) min 0.9];
    _soft
};
private _floorColSel = _theme getOrDefault ["floor", [1, 0.92, 0.25, 0.9]];
private _floorCol = +_col;
_floorCol set [3, ((_col select 3) * 0.5) min 0.55];

private _ring = [_building, false] call comspec_overwatch_connect_fnc_ecotiRefreshBuildingFootprint;
if ((count _ring) < 3) exitWith {
    [_building, _col] call comspec_overwatch_connect_fnc_ecotiDrawOutline;
};

private _fnc_ringAt = {
    params ["_z"];
    _ring apply { _building modelToWorldVisual [_x select 0, _x select 1, _z] }
};

private _bot = [_z0] call _fnc_ringAt;
private _top = [_zTop] call _fnc_ringAt;
private _n = (count _bot) min (count _top);
if (_n < 3) exitWith {
    [_building, _col] call comspec_overwatch_connect_fnc_ecotiDrawOutline;
};

for "_i" from 0 to (_n - 1) do {
    private _j = (_i + 1) mod _n;
    drawLine3D [_bot select _i, _bot select _j, _col];
    drawLine3D [_top select _i, _top select _j, _col];
    drawLine3D [_bot select _i, _top select _i, _col];
};

private _lastFloor = if (_cutaway) then { (_sel + 1) min _floors } else { _floors - 1 };
if (_floors > 1 || {_cutaway}) then {
    for "_i" from 1 to _lastFloor do {
        private _t = _i / _floors;
        private _z = _z0 + (_h * _t);
        private _isSel = _cutaway && {_i == _lastFloor};
        private _fCol = if (_isSel) then { _floorColSel } else { _floorCol };
        private _pts = [_z] call _fnc_ringAt;
        private _pn = count _pts;
        if (_pn < 3) then { continue };
        for "_k" from 0 to (_pn - 1) do {
            drawLine3D [_pts select _k, _pts select ((_k + 1) mod _pn), _fCol];
        };
        if (_isSel && {_pn >= 4}) then {
            drawLine3D [_pts select 0, _pts select (floor (_pn / 2)), _fCol];
            drawLine3D [_pts select (floor (_pn / 4)), _pts select (floor ((3 * _pn) / 4)), _fCol];
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
    drawIcon3D [_icon, _floorColSel, _world, 0.35, 0.35, 0, "", 0, 0.02, "PuristaMedium"];
    _drawn = _drawn + 1;
} forEach _allPos;

private _badgePos = _building modelToWorldVisual [0, 0, (_zBandLo + _zTop) / 2];
if (_drawFloorLabel) then {
    drawIcon3D [
        "\a3\ui_f\data\map\mapcontrol\Bunker_CA.paa",
        _floorColSel,
        _badgePos,
        0.45,
        0.45,
        0,
        format ["Étage %1/%2", _sel + 1, _floors],
        0,
        0.026,
        "PuristaMedium"
    ];
};
