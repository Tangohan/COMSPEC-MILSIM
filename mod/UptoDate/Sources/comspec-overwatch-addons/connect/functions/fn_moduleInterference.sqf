/*
    Module Zeus/Eden : interférence.
*/
private _args = if (_this isEqualType []) then { _this } else { [_this] };
private _logic = objNull;
private _units = [];
private _activated = true;
private _a0 = _args param [0, objNull];
if (_a0 isEqualType objNull) then {
    _logic = _a0;
    _units = _args param [1, []];
    _activated = _args param [2, true];
} else {
    if (_a0 isEqualType "" && {(_args param [1, objNull]) isEqualType objNull}) then {
        _logic = _args param [1, objNull];
        _units = _args param [2, []];
        _activated = _args param [3, true];
    };
};
if (isNull _logic) exitWith { false };
if (!(_units isEqualType [])) then { _units = []; };
if (!(_activated isEqualType true)) then { _activated = true; };
[_logic, _units, _activated, "interference", 300, 50] call comspec_overwatch_connect_fnc_moduleApplyRoleplayZone
