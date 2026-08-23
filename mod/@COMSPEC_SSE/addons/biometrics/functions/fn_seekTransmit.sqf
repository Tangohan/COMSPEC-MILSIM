private _target = missionNamespace getVariable ["comspec_sse_seekTarget", objNull];
if (isNull _target) exitWith {
    hint "Aucun sujet SEEK à transmettre.";
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
