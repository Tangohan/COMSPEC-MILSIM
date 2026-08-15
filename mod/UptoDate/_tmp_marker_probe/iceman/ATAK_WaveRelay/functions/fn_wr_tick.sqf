private _state = call Iceman_fnc_wr_getState;
private _hasRadio = [player] call Iceman_fnc_wr_hasRadio;
private _buildVersion = missionNamespace getVariable ["Iceman_WR_buildVersion", "2026.07.20-fast-ptt-v1"];
missionNamespace setVariable ["Iceman_WR_buildVersion", _buildVersion, false];

private _hadRadio = _state getOrDefault ["lastHasRadio", _hasRadio];
if (!_hasRadio && {_hadRadio}) then {
    private _wasTransmitting =
        (_state getOrDefault ["acrePttSlot", -1]) >= 0
        || {!((_state getOrDefault ["txKeysDown", []]) isEqualTo [])}
        || {_state getOrDefault ["txVisualActive", false]};

    if (_wasTransmitting) then {
        if !(isNil "acre_sys_core_fnc_handleMultiPttKeyPressUp") then {
            call acre_sys_core_fnc_handleMultiPttKeyPressUp;
        };

        private _activeTG = _state getOrDefault ["activeTalkgroup", 1];
        private _activeSlot = _state getOrDefault ["txSlot", 1];
        [false, _activeTG, _activeSlot] call Iceman_fnc_wr_setTxVisual;
    };

    if !(isNil "acre_sys_list_fnc_hideHint") then {
        ["Iceman_WR_RX"] call acre_sys_list_fnc_hideHint;
        ["acre_broadcast"] call acre_sys_list_fnc_hideHint;
    };

    _state set ["txKeysDown", []];
    _state set ["acrePttSlot", -1];
};
_state set ["lastHasRadio", _hasRadio];

player setVariable ["Iceman_WR_hasMPU5", _hasRadio, true];
if (_hasRadio) then {
    private _radio = "";
    if !(isNil "acre_api_fnc_getRadioByType") then {
        private _candidate = ["ACRE_MPU5"] call acre_api_fnc_getRadioByType;
        if (!isNil "_candidate" && {_candidate isEqualType ""}) then {
            _radio = _candidate;
        };
    };
    private _txSlots = call Iceman_fnc_wr_getTxSlots;
    player setVariable ["Iceman_WR_nodeId", [player] call Iceman_fnc_wr_getNodeId, true];
    player setVariable ["Iceman_WR_radioId", _radio, true];
    player setVariable ["Iceman_WR_buildVersion", _buildVersion, true];
    player setVariable ["Iceman_WR_gateway", _state getOrDefault ["gateway", false], true];
    player setVariable ["Iceman_WR_frequency", _state getOrDefault ["frequency", "32.0"], true];
    player setVariable ["Iceman_WR_activeTG", _state getOrDefault ["activeTalkgroup", 1], true];
    player setVariable ["Iceman_WR_txSlots", +_txSlots, true];
    player setVariable ["Iceman_WR_txTalkgroups", +(_txSlots select {_x > 0}), true];
    player setVariable ["Iceman_WR_monitorTalkgroups", +(_state getOrDefault ["monitorTalkgroups", [1, 2]]), true];
    call Iceman_fnc_wr_syncAcreChannels;
} else {
    player setVariable ["Iceman_WR_radioId", "", true];
    player setVariable ["Iceman_WR_activeTG", -1, true];
    player setVariable ["Iceman_WR_txSlots", [], true];
    player setVariable ["Iceman_WR_txTalkgroups", [], true];
    player setVariable ["Iceman_WR_monitorTalkgroups", [], true];
    _state set ["acreChannelSignature", ""];
};

true
