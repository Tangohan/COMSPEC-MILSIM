/*
    Soumet un enregistrement SSE (online → Overwatch/Athena, sinon QUEUED).
    [_recordId, _category, _sourceType, _collector, _pos, _quality, _data] call comspec_sse_fnc_submitRecord
*/
params [
    ["_recordId", "", [""]],
    ["_category", "intel", [""]],
    ["_sourceType", "unknown", [""]],
    ["_collector", "", [""]],
    ["_pos", [0,0,0], [[]], 3],
    ["_quality", 0, [0]],
    ["_data", createHashMap, [createHashMap, []]]
];

private _payload = [_recordId, _category, _sourceType, _collector, _pos, _quality, _data] call comspec_sse_fnc_buildPayload;
_payload set ["case_reference", [] call comspec_sse_fnc_getCaseReference];
_payload set ["idempotency_key", [_category, _recordId] call comspec_sse_fnc_makeIdempotencyKey];

private _records = missionNamespace getVariable ["comspec_sse_missionRecords", []];
_records pushBack _payload;
missionNamespace setVariable ["comspec_sse_missionRecords", _records, true];

private _envelope = createHashMapFromArray [
    ["kind", toUpper _category],
    ["command", "SendSSE"],
    ["payload", _payload],
    ["record_id", _recordId],
    ["txStatus", "PENDING"]
];

if ([] call comspec_sse_fnc_isOnline) then {
    private _ok = [_envelope] call comspec_sse_fnc_sendViaOverwatch;
    if (_ok) then {
        hint format ["SSE transmis — %1", _recordId];
        [_recordId, "transmit", _sourceType, "Transmis Athena", _quality, "TRANSMITTED"] call comspec_sse_fnc_addJournalEntry;
    } else {
        [_envelope] call comspec_sse_fnc_queueOffline;
        hint format ["SSE en file (échec envoi) — %1", _recordId];
        [_recordId, "transmit", _sourceType, "QUEUED (échec)", _quality, "QUEUED"] call comspec_sse_fnc_addJournalEntry;
    };
} else {
    [_envelope] call comspec_sse_fnc_queueOffline;
    hint format ["Mode hors-ligne — %1 mis en file QUEUED", _recordId];
    [_recordId, "transmit", _sourceType, "QUEUED offline", _quality, "QUEUED"] call comspec_sse_fnc_addJournalEntry;
};

[format ["submitRecord %1 cat=%2 q=%3", _recordId, _category, _quality]] call comspec_sse_fnc_log;
true
