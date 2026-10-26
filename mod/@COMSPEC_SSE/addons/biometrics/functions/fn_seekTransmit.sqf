private _target = missionNamespace getVariable ["comspec_sse_seekTarget", objNull];
if (isNull _target) exitWith {
    ["Aucun sujet SEEK à transmettre.", "WARNING"] call comspec_sse_fnc_notify;
    false
};

if (!isNil "comspec_sse_fnc_transmitEntity") exitWith {
    [_target, false, true] call comspec_sse_fnc_transmitEntity;
    true
};

if (!isNil "comspec_sse_fnc_submitPersonRecord") then {
    [_target] call comspec_sse_fnc_submitPersonRecord;
};
if (!isNil "comspec_sse_fnc_submitBiometricsSim") then {
    [_target] call comspec_sse_fnc_submitBiometricsSim;
};
true
