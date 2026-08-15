private _display = uiNamespace getVariable ["Iceman_TOC_display", displayNull];
if (isNull _display) exitWith {};

private _combo = _display displayCtrl 94102;
private _idx = lbCurSel _combo;
if (_idx < 0) exitWith {
    ["Pick a profile first."] call Iceman_fnc_toc_setStatus;
};

private _settings = [call compile (_combo lbData _idx)] call Iceman_fnc_toc_normalizeSettings;
_settings params ["_x", "_y", "_z", "_width", "_height", "_pipDistance", "_pitch", "_roll", "_mode", "_surfaceIndex", "_vision"];

(_display displayCtrl 94103) ctrlSetText str _x;
(_display displayCtrl 94104) ctrlSetText str _y;
(_display displayCtrl 94105) ctrlSetText str _z;
(_display displayCtrl 94107) ctrlSetText "Game";
(_display displayCtrl 94113) ctrlSetText str _pitch;
(_display displayCtrl 94114) ctrlSetText str _roll;
(_display displayCtrl 94116) ctrlSetText str _surfaceIndex;
private _modeCombo = _display displayCtrl 94115;
private _modeSel = ["surface", "panel"] find _mode;
if (_modeSel < 0) then {
    _modeSel = 0;
};
_modeCombo lbSetCurSel _modeSel;
private _visionCombo = _display displayCtrl 94117;
if (_vision == "thermal") then {
    _vision = "thermal_whot";
};
private _visionSel = ["normal", "nv", "thermal_whot", "thermal_bhot", "a3ti_current"] find _vision;
if (_visionSel < 0) then {
    _visionSel = 0;
};
_visionCombo lbSetCurSel _visionSel;
(_display displayCtrl 94109) ctrlSetText (_combo lbText _idx);

["Profile loaded. Apply Stream to push it to the screen."] call Iceman_fnc_toc_setStatus;
