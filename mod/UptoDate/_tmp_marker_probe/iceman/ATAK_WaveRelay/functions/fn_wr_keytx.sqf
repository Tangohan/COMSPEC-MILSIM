params [["_slot", 1], ["_down", true]];

private _slotIndex = (round _slot) max 1 min 4;
private _state = call Iceman_fnc_wr_getState;
private _pressed = +(_state getOrDefault ["txKeysDown", []]);

if (!_down) exitWith {
    private _wasActive = (_slotIndex in _pressed) || {(_state getOrDefault ["acrePttSlot", -1]) == _slotIndex};
    if (!_wasActive) exitWith {false};

    _pressed = _pressed - [_slotIndex];
    _state set ["txKeysDown", _pressed];

    private _radio = _state getOrDefault ["acreLastRadio", player getVariable ["Iceman_WR_radioId", ""]];
    if !(isNil "Iceman_fnc_mpu5_keyAcrePtt") then {
        [_radio, false, format ["Wave Relay TX%1", _slotIndex]] call Iceman_fnc_mpu5_keyAcrePtt;
    } else {
        if !(isNil "acre_sys_core_fnc_handleMultiPttKeyPressUp") then {
            call acre_sys_core_fnc_handleMultiPttKeyPressUp;
        };
    };

    _state set ["acrePttSlot", -1];
    private _tg = _state getOrDefault ["activeTalkgroup", 1];
    [{
        params ["_tg", "_slotIndex"];
        [false, _tg, _slotIndex] call Iceman_fnc_wr_setTxVisual;
    }, [_tg, _slotIndex]] call CBA_fnc_execNextFrame;

    true
};

if !([player] call Iceman_fnc_wr_hasRadio) exitWith {
    false
};

private _txSlots = call Iceman_fnc_wr_getTxSlots;
private _tg = _txSlots # (_slotIndex - 1);
if (_tg <= 0) exitWith {false};
if (_slotIndex in _pressed) exitWith {true};
if !(_pressed isEqualTo []) exitWith {
    ["WAVE RELAY", "Release the active TX slot before switching talkgroups.", 1.5] call cTab_fnc_addNotification;
    false
};

if !([_tg] call Iceman_fnc_wr_applyAcreTalkgroup) exitWith {
    _state set ["acrePttSlot", -1];
    ["WAVE RELAY", "MPU-5 channel selection failed.", 2] call cTab_fnc_addNotification;
    false
};

private _radio = _state getOrDefault ["acreLastRadio", ""];
if (_radio == "") exitWith {false};

private _result = [false, "ACRE PTT functions unavailable"];
if !(isNil "Iceman_fnc_mpu5_keyAcrePtt") then {
    _result = [_radio, true, format ["Wave Relay TX%1", _slotIndex]] call Iceman_fnc_mpu5_keyAcrePtt;
} else {
    if (!(isNil "acre_api_fnc_setCurrentRadio") && {!(isNil "acre_sys_core_fnc_handleMultiPttKeyPress")}) then {
        [_radio] call acre_api_fnc_setCurrentRadio;
        [-1] call acre_sys_core_fnc_handleMultiPttKeyPress;
        private _accepted = (missionNamespace getVariable ["acre_sys_core_pttKeyDown", false]) && {
            (missionNamespace getVariable ["ACRE_BROADCASTING_RADIOID", ""]) == _radio
        };
        _result = [_accepted, ["ACRE rejected MPU-5 voice transmit.", ""] select _accepted];
    };
};

if !(_result param [0, false]) exitWith {
    _state set ["acrePttSlot", -1];
    ["WAVE RELAY", _result param [1, "ACRE rejected MPU-5 voice transmit."], 2] call cTab_fnc_addNotification;
    false
};

_pressed pushBackUnique _slotIndex;
_state set ["txKeysDown", _pressed];
_state set ["txSlot", _slotIndex];
_state set ["activeTalkgroup", _tg];
_state set ["acrePttSlot", _slotIndex];

[{
    params ["_tg", "_slotIndex"];
    private _state = call Iceman_fnc_wr_getState;
    if ((_state getOrDefault ["acrePttSlot", -1]) == _slotIndex) then {
        [true, _tg, _slotIndex] call Iceman_fnc_wr_setTxVisual;
    };
}, [_tg, _slotIndex]] call CBA_fnc_execNextFrame;

true
