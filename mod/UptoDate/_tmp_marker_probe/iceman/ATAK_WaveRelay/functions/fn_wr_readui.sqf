private _state = call Iceman_fnc_wr_getState;
private _controls = call Iceman_fnc_wr_findControls;
private _freqCtrl = _controls getOrDefault ["frequency", controlNull];
private _profileCtrl = _controls getOrDefault ["profile", controlNull];

private _normalizeFrequency = {
    params [["_raw", "32.0"]];
    private _num = parseNumber _raw;
    if (_num <= 0) then {_num = 32};
    private _rounded = round (_num * 10) / 10;
    private _text = str _rounded;
    if ((_text find ".") < 0) then {_text = _text + ".0"};
    _text
};

private _saveCurrentBank = {
    private _s = call Iceman_fnc_wr_getState;
    private _freq = _s getOrDefault ["frequency", "32.0"];
    private _banks = +(_s getOrDefault ["freqBanks", []]);
    private _entry = [
        _freq,
        +(call Iceman_fnc_wr_getTxSlots),
        +(_s getOrDefault ["monitorTalkgroups", [1, 2]]),
        +(_s getOrDefault ["monitorAudio", [[1, "BOTH"], [2, "BOTH"]]]),
        +(_s getOrDefault ["monitorVolume", [[1, 1], [2, 1]]])
    ];
    private _idx = _banks findIf {(_x # 0) == _freq};
    if (_idx >= 0) then {
        _banks set [_idx, _entry];
    } else {
        _banks pushBack _entry;
    };
    _s set ["freqBanks", _banks];
};

private _loadBank = {
    params ["_freq"];
    private _s = call Iceman_fnc_wr_getState;
    private _banks = _s getOrDefault ["freqBanks", []];
    private _idx = _banks findIf {(_x # 0) == _freq};
    if (_idx >= 0) then {
        private _entry = _banks # _idx;
        _s set ["txSlots", [if ((count _entry) > 1) then {_entry # 1} else {[1]}] call Iceman_fnc_wr_getTxSlots];
        _s set ["txTalkgroups", (_s getOrDefault ["txSlots", [1, 0, 0, 0]]) select {_x > 0}];
        _s set ["monitorTalkgroups", +(_entry # 2)];
        _s set ["monitorAudio", if ((count _entry) > 3) then {+(_entry # 3)} else {[]}];
        _s set ["monitorVolume", if ((count _entry) > 4) then {+(_entry # 4)} else {[]}];
        private _slots = call Iceman_fnc_wr_getTxSlots;
        private _first = _slots findIf {_x > 0};
        if (_first >= 0) then {
            _s set ["activeTalkgroup", _slots # _first];
        };
    } else {
        _s set ["txSlots", [1, 0, 0, 0]];
        _s set ["txTalkgroups", [1]];
        _s set ["monitorTalkgroups", [1, 2]];
        _s set ["monitorAudio", [[1, "BOTH"], [2, "BOTH"]]];
        _s set ["monitorVolume", [[1, 1], [2, 1]]];
        _s set ["activeTalkgroup", 1];
    };
};

if (!isNull _freqCtrl) then {
    private _newFreq = [ctrlText _freqCtrl] call _normalizeFrequency;
    private _oldFreq = _state getOrDefault ["frequency", "32.0"];
    if (_newFreq != _oldFreq) then {
        call _saveCurrentBank;
        _state set ["frequency", _newFreq];
        [_newFreq] call _loadBank;
        _freqCtrl ctrlSetText _newFreq;
    };
};

if (!isNull _profileCtrl) then {
    private _profile = ctrlText _profileCtrl;
    if (_profile == "") then {_profile = "Default"};
    _state set ["profileName", _profile];
};

call _saveCurrentBank;
call Iceman_fnc_wr_saveState;
call Iceman_fnc_wr_syncAcreChannels;
true
