private _display = uiNamespace getVariable ["Iceman_TOC_display", displayNull];
if (isNull _display) exitWith {};

private _combo = _display displayCtrl 94102;
private _idx = lbCurSel _combo;
if (_idx < 0) exitWith {
    ["Pick a profile first."] call Iceman_fnc_toc_setStatus;
};

private _name = _combo lbText _idx;
private _profiles = (call Iceman_fnc_toc_getProfiles) select {(_x # 0) != _name};
profileNamespace setVariable ["Iceman_TOC_profiles", _profiles];
saveProfileNamespace;

call Iceman_fnc_toc_refreshDialog;
["Profile deleted."] call Iceman_fnc_toc_setStatus;
