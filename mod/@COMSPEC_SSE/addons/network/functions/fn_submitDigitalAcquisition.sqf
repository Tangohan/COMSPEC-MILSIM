/*
    [_entity, _fog] call comspec_sse_fnc_submitDigitalAcquisition
*/
params [
    ["_entity", objNull, [objNull]],
    ["_fog", createHashMap, [createHashMap]],
    ["_announce", true, [true]]
];

// Appel non planifié (bouton d'interface, app SSE terrain) : relancé en spawn, sinon callExtension bloque le jeu
// pendant la requête HTTP (fn_extensionCall n'attend via extAwait qu'en contexte suspendable).
if (!canSuspend) exitWith { _this spawn comspec_sse_fnc_submitDigitalAcquisition; true };

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
// Copie locale bornée : diffusée à tous à chaque envoi (et gardée pour chaque arrivant), elle grossissait toute la mission.
if ((count _records) > 50) then { _records deleteRange [0, (count _records) - 50]; };
missionNamespace setVariable ["comspec_sse_missionRecords", _records];

private _envelope = createHashMapFromArray [
    ["kind", "DIGITAL"],
    ["command", "SubmitSseDigital"],
    ["payload", _payload],
    ["record_id", _uid],
    ["txStatus", "PENDING"]
];

private _ok = false;
if ([] call comspec_sse_fnc_isOnline) then {
    _ok = [_envelope] call comspec_sse_fnc_sendViaOverwatch;
};

if (_ok) then {
    if (_announce) then { ["Acquisition numérique envoyée au registre.", "SUCCESS"] call comspec_sse_fnc_notify; };
    private _src = _payload getOrDefault ["source_type", "device"];
    private _q = _payload getOrDefault ["quality", 0];
    [_uid, "digital", _src, "Athena digital", _q, "TRANSMITTED"] call comspec_sse_fnc_addJournalEntry;
} else {
    [_envelope] call comspec_sse_fnc_queueOffline;
    if (_announce) then { ["Acquisition numérique mise en attente.", "WARNING"] call comspec_sse_fnc_notify; };
    private _src = _payload getOrDefault ["source_type", "device"];
    private _q = _payload getOrDefault ["quality", 0];
    [_uid, "digital", _src, "QUEUED", _q, "QUEUED"] call comspec_sse_fnc_addJournalEntry;
};

[format ["submitDigitalAcquisition uid=%1 ok=%2 raw=%3", _uid, _ok, missionNamespace getVariable ["comspec_sse_lastExtRaw", ""]]] call comspec_sse_fnc_log;
_ok
