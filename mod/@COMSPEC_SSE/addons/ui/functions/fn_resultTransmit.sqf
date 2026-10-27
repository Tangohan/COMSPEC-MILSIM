private _fog = missionNamespace getVariable ["comspec_sse_lastResult", createHashMap];
if (count _fog == 0) exitWith { ["Aucun résultat à transmettre.", "WARNING"] call comspec_sse_fnc_notify; false };

// Entité figée par showResult pour CE résultat (pas de repli sur le sujet UI : il peut être un autre sujet).
private _ent = missionNamespace getVariable ["comspec_sse_lastResultEntity", objNull];

private _type = toUpper (_fog getOrDefault ["type", ""]);
private _personLike = _type in ["IDENTITY", "PERSON", "BIOMETRICS"];

if (_personLike && {!isNull _ent} && {!isNil "comspec_sse_fnc_transmitEntity"}) exitWith {
    [_ent, false, true] call comspec_sse_fnc_transmitEntity;
    true
};

if (!isNull _ent && {_ent isKindOf "CAManBase"} && {_type isEqualTo ""} && {!isNil "comspec_sse_fnc_transmitEntity"}) exitWith {
    [_ent, false, true] call comspec_sse_fnc_transmitEntity;
    true
};

if (!isNil "comspec_sse_fnc_submitDigitalAcquisition") then {
    [_ent, _fog, true] call comspec_sse_fnc_submitDigitalAcquisition;
} else {
    if (!isNil "comspec_sse_fnc_submitRecord") then {
        [
            _fog getOrDefault ["uid", "SSE-UNKNOWN"],
            "digital",
            _fog getOrDefault ["type", "unknown"],
            name player,
            getPosATL player,
            _fog getOrDefault ["quality", 0],
            _fog
        ] call comspec_sse_fnc_submitRecord;
    } else {
        ["Liaison indisponible — résultat conservé sur place.", "WARNING"] call comspec_sse_fnc_notify;
    };
};
true
