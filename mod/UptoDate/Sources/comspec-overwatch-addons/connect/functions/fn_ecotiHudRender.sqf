/*
    Rend la file de pastilles en 2D écran (worldToScreen + contrôles).
    Losange / icône, ombre portée, fondu distance. Pas de plaque pleine par défaut.
    Params: [_queue] — liste [_pos, _icon, _color, _label, _dist, _iconSize, _compact]
*/
params [["_queue", [], [[]]]];

if (!hasInterface) exitWith {};
if (_queue isEqualTo []) exitWith {
    private _dispKeep = uiNamespace getVariable ["COMSPEC_EcotiHudDisp", displayNull];
    if (isNull _dispKeep) exitWith {};
    private _prev = uiNamespace getVariable ["COMSPEC_EcotiHudSlotCount", 0];
    if (!(_prev isEqualType 0)) then { _prev = 0; };
    for "_i" from 0 to (_prev - 1) do {
        private _base = 77500 + (_i * 4);
        {
            private _c = _dispKeep displayCtrl _x;
            if (!isNull _c) then { _c ctrlShow false; _c ctrlCommit 0; };
        } forEach [_base, _base + 1, _base + 2, _base + 3];
    };
    uiNamespace setVariable ["COMSPEC_EcotiHudSlotCount", 0];
};

private _disp = [] call comspec_overwatch_connect_fnc_ecotiHudEnsure;
if (isNull _disp) exitWith {};
missionNamespace setVariable ["COMSPEC_EcotiHudLayerOn", true, false];

private _maxDist = missionNamespace getVariable ["comspec_overwatch_ecoti_max_dist", 1200];
private _theme = [] call comspec_overwatch_connect_fnc_ecotiThemeColors;
private _txtColBase = _theme getOrDefault ["badge", [1, 1, 1, 1]];
private _showPlate = missionNamespace getVariable ["comspec_overwatch_ecoti_badge_plate", false];
if (!(_showPlate isEqualType true)) then { _showPlate = false; };

private _prevSlots = uiNamespace getVariable ["COMSPEC_EcotiHudSlotCount", 0];
if (!(_prevSlots isEqualType 0)) then { _prevSlots = 0; };

_queue = [_queue, [], { _x select 4 }, "ASCEND"] call BIS_fnc_sortBy;

private _boxes = [];
private _used = 0;
private _bhFull = 0.026 * safeZoneH;
private _bhDot = 0.018 * safeZoneH;
private _gap = 0.004 * safeZoneH;
private _iconW = 0.016 * safeZoneW;

private _fnc_getCtrls = {
    params ["_idx"];
    private _base = 77500 + (_idx * 4);
    private _bg = _disp displayCtrl _base;
    private _sh = _disp displayCtrl (_base + 1);
    private _pic = _disp displayCtrl (_base + 2);
    private _txt = _disp displayCtrl (_base + 3);
    if (isNull _bg) then {
        _bg = _disp ctrlCreate ["RscText", _base];
        _sh = _disp ctrlCreate ["RscText", _base + 1];
        _pic = _disp ctrlCreate ["RscPictureKeepAspect", _base + 2];
        _txt = _disp ctrlCreate ["RscText", _base + 3];
        _sh ctrlSetFont "PuristaMedium";
        _txt ctrlSetFont "PuristaMedium";
        _sh ctrlSetFontHeight (0.022 * safeZoneH);
        _txt ctrlSetFontHeight (0.022 * safeZoneH);
    };
    [_bg, _sh, _pic, _txt]
};

{
    if (_used >= 40) exitWith {};
    _x params ["_pos", "_ic", "_col", "_label", "_dist", "_isz", ["_compact", false]];
    if (!(_pos isEqualType []) || {(count _pos) < 3}) then { continue };

    private _scr = worldToScreen _pos;
    if (!(_scr isEqualType []) || {(count _scr) < 2}) then { continue };
    private _sx = _scr select 0;
    private _sy = _scr select 1;
    if (_sx < -0.02 || {_sx > 1.02} || {_sy < -0.02} || {_sy > 1.02}) then { continue };

    private _fadeStart = (_maxDist * 0.4) max 80;
    private _alpha = if (_dist <= _fadeStart) then {
        0.95
    } else {
        (0.95 - (0.67 * ((_dist - _fadeStart) / ((_maxDist - _fadeStart) max 1)))) max 0.28
    };
    _col = [_col] call comspec_overwatch_connect_fnc_ecotiNormalizeColor;
    private _iconCol = [
        _col select 0,
        _col select 1,
        _col select 2,
        ((_col select 3) * _alpha) min 1
    ];
    private _txtCol = [
        _txtColBase select 0,
        _txtColBase select 1,
        _txtColBase select 2,
        ((_txtColBase select 3) * _alpha) min 1
    ];

    private _name = trim _label;
    if (_name isEqualTo "") then { _name = "Repère"; };
    private _brPar = _name find " (";
    if (_brPar > 0) then {
        private _tailP = _name select [_brPar + 2];
        private _closeP = _tailP find ")";
        if (_closeP > 0) then {
            private _innerP = _tailP select [0, _closeP];
            if (_innerP isEqualTo (str (parseNumber _innerP)) && {(parseNumber _innerP) > 0}) then {
                _name = trim (_name select [0, _brPar]);
            };
        };
    };
    if ((count _name) > 20) then { _name = (_name select [0, 18]) + "…"; };
    private _distTxt = [_dist] call comspec_overwatch_connect_fnc_ecotiFormatDistance;
    private _line = format ["%1 · %2", _name, _distTxt];

    if (_ic isEqualTo "" || {_ic find "military\dot_CA" >= 0}) then {
        _ic = [] call comspec_overwatch_connect_fnc_ecotiIconPath;
    };

    private _txtW = (((count _line) * 0.0062 * safeZoneW) + 0.01 * safeZoneW) min (0.2 * safeZoneW);
    private _bw = _iconW + 0.006 * safeZoneW + _txtW;
    private _bh = if (_compact) then { _bhDot } else { _bhFull };
    private _px = safeZoneX + (_sx * safeZoneW) - (_iconW * 0.5);
    private _py = safeZoneY + (_sy * safeZoneH) - (_bh * 0.5);

    private _guard = 0;
    while { _guard < 14 } do {
        private _hit = false;
        private _pushY = _py;
        {
            _x params ["_ox", "_oy", "_ow", "_oh"];
            if (
                _px < (_ox + _ow)
                && {(_px + _bw) > _ox}
                && {_py < (_oy + _oh + _gap)}
                && {(_py + _bh) > (_oy - _gap)}
            ) then {
                _hit = true;
                _pushY = (_oy + _oh + _gap) max _pushY;
            };
        } forEach _boxes;
        if (!_hit) exitWith {};
        _py = _pushY;
        _guard = _guard + 1;
    };

    if (_py > (safeZoneY + safeZoneH * 0.88)) then {
        _compact = true;
        _bh = _bhDot;
        _bw = _iconW * 1.15;
    };

    _boxes pushBack [_px, _py, _bw, _bh];
    ([_used] call _fnc_getCtrls) params ["_bg", "_sh", "_pic", "_txt"];

    if (_iconW < 0.001 || {_bhDot < 0.001} || {_bw < 0.001} || {_bh < 0.001}) then { continue };

    if (_compact) then {
        _bg ctrlShow false;
        _sh ctrlShow false;
        _txt ctrlShow false;
        _pic ctrlShow true;
        _pic ctrlSetText _ic;
        _pic ctrlSetTextColor _iconCol;
        _pic ctrlSetPosition [_px, _py, _iconW, _bhDot];
        _pic ctrlCommit 0;
    } else {
        if (_showPlate) then {
            _bg ctrlShow true;
            _bg ctrlSetBackgroundColor [0, 0, 0, 0.14 * _alpha];
            _bg ctrlSetPosition [_px - 0.002 * safeZoneW, _py, _bw + 0.004 * safeZoneW, _bh];
            _bg ctrlCommit 0;
        } else {
            _bg ctrlShow false;
        };

        _pic ctrlShow true;
        _pic ctrlSetText _ic;
        _pic ctrlSetTextColor _iconCol;
        _pic ctrlSetPosition [_px, _py + 0.002 * safeZoneH, _iconW, _iconW];
        _pic ctrlCommit 0;

        private _tx = _px + _iconW + 0.004 * safeZoneW;
        private _ty = _py - 0.001 * safeZoneH;
        private _tw = _txtW;
        private _th = _bh;

        _sh ctrlShow true;
        _sh ctrlSetText _line;
        _sh ctrlSetTextColor [0, 0, 0, 0.55 * _alpha];
        _sh ctrlSetPosition [_tx + 0.0012 * safeZoneW, _ty + 0.0012 * safeZoneH, _tw, _th];
        _sh ctrlCommit 0;

        _txt ctrlShow true;
        _txt ctrlSetText _line;
        _txt ctrlSetTextColor _txtCol;
        _txt ctrlSetPosition [_tx, _ty, _tw, _th];
        _txt ctrlCommit 0;
    };

    _used = _used + 1;
} forEach _queue;

for "_i" from _used to (_prevSlots - 1) do {
    ([_i] call _fnc_getCtrls) params ["_bg", "_sh", "_pic", "_txt"];
    { _x ctrlShow false; _x ctrlCommit 0; } forEach [_bg, _sh, _pic, _txt];
};

uiNamespace setVariable ["COMSPEC_EcotiHudSlotCount", _used];
