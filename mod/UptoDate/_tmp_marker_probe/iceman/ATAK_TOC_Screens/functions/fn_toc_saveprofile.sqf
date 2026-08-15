private _display = uiNamespace getVariable ["Iceman_TOC_display", displayNull];
if (isNull _display) exitWith {};

private _name = ctrlText (_display displayCtrl 94109);
if (_name isEqualTo "") then {
    _name = "Default";
};

private _settings = call Iceman_fnc_toc_readDialog;
private _profiles = call Iceman_fnc_toc_getProfiles;
_profiles = _profiles select {(_x # 0) != _name};
_profiles pushBack [_name, _settings];

profileNamespace setVariable ["Iceman_TOC_profiles", _profiles];
saveProfileNamespace;

call Iceman_fnc_toc_refreshDialog;
["Profile saved."] call Iceman_fnc_toc_setStatus;
