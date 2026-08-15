private _display = uiNamespace getVariable ["Iceman_TOC_display", displayNull];
if (isNull _display) exitWith {[] call Iceman_fnc_toc_normalizeSettings};

private _target = uiNamespace getVariable ["Iceman_TOC_target", objNull];
private _existing = [_target] call Iceman_fnc_toc_getSettings;

private _x = _existing param [0, 0];
private _y = _existing param [1, 0];
private _z = _existing param [2, 0.05];
private _width = _existing param [3, 2];
private _height = _existing param [4, 1];
private _pipDistance = 0;
private _pitch = _existing param [6, 0];
private _roll = _existing param [7, 0];
private _mode = "surface";
private _surfaceIndex = (_existing param [9, 0]) max 0;
private _visionCombo = _display displayCtrl 94117;
private _visionIdx = lbCurSel _visionCombo;
private _vision = "normal";
if (_visionIdx >= 0) then {
    _vision = _visionCombo lbData _visionIdx;
};

[_x, _y, _z, _width, _height, _pipDistance, _pitch, _roll, _mode, _surfaceIndex, _vision]
