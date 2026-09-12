/*
    Badge lisible (plaque sombre + texte contouré) pour l’affichage situation JVN.
    Params: [_pos, _icon, _color, _label, _dist, _iconSize]
*/
params [
    ["_pos", [0, 0, 0], [[]]],
    ["_icon", "\a3\ui_f\data\map\markers\military\dot_CA.paa", [""]],
    ["_color", [0.55, 1, 0.65, 0.95], [[]]],
    ["_label", "", [""]],
    ["_dist", 0, [0]],
    ["_iconSize", 0.72, [0]]
];

if (!(_pos isEqualType []) || {(count _pos) < 3}) exitWith {};

private _distTxt = [_dist] call comspec_overwatch_connect_fnc_ecotiFormatDistance;
private _name = trim _label;
if (_name isEqualTo "") then { _name = "Repère"; };
private _txt = format ["%1  ·  %2", _name, _distTxt];

// Plaque sombre derrière le texte (améliore le contraste sur mur / feuillage).
drawIcon3D [
    "#(rgb,8,8,3)color(0.02,0.06,0.04,0.72)",
    [1, 1, 1, 0.55],
    _pos,
    (_iconSize * 4.2) min 3.8,
    (_iconSize * 1.35) min 1.4,
    0,
    "",
    0,
    0.001,
    "PuristaMedium"
];

drawIcon3D [
    _icon,
    _color,
    _pos,
    _iconSize,
    _iconSize,
    0,
    _txt,
    2,
    0.032,
    "PuristaSemiBold",
    "right"
];
