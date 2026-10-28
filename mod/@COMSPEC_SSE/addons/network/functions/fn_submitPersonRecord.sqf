/*
    Envoie / file une fiche personne Athena.
    [_entity, _extra] call comspec_sse_fnc_submitPersonRecord
*/
params [
    ["_entity", objNull, [objNull]],
    ["_extra", createHashMap, [createHashMap]],
    ["_announce", true, [true]]
];

// Appel non planifié (bouton d'interface, app SSE terrain) : relancé en spawn, sinon callExtension bloque le jeu
// pendant la requête HTTP (fn_extensionCall n'attend via extAwait qu'en contexte suspendable).
// Verrou par sujet (local, non diffusé) posé avant le spawn : des clics répétés lançaient des envois parallèles du
// même sujet (doublons Athena, journal, missionRecords). Levé à la fin du corps relancé.
if (!canSuspend) exitWith {
    if (!isNull _entity && {_entity getVariable ["comspec_sse_txBusy", false]}) exitWith {
        if (_announce) then { ["Transmission en cours…", "INFO"] call comspec_sse_fnc_notify; };
        false
    };
    if (!isNull _entity) then { _entity setVariable ["comspec_sse_txBusy", true]; };
    _this spawn {
        _this call comspec_sse_fnc_submitPersonRecord;
        private _e = _this param [0, objNull];
        if (_e isEqualType objNull && {!isNull _e}) then { _e setVariable ["comspec_sse_txBusy", false]; };
    };
    true
};

private _payload = [_entity, _extra] call comspec_sse_fnc_buildAthenaPersonPayload;
private _uid = _payload getOrDefault ["sse_uid", "?"];

// Stockage mission
private _records = missionNamespace getVariable ["comspec_sse_missionRecords", []];
_records pushBack _payload;
// Copie locale bornée : diffusée à tous à chaque envoi (et gardée pour chaque arrivant), elle grossissait toute la mission.
if ((count _records) > 50) then { _records deleteRange [0, (count _records) - 50]; };
missionNamespace setVariable ["comspec_sse_missionRecords", _records];

private _envelope = createHashMapFromArray [
    ["kind", "PERSON"],
    ["command", "SubmitSsePerson"],
    ["payload", _payload],
    ["record_id", _uid],
    // Retrouver le sujet quand la fiche part plus tard depuis la file offline (fn_flushQueue).
    ["entity_netid", if (isNull _entity) then { "" } else { netId _entity }],
    ["txStatus", "PENDING"]
];

private _ok = false;
if ([] call comspec_sse_fnc_isOnline) then {
    _ok = [_envelope] call comspec_sse_fnc_sendViaOverwatch;
} else {
    [format ["submitPersonRecord hors-ligne uid=%1", _uid], "WARN"] call comspec_sse_fnc_log;
};

if (_ok) then {
    // Post-traitement partagé avec flushQueue (envoi depuis la file offline).
    [_entity, _uid, _envelope getOrDefault ["txRaw", ""]] call comspec_sse_fnc_personTransmitted;
    if (_announce) then {
        ["Fiche d’identité envoyée au registre.", "SUCCESS"] call comspec_sse_fnc_notify;
    };
    [_uid, "person", "athena", "Fiche personne", 80, "TRANSMITTED"] call comspec_sse_fnc_addJournalEntry;
} else {
    [_envelope] call comspec_sse_fnc_queueOffline;
    if (_announce) then {
        ["La fiche n’est pas encore arrivée au registre. Elle est mise en attente.", "WARNING"] call comspec_sse_fnc_notify;
    };
    [_uid, "person", "athena", "QUEUED", 80, "QUEUED"] call comspec_sse_fnc_addJournalEntry;
};

[format ["submitPersonRecord uid=%1 ok=%2 raw=%3", _uid, _ok, _envelope getOrDefault ["txRaw", ""]]] call comspec_sse_fnc_log;
_ok
