if (!hasInterface) exitWith {};

missionNamespace setVariable ["Iceman_WR_buildVersion", "2026.07.20-fast-ptt-v1", false];

if (isNil "Iceman_WR_state") then {
    private _stored = profileNamespace getVariable ["Iceman_WR_profile", []];
    Iceman_WR_state = createHashMapFromArray [
        ["tab", "home"],
        ["selection", 0],
        ["frequency", "32.0"],
        ["profileName", "Default"],
        ["activeTalkgroup", 1],
        ["txSlots", [1, 0, 0, 0]],
        ["txTalkgroups", [1]],
        ["txEditSlot", 1],
        ["monitorTalkgroups", [1, 2]],
        ["monitorAudio", [[1, "BOTH"], [2, "BOTH"]]],
        ["monitorVolume", [[1, 1], [2, 1]]],
        ["txSlot", 1],
        ["txKeysDown", []],
        ["ctabPttActive", []],
        ["pttKeyBindings", []],
        ["acrePttSlot", -1],
        ["acreChannelSignature", ""],
        ["freqBanks", []],
        ["subscriptions", []],
        ["gateway", false],
        ["lastNodes", []],
        ["lastFeeds", []],
        ["lastFeedInfo", []],
        ["lastHealthRows", []],
        ["lastHasRadio", false],
        ["updating", false]
    ];
    if (_stored isEqualType [] && {(count _stored) > 0}) then {
        {
            if (_x isEqualType [] && {(count _x) == 2}) then {
                Iceman_WR_state set [_x # 0, _x # 1];
            };
        } forEach _stored;
    };
    call Iceman_fnc_wr_getTxSlots;
};

{
    if (isNil {uiNamespace getVariable _x}) then {
        uiNamespace setVariable [_x, missionNamespace getVariable [_x, {}]];
    };
} forEach [
    "Iceman_fnc_wr_action",
    "Iceman_fnc_wr_locateSelected",
    "Iceman_fnc_wr_onListSelect",
    "Iceman_fnc_wr_onOpened",
    "Iceman_fnc_wr_open",
    "Iceman_fnc_wr_readUi",
    "Iceman_fnc_wr_selectTab",
    "Iceman_fnc_wr_showRadioHint",
    "Iceman_fnc_wr_updatePanel"
];

[
    ["Iceman ATAK", "Wave Relay"],
    "waveRelayOpen",
    ["Wave Relay: Open App", "Open Wave Relay when ATAK is already displayed."],
    {call Iceman_fnc_wr_open},
    "",
    [],
    false
] call CBA_fnc_addKeybind;

{
    private _slot = _x;
    [
        ["Iceman ATAK", "Wave Relay"],
        format ["waveRelayTX%1", _slot],
        [format ["MPU-5 TX%1", _slot], format ["Select and cue Wave Relay transmit slot %1.", _slot]],
        compile format ["[%1, true] call Iceman_fnc_wr_keyTx", _slot],
        compile format ["[%1, false] call Iceman_fnc_wr_keyTx", _slot],
        [],
        false,
        0,
        false
    ] call CBA_fnc_addKeybind;
} forEach [1, 2, 3, 4];

["ctab_interface_open", {
    _this call Iceman_fnc_wr_installCtabPtt;
}] call CBA_fnc_addEventHandler;

["acre_remoteStartedSpeaking", {
    params ["_unit", "_speakingType", "_radioId"];
    if !([player] call Iceman_fnc_wr_hasRadio) exitWith {};
    if (isNull _unit || {_unit isEqualTo player}) exitWith {};
    if (_radioId == "" || {_radioId == ","}) exitWith {};

    private _baseRadio = "";
    if !(isNil "acre_sys_radio_fnc_getRadioBaseClassname") then {
        _baseRadio = [_radioId] call acre_sys_radio_fnc_getRadioBaseClassname;
    };
    if (_baseRadio != "ACRE_MPU5") exitWith {};

    private _tg = _unit getVariable ["Iceman_WR_activeTG", 1];
    private _remoteFreq = _unit getVariable ["Iceman_WR_frequency", ""];
    private _state = call Iceman_fnc_wr_getState;
    private _localFreq = _state getOrDefault ["frequency", "32.0"];
    private _monitors = +(_state getOrDefault ["monitorTalkgroups", [1, 2]]);
    if (_remoteFreq != "" && {_remoteFreq != _localFreq}) exitWith {};
    if !((_tg in _monitors) || {_tg == 16}) exitWith {};

    private _ear = [_tg] call Iceman_fnc_wr_getMonitorEar;
    ["RX", _tg, name _unit, -1, _ear] call Iceman_fnc_wr_showRadioHint;
}] call CBA_fnc_addEventHandler;

["acre_remoteStoppedSpeaking", {
    if (!(isNil "acre_sys_list_fnc_hideHint")) then {
        ["Iceman_WR_RX"] call acre_sys_list_fnc_hideHint;
    };
}] call CBA_fnc_addEventHandler;

[{
    call Iceman_fnc_wr_tick;
}, 2] call CBA_fnc_addPerFrameHandler;

[{
    if !(isNil "BCE_fnc_ATAK_getAPPs") then {
        [true, true] call BCE_fnc_ATAK_getAPPs;
    };
}, 1] call CBA_fnc_waitAndExecute;
