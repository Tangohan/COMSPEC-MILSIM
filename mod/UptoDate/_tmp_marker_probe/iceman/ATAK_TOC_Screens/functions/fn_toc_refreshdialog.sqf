private _display = uiNamespace getVariable ["Iceman_TOC_display", displayNull];
if (isNull _display) exitWith {};

private _target = uiNamespace getVariable ["Iceman_TOC_target", objNull];
private _feeds = call Iceman_fnc_toc_getFeeds;
private _feedList = _display displayCtrl 94101;
lbClear _feedList;
{
    private _idx = _feedList lbAdd (_x # 0);
    _feedList lbSetData [_idx, str _x];
} forEach _feeds;
if ((lbSize _feedList) > 0) then {
    _feedList lbSetCurSel 0;
};

private _profiles = call Iceman_fnc_toc_getProfiles;
private _profileCombo = _display displayCtrl 94102;
lbClear _profileCombo;
{
    private _idx = _profileCombo lbAdd (_x # 0);
    _profileCombo lbSetData [_idx, str (_x # 1)];
} forEach _profiles;
if ((lbSize _profileCombo) > 0) then {
    _profileCombo lbSetCurSel 0;
};

private _modeCombo = _display displayCtrl 94115;
lbClear _modeCombo;
private _surfaceIdx = _modeCombo lbAdd "Surface";
_modeCombo lbSetData [_surfaceIdx, "surface"];
private _panelIdx = _modeCombo lbAdd "Panel";
_modeCombo lbSetData [_panelIdx, "panel"];

private _visionCombo = _display displayCtrl 94117;
lbClear _visionCombo;
private _visionOptions = [
    ["Normal", "normal"],
    ["Night Vision", "nv"],
    ["Thermal WHOT", "thermal_whot"],
    ["Thermal BHOT (A3TI)", "thermal_bhot"],
    ["A3TI WHOT", "a3ti_whot"],
    ["A3TI BHOT", "a3ti_bhot"],
    ["A3TI Current", "a3ti_current"]
];
{
    private _idx = _visionCombo lbAdd (_x # 0);
    _visionCombo lbSetData [_idx, _x # 1];
} forEach _visionOptions;

private _settings = [_target] call Iceman_fnc_toc_getSettings;
_settings params ["_x", "_y", "_z", "_width", "_height", "_pipDistance", "_pitch", "_roll", "_mode", "_surfaceIndex", "_vision"];

(_display displayCtrl 94103) ctrlSetText str _x;
(_display displayCtrl 94104) ctrlSetText str _y;
(_display displayCtrl 94105) ctrlSetText str _z;
(_display displayCtrl 94107) ctrlSetText "Game";
(_display displayCtrl 94113) ctrlSetText str _pitch;
(_display displayCtrl 94114) ctrlSetText str _roll;
(_display displayCtrl 94116) ctrlSetText str _surfaceIndex;
private _modeSel = ["surface", "panel"] find _mode;
if (_modeSel < 0) then {
    _modeSel = 0;
};
_modeCombo lbSetCurSel _modeSel;
if (_vision == "thermal") then {
    _vision = "thermal_whot";
};
private _visionSel = ["normal", "nv", "thermal_whot", "thermal_bhot", "a3ti_whot", "a3ti_bhot", "a3ti_current"] find _vision;
if (_visionSel < 0) then {
    _visionSel = 0;
};
_visionCombo lbSetCurSel _visionSel;
(_display displayCtrl 94109) ctrlSetText "Default";

if (_feeds isEqualTo []) then {
    ["No active helmet/UAV feeds found yet. Open cTab once or wait for cTab to refresh its lists."] call Iceman_fnc_toc_setStatus;
} else {
    [format ["%1 feed(s). Surface mode writes to the selected screen surface.", count _feeds]] call Iceman_fnc_toc_setStatus;
};
