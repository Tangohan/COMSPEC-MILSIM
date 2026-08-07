/*
    Associe une empreinte BIO-xxxx à une identité.
    [_bioId, _identityName] call comspec_sse_fnc_matchBiometric
*/
params [
    ["_bioId", "", [""]],
    ["_identityName", "", [""]]
];
if (_bioId == "" || {isNil "comspec_sse_biometricIndex"}) exitWith { false };
private _rec = comspec_sse_biometricIndex getOrDefault [_bioId, createHashMap];
_rec set ["status", "MATCHED"];
_rec set ["identity", _identityName];
_rec set ["matchedAt", time];
comspec_sse_biometricIndex set [_bioId, _rec];
["SSE_NetworkLinked", [_bioId, "BIOMETRIC_MATCH", _identityName]] call comspec_sse_fnc_emitEvent;
true
