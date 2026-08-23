/*
    Envoie / file une fiche personne Athena.
    [_entity, _extra] call comspec_sse_fnc_submitPersonRecord
*/
params [
    ["_entity", objNull, [objNull]],
    ["_extra", createHashMap, [createHashMap]],
    ["_announce", true, [true]]
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
} else {
    [format ["submitPersonRecord hors-ligne uid=%1", _uid], "WARN"] call comspec_sse_fnc_log;
};

if (_ok) then {
    [_entity, "TRANSMITTED"] call comspec_sse_fnc_markTransmitted;
    // Mémoriser l’id Athena (détail OK) pour biométrie / photo.
    private _raw = missionNamespace getVariable ["comspec_sse_lastExtRaw", ""];
    if (_raw isEqualType "" && {_raw isNotEqualTo ""}) then {
        private _parsed = parseSimpleArray _raw;
        if (_parsed isEqualType [] && {(count _parsed) >= 2}) then {
            private _detail = _parsed select 1;
            if (_detail isEqualType "" && {_detail isNotEqualTo ""} && {_detail isNotEqualTo "Success"}) then {
                private _n = parseNumber _detail;
                if (_n > 0) then {
                    _entity setVariable ["comspec_sse_athenaPersonId", str (floor _n), true];
                };
            };
        };
    };
    if (_announce) then {
        hint "Fiche d’identité envoyée au registre.";
    };
    [_uid, "person", "athena", "Fiche personne", 80, "TRANSMITTED"] call comspec_sse_fnc_addJournalEntry;
} else {
    [_envelope] call comspec_sse_fnc_queueOffline;
    if (_announce) then {
        hint "La fiche n’est pas encore arrivée au registre. Elle est mise en attente.";
    };
    [_uid, "person", "athena", "QUEUED", 80, "QUEUED"] call comspec_sse_fnc_addJournalEntry;
};

[format ["submitPersonRecord uid=%1 ok=%2 raw=%3", _uid, _ok, missionNamespace getVariable ["comspec_sse_lastExtRaw", ""]]] call comspec_sse_fnc_log;
_ok
