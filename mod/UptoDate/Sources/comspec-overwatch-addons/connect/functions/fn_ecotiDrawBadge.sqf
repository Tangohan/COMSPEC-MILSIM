/*
    Badge ECOTI sous JVN.
    Mode world3d : drawIcon3D (losange, ombre portée, fondu distance).
    Mode screen2d : file pour le HUD écran (anti-chevauchement).
    Params: [_pos, _icon, _color, _label, _dist, _iconSize, _compact]
*/
params [
    ["_pos", [0, 0, 0], [[]]],
    ["_icon", "", [""]],
    ["_color", [0.75, 1, 0.95, 1], [[]]],
    ["_label", "", [""]],
    ["_dist", 0, [0]],
    ["_iconSize", 0.55, [0]],
    ["_compact", false, [true]]
];

if (!(_pos isEqualType []) || {(count _pos) < 3}) exitWith {};

if (_icon isEqualTo "" || {_icon find "military\dot_CA" >= 0}) then {
    _icon = [] call comspec_overwatch_connect_fnc_ecotiIconPath;
};

private _mode = missionNamespace getVariable ["comspec_overwatch_ecoti_render_mode", "world3d"];
if (!(_mode isEqualType "")) then { _mode = "world3d"; };
_mode = toLower _mode;

if (_mode in ["screen2d", "screen", "hud2d", "2d"]) exitWith {
    private _q = missionNamespace getVariable ["COMSPEC_EcotiBadgeQueue", []];
    if (!(_q isEqualType [])) then { _q = []; };
    _q pushBack [_pos, _icon, _color, _label, _dist, _iconSize, _compact];
    missionNamespace setVariable ["COMSPEC_EcotiBadgeQueue", _q, false];
};

_color = [_color] call comspec_overwatch_connect_fnc_ecotiNormalizeColor;
private _theme = [] call comspec_overwatch_connect_fnc_ecotiThemeColors;
private _txtCol = _theme getOrDefault ["badge", [1, 1, 1, 1]];

private _sz = (_iconSize max 0.28) min 0.7;
if (_compact) then { _sz = _sz * 0.72; };

private _maxDist = missionNamespace getVariable ["comspec_overwatch_ecoti_max_dist", 1200];
private _fadeStart = (_maxDist * 0.4) max 80;
private _fade = if (_dist <= _fadeStart) then {
    1
} else {
    (1 - (0.65 * ((_dist - _fadeStart) / ((_maxDist - _fadeStart) max 1)))) max 0.32
};
_color set [3, ((_color select 3) * _fade) min 1];
_txtCol set [3, ((_txtCol select 3) * _fade) min 1];

if (_compact) exitWith {
    drawIcon3D [
        _icon,
        _color,
        _pos,
        _sz,
        _sz,
        0,
        "",
        0,
        0.001,
        "PuristaMedium"
    ];
};

private _distTxt = [_dist] call comspec_overwatch_connect_fnc_ecotiFormatDistance;
private _name = trim _label;
if (_name isEqualTo "") then { _name = "Repère"; };

private _brSq = _name find " [";
if (_brSq > 0) then {
    private _tail = _name select [_brSq + 2];
    private _close = _tail find "]";
    if (_close > 0) then {
        private _inner = _tail select [0, _close];
        if (_inner isEqualTo (str (parseNumber _inner)) && {(parseNumber _inner) > 0}) then {
            _name = trim (_name select [0, _brSq]);
        };
    };
};
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

if ((count _name) > 22) then {
    _name = (_name select [0, 20]) + "…";
};
private _txt = format ["%1 · %2", _name, _distTxt];
private _fs = (linearConversion [40, 900, _dist, 0.028, 0.022, true]) max 0.02;

drawIcon3D [
    _icon,
    _color,
    _pos,
    _sz,
    _sz,
    0,
    "",
    0,
    0.001,
    "PuristaMedium"
];

drawIcon3D [
    "",
    _txtCol,
    _pos,
    0.01,
    0.01,
    0,
    _txt,
    1,
    _fs,
    "PuristaMedium",
    "right"
];
