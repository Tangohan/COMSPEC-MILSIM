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
if (!canSuspend) exitWith { _this spawn comspec_sse_fnc_submitBiometricsSim; true };

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
