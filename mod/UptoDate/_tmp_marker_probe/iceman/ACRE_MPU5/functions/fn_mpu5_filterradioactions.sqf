private _actions = if (isNil "acre_ace_interact_fnc_radioListChildrenActions") then {
    []
} else {
    _this call acre_ace_interact_fnc_radioListChildrenActions
};

_actions select {
    private _action = _x param [0, []];
    private _actionId = if (_action isEqualType []) then {_action param [0, ""]} else {""};
    (toLowerANSI _actionId find "acre_mpu5") != 0
}
