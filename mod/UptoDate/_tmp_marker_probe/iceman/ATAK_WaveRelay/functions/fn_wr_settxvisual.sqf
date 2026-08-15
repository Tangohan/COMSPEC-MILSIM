params [["_active", true], ["_talkgroup", 1], ["_slot", 1]];

private _state = call Iceman_fnc_wr_getState;
private _tg = (round _talkgroup) max 1 min 16;

private _hasAcreGesture = {
    params ["_gesture"];
    isClass (configFile >> "CfgGesturesMale" >> "States" >> _gesture)
};

private _clearLegacyLoop = {
    private _handle = _state getOrDefault ["txVisualPFH", -1];
    if (_handle isEqualType 0 && {_handle >= 0} && {!(isNil "CBA_fnc_removePerFrameHandler")}) then {
        [_handle] call CBA_fnc_removePerFrameHandler;
    };
    _state set ["txVisualPFH", -1];
};

private _startRadioGesture = {
    private _gesture = if ((headgear player) != "") then {
        "acre_sys_gestures_helmet"
    } else {
        "acre_sys_gestures_vest"
    };

    if (!([_gesture] call _hasAcreGesture)) then {
        _gesture = "acre_sys_gestures_helmet";
    };

    if ([_gesture] call _hasAcreGesture) exitWith {
        player playActionNow _gesture;
        player setVariable ["acre_sys_gestures_onRadio", true];
        _state set ["txVisualGesture", _gesture];
        true
    };

    _state set ["txVisualGesture", "GestureHi"];
    if (!(isNil "ace_common_fnc_doGesture")) exitWith {
        [player, "GestureHi", 1] call ace_common_fnc_doGesture;
        true
    };

    player playActionNow "GestureHi";
    true
};

private _stopRadioGesture = {
    private _gesture = _state getOrDefault ["txVisualGesture", ""];
    if (_gesture in ["acre_sys_gestures_helmet", "acre_sys_gestures_vest"]) exitWith {
        player playActionNow "acre_sys_gestures_stop";
        player setVariable ["acre_sys_gestures_onRadio", false];
        _state set ["txVisualGesture", ""];
        true
    };

    _state set ["txVisualGesture", ""];
    if (!(isNil "ace_common_fnc_stopGesture")) exitWith {
        [player] call ace_common_fnc_stopGesture;
        true
    };

    false
};

if (_active) then {
    _state set ["txVisualActive", true];
    _state set ["txVisualTalkgroup", _tg];
    _state set ["txVisualSlot", _slot];
    call _clearLegacyLoop;

    ["TX", _tg, name player] call Iceman_fnc_wr_showRadioHint;
    call _startRadioGesture;
} else {
    _state set ["txVisualActive", false];
    call _clearLegacyLoop;

    if (!(isNil "acre_sys_list_fnc_hideHint")) then {
        ["acre_broadcast"] call acre_sys_list_fnc_hideHint;
    };

    call _stopRadioGesture;
};

true
