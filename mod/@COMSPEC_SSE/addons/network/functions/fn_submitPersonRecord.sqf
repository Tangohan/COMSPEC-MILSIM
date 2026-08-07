/*
    Envoie / file une fiche personne Athena.
    [_entity, _extra] call comspec_sse_fnc_submitPersonRecord
*/
params [
    ["_entity", objNull, [objNull]],
    ["_extra", createHashMap, [createHashMap]]
];

private _payload = [_entity, _extra] call comspec_sse_fnc_buildAthenaPersonPayload;
private _uid = _payload getOrDefault ["sse_uid", "?"];

// Stockage mission
private _records = missionNamespace getVariable ["comspec_sse_missionRecords", []];
_records pushBack _payload;
missionNamespace setVariable ["comspec_sse_missionRecords", _records, true];

private _envelope = createHashMapFromArray [
    ["kind", "PERSON"],
    ["command", "SubmitSsePerson"],
    ["payload", _payload],
    ["record_id", _uid],
    ["txStatus", "PENDING"]
];

private _ok = false;
if ([] call comspec_sse_fnc_isOnline) then {
    _ok = [_envelope] call comspec_sse_fnc_sendViaOverwatch;
};

if (_ok) then {
    [_entity, "TRANSMITTED"] call comspec_sse_fnc_markTransmitted;
    hint format ["Fiche personne transmise — %1", _uid];
    [_uid, "person", "athena", "Fiche personne", 80, "TRANSMITTED"] call comspec_sse_fnc_addJournalEntry;
} else {
    [_envelope] call comspec_sse_fnc_queueOffline;
    hint format ["Fiche personne QUEUED — %1", _uid];
    [_uid, "person", "athena", "QUEUED", 80, "QUEUED"] call comspec_sse_fnc_addJournalEntry;
};

_ok
