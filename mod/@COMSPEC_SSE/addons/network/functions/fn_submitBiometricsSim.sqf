/*
    [_entity, _bioBundle] call comspec_sse_fnc_submitBiometricsSim
*/
params [
    ["_entity", objNull, [objNull]],
    ["_bioBundle", createHashMap, [createHashMap]],
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
        _this call comspec_sse_fnc_submitBiometricsSim;
        private _e = _this param [0, objNull];
        if (_e isEqualType objNull && {!isNull _e}) then { _e setVariable ["comspec_sse_txBusy", false]; };
    };
    true
};

private _payload = [_entity, _bioBundle] call comspec_sse_fnc_buildAthenaBiometricsPayload;
private _uid = _payload getOrDefault ["sse_uid", "?"];

// Id Athena mémorisé après SubmitSsePerson (requis par /persons/{id}/biometrics-sim).
private _athenaId = _entity getVariable ["comspec_sse_athenaPersonId", ""];
if (!(_athenaId isEqualType "")) then { _athenaId = str _athenaId; };
if (_athenaId isNotEqualTo "" && {_athenaId isNotEqualTo "0"}) then {
    _payload set ["athena_person_id", _athenaId];
    _payload set ["person_id", _athenaId];
};

private _envelope = createHashMapFromArray [
    ["kind", "BIOMETRICS"],
    ["command", "SubmitSseBiometricsSim"],
    ["payload", _payload],
    ["record_id", _uid],
    // Rattachement à la fiche personne quand le sse_uid est inconnu (fn_personTransmitted).
    ["entity_netid", if (isNull _entity) then { "" } else { netId _entity }],
    ["txStatus", "PENDING"]
];

private _ok = false;
if ([] call comspec_sse_fnc_isOnline) then {
    _ok = [_envelope] call comspec_sse_fnc_sendViaOverwatch;
};

if (_ok) then {
    if (_announce) then { ["Biométrie envoyée au registre.", "SUCCESS"] call comspec_sse_fnc_notify; };
    [_uid, "biometrics", "seek_ii", "Bio sim", 80, "TRANSMITTED"] call comspec_sse_fnc_addJournalEntry;
} else {
    [_envelope] call comspec_sse_fnc_queueOffline;
    if (_announce) then { ["Biométrie mise en attente.", "WARNING"] call comspec_sse_fnc_notify; };
    [_uid, "biometrics", "seek_ii", "QUEUED", 80, "QUEUED"] call comspec_sse_fnc_addJournalEntry;
};

[format ["submitBiometricsSim uid=%1 ok=%2 raw=%3", _uid, _ok, missionNamespace getVariable ["comspec_sse_lastExtRaw", ""]]] call comspec_sse_fnc_log;
_ok
