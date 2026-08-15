params [["_slot", 1], ["_down", true]];

if !([player] call Iceman_fnc_bridge_hasradio) exitWith {false};

private _state = call Iceman_fnc_bridge_getstate;
private _tx = +(_state getOrDefault ["txChannels", []]);
private _idx = (_slot - 1) max 0;
private _pressed = +(_state getOrDefault ["txKeysDown", []]);

if (_idx >= count _tx) exitWith {
    if (!_down && {_slot in _pressed}) then {
        _pressed = _pressed - [_slot];
        _state set ["txKeysDown", _pressed];
        if ((_state getOrDefault ["acrePttSlot", -1]) == _slot && {!(isNil "acre_sys_core_fnc_handleMultiPttKeyPressUp")}) then {
            call acre_sys_core_fnc_handleMultiPttKeyPressUp;
        };
        _state set ["acrePttSlot", -1];
        [false, [], _slot] call Iceman_fnc_bridge_settxvisual;
    };
    false
};

private _record = _tx # _idx;

if (_down) then {
    if !([_record # 0] call Iceman_fnc_bridge_haslegacyradio) exitWith {
        private _legacyName = ["PRC-117F", "PRC-152"] select ((_record # 0) == "ACRE_PRC152");
        ["BRIDGE", format ["%1 required in your kit or current vehicle for Bridge TX%2.", _legacyName, _slot], 2] call Iceman_fnc_bridge_notify;
        false
    };
    if (_slot in _pressed) exitWith {false};
    if !(_pressed isEqualTo []) exitWith {
        ["BRIDGE", "Release the active Bridge TX before switching channels.", 1.5] call Iceman_fnc_bridge_notify;
        false
    };

    private _applied = [_record] call Iceman_fnc_bridge_applyactive;
    private _radio = _state getOrDefault ["acreLastRadio", ""];
    if (!_applied || {_radio == ""} || {isNil "acre_sys_core_fnc_handleMultiPttKeyPress"} || {isNil "Iceman_fnc_mpu5_keyAcrePtt"}) exitWith {
        _state set ["acrePttSlot", -1];
        ["BRIDGE", "MPU-5 did not key through ACRE.", 2] call Iceman_fnc_bridge_notify;
        false
    };

    private _keyed = [_radio, true, format ["Bridge TX%1", _slot]] call Iceman_fnc_mpu5_keyAcrePtt;
    if !(_keyed # 0) exitWith {
        _state set ["acrePttSlot", -1];
        ["BRIDGE", _keyed # 1, 2] call Iceman_fnc_bridge_notify;
        false
    };

    _pressed pushBackUnique _slot;
    _state set ["txKeysDown", _pressed];
    _state set ["acrePttSlot", _slot];
    [true, _record, _slot] call Iceman_fnc_bridge_settxvisual;
} else {
    if !(_slot in _pressed) exitWith {false};
    _pressed = _pressed - [_slot];
    _state set ["txKeysDown", _pressed];
    if ((_state getOrDefault ["acrePttSlot", -1]) == _slot && {!(isNil "Iceman_fnc_mpu5_keyAcrePtt")}) then {
        [_state getOrDefault ["acreLastRadio", ""], false, "Bridge"] call Iceman_fnc_mpu5_keyAcrePtt;
    };
    _state set ["acrePttSlot", -1];
    [false, _record, _slot] call Iceman_fnc_bridge_settxvisual;
};

false
