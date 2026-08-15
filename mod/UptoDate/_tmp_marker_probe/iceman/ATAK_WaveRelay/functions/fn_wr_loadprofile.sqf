params [["_index", -1]];

private _profiles = call Iceman_fnc_wr_getProfiles;
if (_profiles isEqualTo []) exitWith {
    ["WAVE RELAY", "No saved profiles.", 3] call cTab_fnc_addNotification;
    false
};

if (_index < 0) then {
    _index = (call Iceman_fnc_wr_getState) getOrDefault ["selection", 0];
};
if (_index < 0 || {_index >= count _profiles}) exitWith {false};

private _profile = _profiles # _index;
_profile params ["_name", "_frequency", "_banks", "_subscriptions", "_gateway"];

private _state = call Iceman_fnc_wr_getState;
_state set ["profileName", _name];
_state set ["frequency", _frequency];
_state set ["freqBanks", +_banks];
_state set ["subscriptions", +_subscriptions];
_state set ["gateway", _gateway];

private _bankIndex = _banks findIf {(_x # 0) == _frequency};
if (_bankIndex >= 0) then {
    private _bank = _banks # _bankIndex;
    _state set ["txSlots", [if ((count _bank) > 1) then {_bank # 1} else {[1]}] call Iceman_fnc_wr_getTxSlots];
    _state set ["txTalkgroups", (_state getOrDefault ["txSlots", [1, 0, 0, 0]]) select {_x > 0}];
    _state set ["monitorTalkgroups", +(_bank # 2)];
    _state set ["monitorAudio", if ((count _bank) > 3) then {+(_bank # 3)} else {[]}];
    _state set ["monitorVolume", if ((count _bank) > 4) then {+(_bank # 4)} else {[]}];
    private _tx = +(_state getOrDefault ["txTalkgroups", [1]]);
    private _active = 1;
    if !(_tx isEqualTo []) then {_active = _tx # 0};
    _state set ["activeTalkgroup", _active];
};

player setVariable ["Iceman_WR_gateway", _gateway, true];
call Iceman_fnc_wr_saveState;

private _controls = call Iceman_fnc_wr_findControls;
private _freqCtrl = _controls getOrDefault ["frequency", controlNull];
private _profileCtrl = _controls getOrDefault ["profile", controlNull];
if (!isNull _freqCtrl) then {_freqCtrl ctrlSetText _frequency};
if (!isNull _profileCtrl) then {_profileCtrl ctrlSetText _name};

_state set ["acreChannelSignature", ""];
call Iceman_fnc_wr_syncAcreChannels;

["WAVE RELAY", format ["Loaded profile %1.", _name], 3] call cTab_fnc_addNotification;
true
