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

// Appel non planifié (bouton d'interface, app SSE terrain) : relancé en spawn, sinon callExtension bloque le jeu
// pendant la requête HTTP (fn_extensionCall n'attend via extAwait qu'en contexte suspendable).
// Verrou par record_id posé avant le spawn : des clics répétés lançaient des envois parallèles du même
// enregistrement (doublons Athena, journal, missionRecords). Levé à la fin du corps relancé.
if (!canSuspend) exitWith {
    if (isNil "comspec_sse_txBusyIds") then { comspec_sse_txBusyIds = createHashMap; };
    // record_id vide : pas de verrou (sinon tous les envois sans id se bloqueraient entre eux).
    private _lock = _recordId isNotEqualTo "";
    if (_lock && {_recordId in comspec_sse_txBusyIds}) exitWith {
        ["Transmission en cours…", "INFO"] call comspec_sse_fnc_notify;
        false
    };
    if (_lock) then { comspec_sse_txBusyIds set [_recordId, true]; };
    [_this, _lock, _recordId] spawn {
        params ["_args", "_lock", "_rid"];
        _args call comspec_sse_fnc_submitRecord;
        if (_lock) then { comspec_sse_txBusyIds deleteAt _rid; };
    };
    true
};

private _payload = [_recordId, _category, _sourceType, _collector, _pos, _quality, _data] call comspec_sse_fnc_buildPayload;
_payload set ["case_reference", [] call comspec_sse_fnc_getCaseReference];
_payload set ["idempotency_key", [_category, _recordId] call comspec_sse_fnc_makeIdempotencyKey];

private _records = missionNamespace getVariable ["comspec_sse_missionRecords", []];
_records pushBack _payload;
// Copie locale bornée : diffusée à tous à chaque envoi (et gardée pour chaque arrivant), elle grossissait toute la mission.
if ((count _records) > 50) then { _records deleteRange [0, (count _records) - 50]; };
missionNamespace setVariable ["comspec_sse_missionRecords", _records];

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
        [format ["SSE transmis — %1", _recordId], "SUCCESS"] call comspec_sse_fnc_notify;
        [_recordId, "transmit", _sourceType, "Transmis Athena", _quality, "TRANSMITTED"] call comspec_sse_fnc_addJournalEntry;
    } else {
        [_envelope] call comspec_sse_fnc_queueOffline;
        [format ["SSE en file (échec envoi) — %1", _recordId], "WARNING"] call comspec_sse_fnc_notify;
        [_recordId, "transmit", _sourceType, "QUEUED (échec)", _quality, "QUEUED"] call comspec_sse_fnc_addJournalEntry;
    };
} else {
    [_envelope] call comspec_sse_fnc_queueOffline;
    [format ["Mode hors-ligne — %1 mis en file QUEUED", _recordId], "WARNING"] call comspec_sse_fnc_notify;
    [_recordId, "transmit", _sourceType, "QUEUED offline", _quality, "QUEUED"] call comspec_sse_fnc_addJournalEntry;
};

[format ["submitRecord %1 cat=%2 q=%3", _recordId, _category, _quality]] call comspec_sse_fnc_log;
true
