/*
    Adapter Overwatch / extension — V0.4.
    Accepte soit un payload plat (legacy submitRecord), soit une envelope {kind,command,payload}.
    [_payloadOrEnvelope] call comspec_sse_fnc_sendViaOverwatch

    Important : sendIntel (texte HUMINT) n’écrit PAS une fiche Identités Athena.
    Pour PERSON / SubmitSsePerson / biométrie, seul un retour extension ["OK",…] compte.
*/
params [
    ["_input", createHashMap, [createHashMap]]
];

private _envelope = _input;
private _payload = _input;
private _command = "SendSSE";
private _kind = "GENERIC";

if ((_input getOrDefault ["command", ""]) != "") then {
    _command = _input get "command";
    _kind = _input getOrDefault ["kind", "GENERIC"];
    _payload = _input getOrDefault ["payload", _input];
};

private _json = [_payload] call comspec_sse_fnc_toJsonApprox;
private _preferExt = missionNamespace getVariable ["comspec_sse_preferExtension", true];

private _extOk = {
    params ["_raw"];
    if (!(_raw isEqualType "") || {_raw isEqualTo ""}) exitWith { false };
    private _parsed = parseSimpleArray _raw;
    if (_parsed isEqualType [] && {count _parsed >= 1}) exitWith {
        toUpper (str (_parsed select 0)) isEqualTo "OK"
    };
    private _low = toLower _raw;
    // Filet legacy si l’extension ne renvoie pas un tableau simple.
    (_low find """ok""") >= 0
        && {(_low find "error") < 0}
        && {(_low find "fail") < 0}
        && {(_low find "unknown") < 0}
        && {(_low find "not implemented") < 0}
};

private _needsPersonApi = (
    (toUpper _command) in ["SUBMITSSEPERSON", "SUBMITSSEBIOMETRICSSIM"]
) || {(toUpper _kind) in ["PERSON", "BIOMETRICS"]};

// DIGITAL / SendSSE : aussi typé — sendIntel texte ne crée pas d’acquisition Athena.
private _needsTypedApi = _needsPersonApi
    || {(toUpper _command) isEqualTo "SENDSSE"}
    || {(toUpper _kind) in ["DIGITAL", "GENERIC"]};

// 1) Extension typée
if (_preferExt) then {
    private _extArgs = [_json];
    // Biométrie Athena exige l’id numérique personne (pas seulement le JSON sse_uid).
    if ((toUpper _command) isEqualTo "SUBMITSSEBIOMETRICSSIM") then {
        private _pid = _payload getOrDefault ["athena_person_id", ""];
        if (_pid isEqualTo "") then { _pid = _payload getOrDefault ["person_id", ""]; };
        if (_pid isEqualTo "") then { _pid = _payload getOrDefault ["id", ""]; };
        if (!(_pid isEqualType "")) then { _pid = str _pid; };
        if (_pid isNotEqualTo "" && {_pid isNotEqualTo "0"}) then {
            _extArgs = [_pid, _json];
        };
    };
    private _raw = [_command, _extArgs] call comspec_sse_fnc_extensionCall;
    if ([_raw] call _extOk) exitWith {
        [format ["sendViaOverwatch OK ext %1", _command]] call comspec_sse_fnc_log;
        true
    };
    if (_needsTypedApi) exitWith {
        [format ["sendViaOverwatch FAIL ext %1 raw=%2 (pas de fallback sendIntel pour fiche Athena)", _command, _raw], "WARN"] call comspec_sse_fnc_log;
        false
    };
};

// 2) Overwatch sendIntel — signal texte seulement (pas de registre Identités)
if (!_needsTypedApi && {!isNil "comspec_overwatch_connect_fnc_sendIntel"}) exitWith {
    private _sseUid = _payload getOrDefault ["sse_uid", ""];
    if (_sseUid isEqualTo "") then { _sseUid = _payload getOrDefault ["record_id", "?"]; };
    // Nettoie une éventuelle notation scientifique héritée (1.11e+09).
    if (_sseUid isEqualType "" && {(_sseUid find "e+") >= 0 || {(_sseUid find "e-") >= 0} || {(_sseUid find "E+") >= 0}}) then {
        private _parts = _sseUid splitString "-";
        if ((count _parts) >= 2) then {
            private _tail = _parts select ((count _parts) - 1);
            if ((_tail find "e") >= 0 || {(_tail find "E") >= 0}) then {
                private _n = parseNumber _tail;
                if (_n > 0) then {
                    _parts set [(count _parts) - 1, _n toFixed 0];
                    _sseUid = _parts joinString "-";
                };
            };
        };
    };
    private _quality = _payload getOrDefault ["quality", -1];
    if (_quality < 0) then { _quality = _payload getOrDefault ["match_confidence", 0]; };
    private _text = format [
        "SSE|%1|%2|%3|%4",
        _kind,
        _sseUid,
        _payload getOrDefault ["case_reference", [] call comspec_sse_fnc_getCaseReference],
        if (_quality isEqualType 0) then { _quality toFixed 0 } else { _quality }
    ];
    private _score = ((_payload getOrDefault ["quality", 70]) / 100) max 0.1 min 1;
    [player, "HUMINT", _text, _payload getOrDefault ["idempotency_key", ""], "INFANTRY", _score] call comspec_overwatch_connect_fnc_sendIntel;
    ["sendViaOverwatch OK via sendIntel"] call comspec_sse_fnc_log;
    true
};

// 3) Extension générique SendSSE (hors fiches personne)
if (!_needsTypedApi) then {
    private _raw2 = ["SendSSE", [_json]] call comspec_sse_fnc_extensionCall;
    if ([_raw2] call _extOk) exitWith {
        true
    };
};

[format ["sendViaOverwatch unavailable kind=%1 cmd=%2", _kind, _command], "WARN"] call comspec_sse_fnc_log;
false
