/*
    [_entity, _fog] call comspec_sse_fnc_submitDigitalAcquisition
*/
params [
    ["_entity", objNull, [objNull]],
    ["_fog", createHashMap, [createHashMap]],
    ["_announce", true, [true]]
];

if (count _fog == 0) then {
    _fog = missionNamespace getVariable ["comspec_sse_lastDigitalResult", createHashMap];
};
if (count _fog == 0) then {
    _fog = missionNamespace getVariable ["comspec_sse_lastResult", createHashMap];
};

private _payload = [_entity, _fog] call comspec_sse_fnc_buildAthenaDigitalPayload;
private _uid = _payload getOrDefault ["record_id", "?"];

private _records = missionNamespace getVariable ["comspec_sse_missionRecords", []];
_records pushBack _payload;
missionNamespace setVariable ["comspec_sse_missionRecords", _records, true];

private _envelope = createHashMapFromArray [
    ["kind", "DIGITAL"],
    ["command", "SendSSE"],
    ["payload", _payload],
    ["record_id", _uid],
    ["txStatus", "PENDING"]
];

private _ok = false;
if ([] call comspec_sse_fnc_isOnline) then {
    _ok = [_envelope] call comspec_sse_fnc_sendViaOverwatch;
};

if (_ok) then {
    if (_announce) then { hint "Acquisition numérique envoyée au registre."; };
    private _src = _payload getOrDefault ["source_type", "device"];
    private _q = _payload getOrDefault ["quality", 0];
    [_uid, "digital", _src, "Athena digital", _q, "TRANSMITTED"] call comspec_sse_fnc_addJournalEntry;
} else {
    [_envelope] call comspec_sse_fnc_queueOffline;
    if (_announce) then { hint "Acquisition numérique mise en attente."; };
    private _src = _payload getOrDefault ["source_type", "device"];
    private _q = _payload getOrDefault ["quality", 0];
    [_uid, "digital", _src, "QUEUED", _q, "QUEUED"] call comspec_sse_fnc_addJournalEntry;
};

[format ["submitDigitalAcquisition uid=%1 ok=%2 raw=%3", _uid, _ok, missionNamespace getVariable ["comspec_sse_lastExtRaw", ""]]] call comspec_sse_fnc_log;
_ok
