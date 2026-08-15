params [
    ["_active", true],
    ["_record", []],
    ["_slot", 1]
];

private _state = call Iceman_fnc_bridge_getstate;

private _hasAcreGesture = {
    params ["_gesture"];
    isClass (configFile >> "CfgGesturesMale" >> "States" >> _gesture)
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

    false
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
    false
};

if (_active) then {
    _state set ["txVisualActive", true];
    _state set ["txVisualSlot", _slot];
    ["TX", _record, -1] call Iceman_fnc_bridge_showhint;
    call _startRadioGesture;
} else {
    _state set ["txVisualActive", false];

    if (!(isNil "acre_sys_list_fnc_hideHint")) then {
        ["acre_broadcast"] call acre_sys_list_fnc_hideHint;
    };

    call _stopRadioGesture;
};

true
