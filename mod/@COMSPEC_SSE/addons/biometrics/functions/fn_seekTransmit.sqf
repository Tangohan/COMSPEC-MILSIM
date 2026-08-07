private _target = missionNamespace getVariable ["comspec_sse_seekTarget", objNull];
if (isNull _target) exitWith { false };

private _sum = [_target] call comspec_sse_fnc_getBiometricSummary;
private _data = [_target] call comspec_sse_fnc_getData;
private _uid = if (isNil "_data") then {"?"} else {[_data, "uid", "?"] call BIS_fnc_getFromPairs};
private _id = [_target, "identity"] call comspec_sse_fnc_getSection;
private _bio = [_target, "biometrics"] call comspec_sse_fnc_getSection;

private _payloadData = createHashMapFromArray [
    ["identity", if (isNil "_id") then {createHashMap} else {_id}],
    ["biometrics", if (isNil "_bio") then {createHashMap} else {_bio}],
    ["summary", _sum]
];

if (!isNil "comspec_sse_fnc_submitBiometricsSim") then {
    [_target, _payloadData] call comspec_sse_fnc_submitBiometricsSim;
} else {
    [
        _uid,
        "biometrics",
        "seek_ii",
        name player,
        getPosATL _target,
        80,
        _payloadData
    ] call comspec_sse_fnc_submitRecord;
};

hint "Fiche biométrique mise en file / transmise.";
true
