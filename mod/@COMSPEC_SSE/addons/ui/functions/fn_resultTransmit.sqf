private _fog = missionNamespace getVariable ["comspec_sse_lastResult", createHashMap];
if (count _fog == 0) exitWith { hint "Aucun résultat à transmettre."; false };

private _ent = missionNamespace getVariable ["comspec_sse_lastResultEntity", objNull];
if (isNull _ent && {!isNil "comspec_sse_fnc_uiGetRecord"}) then {
    _ent = [] call comspec_sse_fnc_uiGetRecord;
};

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
        hint "Liaison indisponible — résultat conservé sur place.";
    };
};
true
