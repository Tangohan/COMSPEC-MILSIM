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
// Verrou par sujet (local, non diffusé) posé avant le spawn : des clics répétés lançaient des envois parallèles du
// même sujet (doublons Athena, journal, missionRecords). Levé à la fin du corps relancé.
// Clics joueur seulement (_announce) : les paliers / paquets Zeus (_announce false) sont des envois distincts.
if (!canSuspend) exitWith {
    private _lock = _announce && {!isNull _entity};
    if (_lock && {_entity getVariable ["comspec_sse_txBusy", false]}) exitWith {
        ["Transmission en cours…", "INFO"] call comspec_sse_fnc_notify;
        false
    };
    if (_lock) then { _entity setVariable ["comspec_sse_txBusy", true]; };
    [_this, _lock] spawn {
        params ["_args", "_lock"];
        _args call comspec_sse_fnc_submitDigitalAcquisition;
        private _e = _args param [0, objNull];
        if (_lock && {_e isEqualType objNull} && {!isNull _e}) then { _e setVariable ["comspec_sse_txBusy", false]; };
    };
    true
};

// Fog vide : les derniers résultats affichés sont globaux (n'importe quel sujet). On ne les reprend que s'ils
// portent l'uid d'un support de CE sujet ; sinon fog propre au sujet (record_id / lignes / idempotence corrects).
if (count _fog == 0 && {!isNull _entity}) then {
    private _devs = [_entity, "digitalDevices"] call comspec_sse_fnc_getSection;
    private _uids = [];
    if (!isNil "_devs" && {_devs isEqualType []}) then {
        _uids = (_devs select {_x isEqualType createHashMap}) apply {_x getOrDefault ["uid", ""]};
    };
    _uids = _uids - ["", "?"];
    {
        private _cand = missionNamespace getVariable [_x, createHashMap];
        if (count _fog == 0 && {_cand isEqualType createHashMap} && {(_cand getOrDefault ["uid", ""]) in _uids}) then {
            _fog = _cand;
        };
    } forEach ["comspec_sse_lastDigitalResult", "comspec_sse_lastResult"];
    if (count _fog == 0) then {
        _fog = [_entity, "identify"] call comspec_sse_fnc_revealDigitalFog;
    };
};
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
