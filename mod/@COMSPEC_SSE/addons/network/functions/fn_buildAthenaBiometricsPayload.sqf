/*
    Payload biométrie simulée (POST .../biometrics-sim).
    [_entity, _bioData] call comspec_sse_fnc_buildAthenaBiometricsPayload
*/
params [
    ["_entity", objNull, [objNull]],
    ["_bioData", createHashMap, [createHashMap]]
];

private _bio = [_entity, "biometrics"] call comspec_sse_fnc_getSection;
if (isNil "_bio" || {!(_bio isEqualType createHashMap)}) then { _bio = createHashMap; };
if (count _bioData > 0) then {
    if (_bioData getOrDefault ["biometrics", createHashMap] isEqualType createHashMap) then {
        private _b = _bioData get "biometrics";
        if (count _b > 0) then { _bio = _b; };
    };
};

private _data = [_entity] call comspec_sse_fnc_getData;
private _uid = if (isNil "_data") then {"?"} else {[_data, "uid", "?"] call BIS_fnc_getFromPairs};

createHashMapFromArray [
    ["sse_uid", _uid],
    ["fingerprint_id", _bio getOrDefault ["fingerprintId", ""]],
    ["fingerprint_quality", _bio getOrDefault ["fingerprintQuality", 0]],
    ["iris_id", _bio getOrDefault ["irisId", ""]],
    ["iris_quality", _bio getOrDefault ["irisQuality", 0]],
    ["dna_id", _bio getOrDefault ["dnaId", ""]],
    ["dna_quality", _bio getOrDefault ["dnaQuality", 0]],
    ["face_captured", _bio getOrDefault ["facePhoto", false]],
    ["face_quality", _bio getOrDefault ["faceQuality", 0]],
    ["match_hint", _bio getOrDefault ["matchHint", ""]],
    ["watchlist_ref", _bio getOrDefault ["watchlistRef", ""]],
    ["match_confidence", _bio getOrDefault ["matchConfidence", 0]],
    ["case_reference", [] call comspec_sse_fnc_getCaseReference],
    ["idempotency_key", ["BIO", _uid] call comspec_sse_fnc_makeIdempotencyKey],
    ["submitter_callsign", name player],
    ["schema", "comspec_sse_athena_bio_v0.4"]
]
