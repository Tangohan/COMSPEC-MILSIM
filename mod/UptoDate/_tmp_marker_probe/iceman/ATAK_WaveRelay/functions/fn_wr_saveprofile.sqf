call Iceman_fnc_wr_readUi;

private _state = call Iceman_fnc_wr_getState;
private _name = _state getOrDefault ["profileName", "Default"];
if (_name == "") then {_name = "Default"};

private _profile = [
    _name,
    _state getOrDefault ["frequency", "32.0"],
    +(_state getOrDefault ["freqBanks", []]),
    +(_state getOrDefault ["subscriptions", []]),
    _state getOrDefault ["gateway", false]
];

private _profiles = call Iceman_fnc_wr_getProfiles;
private _idx = _profiles findIf {(_x # 0) == _name};
if (_idx >= 0) then {
    _profiles set [_idx, _profile];
} else {
    _profiles pushBack _profile;
};

profileNamespace setVariable ["Iceman_WR_profiles", _profiles];
saveProfileNamespace;

["WAVE RELAY", format ["Saved profile %1.", _name], 3] call cTab_fnc_addNotification;
true
