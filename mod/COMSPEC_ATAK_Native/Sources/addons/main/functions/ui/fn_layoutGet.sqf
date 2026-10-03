/*
    Géométrie du terminal selon le mode.
    MINI : téléphone dans le coin bas droit, la vue du jeu reste visible autour.
    FULL : tablette plein écran, rail d'apps à gauche, inspecteur à droite sur la carte.
*/
private _mode = toUpper (profileNamespace getVariable ["COMSPEC_ATAK_Mode", "MINI"]);
if !(_mode in ["MINI", "FULL"]) then { _mode = "MINI"; };
private _mini = _mode isEqualTo "MINI";

private _dw = [safeZoneW * 0.94, safeZoneW * 0.27] select _mini;
private _dh = [safeZoneH * 0.92, safeZoneH * 0.74] select _mini;
private _dx = [safeZoneX + safeZoneW * 0.03, safeZoneX + safeZoneW - _dw - safeZoneW * 0.015] select _mini;
private _dy = [safeZoneY + safeZoneH * 0.04, safeZoneY + safeZoneH - _dh - safeZoneH * 0.04] select _mini;

private _statusH = safeZoneH * ([0.030, 0.026] select _mini);
private _appH = safeZoneH * ([0.048, 0.040] select _mini);
private _dockH = [0, safeZoneH * 0.058] select _mini;
private _railW = [safeZoneW * 0.050, 0] select _mini;
private _inspW = [_dw * 0.24, 0] select _mini;
private _pad = safeZoneW * 0.004;

private _bodyY = _dy + _statusH + _appH;
private _bodyH = _dh - _statusH - _appH - _dockH;

createHashMapFromArray [
    ["mode", _mode], ["mini", _mini],
    ["device", [_dx, _dy, _dw, _dh]],
    ["status", [_dx, _dy, _dw, _statusH]],
    ["appbar", [_dx, _dy + _statusH, _dw, _appH]],
    ["dock", [_dx, _dy + _dh - _dockH, _dw, _dockH]],
    ["rail", [_dx, _bodyY, _railW, _bodyH]],
    ["body", [_dx + _railW, _bodyY, _dw - _railW, _bodyH]],
    ["inspW", _inspW],
    ["font", safeZoneH * ([0.027, 0.022] select _mini)],
    ["fontSmall", safeZoneH * ([0.020, 0.017] select _mini)],
    ["cols", [6, 3] select _mini],
    ["pad", _pad]
]
