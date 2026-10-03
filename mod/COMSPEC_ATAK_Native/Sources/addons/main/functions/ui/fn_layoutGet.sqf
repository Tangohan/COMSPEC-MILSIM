/*
    Géométrie du terminal.
    - Porté (HUD) ou en main mini : téléphone vertical ou horizontal, dans le coin choisi (bas droit par défaut).
    - En main plein écran : téléphone horizontal agrandi au centre.
    La coque est la texture carrée du S7 (tools/src) ; l'écran occupe la zone transparente mesurée.
*/
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _interactive = _s getOrDefault ["interactive", false];
private _mode = toUpper (profileNamespace getVariable ["COMSPEC_ATAK_Mode", "MINI"]);
if (!_interactive || {!(_mode in ["MINI", "FULL"])}) then { _mode = "MINI"; };
private _orient = toUpper (profileNamespace getVariable ["COMSPEC_ATAK_Orientation", "PORTRAIT"]);
if (_mode isEqualTo "FULL" || {!(_orient in ["PORTRAIT", "LANDSCAPE"])}) then { _orient = ["PORTRAIT", "LANDSCAPE"] select (_mode isEqualTo "FULL"); };
private _mini = _mode isEqualTo "MINI";
private _land = _orient isEqualTo "LANDSCAPE";
private _ratio = pixelH / pixelW; // hauteur (unités écran) d'un carré de largeur 1

// Coque S7 : texture carrée 2048 px. Fractions mesurées dans tools/src/android_s7_ca.png.
// _vis = boîte visible de la coque (avec la fixation), _scr = écran transparent.
private _vis = [[0.1465, 0.1025, 0.7202, 0.9263], [0.1025, 0.2798, 0.9263, 0.8535]] select _land;
private _scr = [[0.3491, 0.2222, 0.6504, 0.7549], [0.2222, 0.3496, 0.7549, 0.6509]] select _land;
_vis params ["_vx0", "_vy0", "_vx1", "_vy1"];
// Côté du carré en unités verticales (_dh) ; largeur = _dh / _ratio pour rester carré à l'écran.
// Taille du mini choisie dans les réglages (petit, normal, grand).
private _miniScale = [1, (profileNamespace getVariable ["COMSPEC_ATAK_MiniScale", 1]) max 0.7 min 1.3] select _mini;
private _dh = if (_land) then {
    private _wantW = [safeZoneW * 0.94, safeZoneW * 0.40 * _miniScale] select _mini;
    private _h = _wantW / (_vx1 - _vx0) * _ratio;
    _h min (([safeZoneH * 0.96, (safeZoneH * 0.50 * _miniScale) min (safeZoneH * 0.9)] select _mini) / (_vy1 - _vy0))
} else {
    ([safeZoneH * 0.94, (safeZoneH * 0.62 * _miniScale) min (safeZoneH * 0.94)] select _mini) / (_vy1 - _vy0)
};
private _dw = _dh / _ratio;
// Emplacement du mini choisi dans les réglages : coin ou milieu de bord (T/M/B + L/R), bas droit par défaut.
private _anchor = toUpper (profileNamespace getVariable ["COMSPEC_ATAK_MiniAnchor", "BR"]);
if !(_anchor in ["TL", "TR", "ML", "MR", "BL", "BR"]) then { _anchor = "BR"; };
private _dx = if (_mini) then {
    if ((_anchor select [1, 1]) isEqualTo "L") then { safeZoneX + safeZoneW * 0.01 - _dw * _vx0 } else { safeZoneX + safeZoneW * 0.99 - _dw * _vx1 }
} else { safeZoneX + (safeZoneW - _dw * (_vx0 + _vx1)) / 2 };
private _dy = if (_mini) then {
    switch (_anchor select [0, 1]) do {
        case "T": { safeZoneY + safeZoneH * 0.02 - _dh * _vy0 };
        case "M": { safeZoneY + (safeZoneH - _dh * (_vy0 + _vy1)) / 2 };
        default { safeZoneY + safeZoneH * 0.98 - _dh * _vy1 };
    }
} else { safeZoneY + (safeZoneH - _dh * (_vy0 + _vy1)) / 2 };
// Décalage choisi par le joueur (glisser la coque), par mode et orientation.
private _offKey = format ["COMSPEC_ATAK_Offset_%1_%2", _mode, _orient];
(profileNamespace getVariable [_offKey, [0, 0]]) params [["_ox", 0], ["_oy", 0]];
_dx = _dx + _ox;
_dy = _dy + _oy;

private _sx = _dx + _dw * (_scr select 0);
private _sy = _dy + _dh * (_scr select 1);
private _sw = _dw * ((_scr select 2) - (_scr select 0));
private _sh = _dh * ((_scr select 3) - (_scr select 1));

private _font = ((_sh * ([[0.040, 0.060] select _land, 0.036] select !_mini)) max (safeZoneH * 0.015)) * ((profileNamespace getVariable ["COMSPEC_ATAK_TextScale", 1]) max 0.8 min 1.25);
private _statusH = _font * 1.45;
private _appH = _font * 1.9;
private _dock = !_land;
private _dockH = [0, _font * 2.9] select _dock;
private _railW = [_font * 2.9 / _ratio, 0] select _dock;
// Panneau SITUATION repliable (réglage profil).
private _inspW = [0, _sw * 0.24] select (!_mini && {profileNamespace getVariable ["COMSPEC_ATAK_InspOpen", true]});
private _pad = _sw * 0.012;
private _bodyY = _sy + _statusH + _appH;
private _bodyH = _sh - _statusH - _appH - _dockH;

createHashMapFromArray [
    ["mode", _mode], ["offsetKey", _offKey], ["visible", [_dx + _dw * _vx0, _dy + _dh * _vy0, _dw * (_vx1 - _vx0), _dh * (_vy1 - _vy0)]], ["mini", _mini], ["orientation", _orient], ["landscape", _land], ["interactive", _interactive], ["dock", _dock],
    ["phone", [_dx, _dy, _dw, _dh]], ["phoneTexture", format ["\z\comspec_atak_native\addons\main\data\phone_%1%2.paa", ["portrait", "landscape"] select _land, ["", "_night"] select (switch (profileNamespace getVariable ["COMSPEC_ATAK_Shell", "auto"]) do { case "day": { false }; case "night": { true }; default { sunOrMoon < 0.5 }; })]],
    ["device", [_sx, _sy, _sw, _sh]],
    ["status", [_sx, _sy, _sw, _statusH]],
    ["appbar", [_sx, _sy + _statusH, _sw, _appH]],
    ["dockRect", [_sx, _sy + _sh - _dockH, _sw, _dockH]],
    ["rail", [_sx, _bodyY, _railW, _bodyH]],
    ["body", [_sx + _railW, _bodyY, _sw - _railW, _bodyH]],
    ["inspW", _inspW],
    ["font", _font],
    ["fontSmall", _font * 0.78],
    ["cols", [[6, 4] select _mini, 3] select (!_land)],
    ["pad", _pad]
]
