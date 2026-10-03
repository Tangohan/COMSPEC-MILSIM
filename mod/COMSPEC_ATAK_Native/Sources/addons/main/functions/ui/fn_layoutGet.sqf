/*
    Géométrie du terminal.
    - Porté (HUD) ou en main mini : téléphone vertical ou horizontal dans le coin bas droit.
    - En main plein écran : téléphone horizontal agrandi au centre.
    La coque est une texture 1:2 ; l'écran occupe la zone mesurée dans tools/gen_assets.py.
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

private ["_dw", "_dh"];
if (_land) then {
    _dw = [safeZoneW * 0.98, safeZoneW * 0.44] select _mini;
    _dh = _dw * _ratio / 2;
    private _maxH = [safeZoneH * 0.98, safeZoneH * 0.46] select _mini;
    if (_dh > _maxH) then { _dh = _maxH; _dw = _dh / _ratio * 2; };
} else {
    _dh = safeZoneH * 0.66;
    _dw = _dh / _ratio / 2;
};
private _dx = [safeZoneX + (safeZoneW - _dw) / 2, safeZoneX + safeZoneW - _dw - safeZoneW * 0.01] select _mini;
private _dy = [safeZoneY + (safeZoneH - _dh) / 2, safeZoneY + safeZoneH - _dh - safeZoneH * 0.02] select _mini;

// Écran dans la coque (fractions) : paysage (0.105, 0.135)-(0.875, 0.865), portrait = rotation de 90°.
private _scr = if (_land) then { [0.105, 0.135, 0.875, 0.865] } else { [0.135, 0.125, 0.865, 0.895] };
private _sx = _dx + _dw * (_scr select 0);
private _sy = _dy + _dh * (_scr select 1);
private _sw = _dw * ((_scr select 2) - (_scr select 0));
private _sh = _dh * ((_scr select 3) - (_scr select 1));

private _font = (_sh * ([[0.040, 0.060] select _land, 0.036] select !_mini)) max (safeZoneH * 0.015);
private _statusH = _font * 1.45;
private _appH = _font * 1.9;
private _dock = !_land;
private _dockH = [0, _font * 2.9] select _dock;
private _railW = [_font * 2.9 / _ratio, 0] select _dock;
private _inspW = [0, _sw * 0.24] select !_mini;
private _pad = _sw * 0.012;
private _bodyY = _sy + _statusH + _appH;
private _bodyH = _sh - _statusH - _appH - _dockH;

createHashMapFromArray [
    ["mode", _mode], ["mini", _mini], ["orientation", _orient], ["landscape", _land], ["interactive", _interactive], ["dock", _dock],
    ["phone", [_dx, _dy, _dw, _dh]], ["phoneTexture", format ["\z\comspec_atak_native\addons\main\data\phone_%1.paa", ["portrait", "landscape"] select _land]],
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
