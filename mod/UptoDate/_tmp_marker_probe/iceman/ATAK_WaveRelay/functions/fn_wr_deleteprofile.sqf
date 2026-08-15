private _state = call Iceman_fnc_wr_getState;
private _selection = _state getOrDefault ["selection", 0];
private _profiles = call Iceman_fnc_wr_getProfiles;

if (_selection < 0 || {_selection >= count _profiles}) exitWith {
    ["WAVE RELAY", "No profile selected.", 3] call cTab_fnc_addNotification;
    false
};

private _name = (_profiles # _selection) # 0;
_profiles deleteAt _selection;
profileNamespace setVariable ["Iceman_WR_profiles", _profiles];
saveProfileNamespace;

["WAVE RELAY", format ["Deleted profile %1.", _name], 3] call cTab_fnc_addNotification;
true
