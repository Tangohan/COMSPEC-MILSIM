params [
    ["_target", objNull, [objNull]]
];

private _bio = [_target, "biometrics"] call comspec_sse_fnc_getSection;
if (isNil "_bio" || {!(_bio isEqualType createHashMap)}) then { _bio = createHashMap; };

private _fmt = {
    params ["_id", "_q"];
    if (_id isEqualTo "" || {_id isEqualTo false}) exitWith { "NONE" };
    if (_id isEqualType true) exitWith { if (_id) then { "CAPTURED" } else { "NONE" }; };
    format ["%1 (Q%2%%)", _id, _q]
};

private _fpId = _bio getOrDefault ["fingerprintId", ""];
private _fpQ = _bio getOrDefault ["fingerprintQuality", 0];
private _irId = _bio getOrDefault ["irisId", ""];
private _irQ = _bio getOrDefault ["irisQuality", 0];
private _dnaId = _bio getOrDefault ["dnaId", ""];
private _dnaQ = _bio getOrDefault ["dnaQuality", 0];
private _fp = [_fpId, _fpQ] call _fmt;
private _ir = [_irId, _irQ] call _fmt;
private _face = if (_bio getOrDefault ["facePhoto", false]) then {
    format ["CAPTURED (Q%1%%)", _bio getOrDefault ["faceQuality", 0]]
} else { "NONE" };
private _dna = [_dnaId, _dnaQ] call _fmt;

private _complete = 0;
if ((_bio getOrDefault ["fingerprintId", ""]) != "") then { _complete = _complete + 1; };
if ((_bio getOrDefault ["irisId", ""]) != "") then { _complete = _complete + 1; };
if (_bio getOrDefault ["facePhoto", false]) then { _complete = _complete + 1; };
if ((_bio getOrDefault ["dnaId", ""]) != "") then { _complete = _complete + 1; };

private _status = switch (_complete) do {
    case 0: { "READY — aucune capture" };
    case 1; case 2: { "PARTIAL" };
    case 3: { "NEAR COMPLETE" };
    default { "COMPLETE" };
};

createHashMapFromArray [
    ["fingerprint", _fp],
    ["iris", _ir],
    ["face", _face],
    ["dna", _dna],
    ["capturedCount", _complete],
    ["status", _status],
    ["matchHint", _bio getOrDefault ["matchHint", ""]]
]
