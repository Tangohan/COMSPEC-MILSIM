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

private _payload = _input;
private _command = "SendSSE";
private _kind = "GENERIC";

if ((_input getOrDefault ["command", ""]) != "") then {
    _command = _input get "command";
    _kind = _input getOrDefault ["kind", "GENERIC"];
    _payload = _input getOrDefault ["payload", _input];
};

private _json = if ((toUpper _command) isEqualTo "SUBMITSSEPERSON" && {!isNil "comspec_sse_fnc_toJsonPerson"}) then {
    [_payload] call comspec_sse_fnc_toJsonPerson
} else {
    [_payload] call comspec_sse_fnc_toJsonApprox
};

private _preferExt = missionNamespace getVariable ["comspec_sse_preferExtension", true];

// Ne pas utiliser str sur le statut : str "OK" vaut "\"OK\"" et râte la comparaison.
private _extOk = {
    params ["_raw"];
    if (!(_raw isEqualType "") || {_raw isEqualTo ""}) exitWith { false };
    private _ok = false;
    if ((count _raw) >= 4 && {(_raw select [0, 2]) isEqualTo "["""}) then {
        private _parsed = parseSimpleArray _raw;
        if (_parsed isEqualType [] && {(count _parsed) >= 1}) then {
            private _s0 = _parsed select 0;
            private _status = if (_s0 isEqualType "") then { _s0 } else { format ["%1", _s0] };
            _ok = (toUpper _status) isEqualTo "OK";
        };
    };
    if (!_ok) then {
        private _u = toUpper _raw;
        _ok = ((_u select [0, 2]) isEqualTo "OK") && {(_u find "ERROR") < 0};
    };
    _ok
};

private _needsPersonApi = (
    (toUpper _command) in ["SUBMITSSEPERSON", "SUBMITSSEBIOMETRICSSIM"]
) || {(toUpper _kind) in ["PERSON", "BIOMETRICS"]};

private _needsTypedApi = _needsPersonApi
    || {(toUpper _command) in ["SENDSSE", "SUBMITSSEDIGITAL"]}
    || {(toUpper _kind) in ["DIGITAL", "GENERIC"]};

if (_preferExt) exitWith {
    private _isAthenaId = {
        params ["_s"];
        if (!(_s isEqualType "")) exitWith { false };
        if (_s isEqualTo "" || {_s isEqualTo "0"}) exitWith { false };
        private _ok = true;
        {
            if (_x < 48 || {_x > 57}) then { _ok = false };
        } forEach toArray _s;
        _ok
    };
    private _extArgs = [_json];
    if ((toUpper _command) isEqualTo "SUBMITSSEBIOMETRICSSIM") then {
        private _pid = _payload getOrDefault ["athena_person_id", ""];
        if (_pid isEqualTo "") then { _pid = _payload getOrDefault ["person_id", ""]; };
        if (_pid isEqualTo "") then { _pid = _payload getOrDefault ["id", ""]; };
        if (!(_pid isEqualType "")) then { _pid = str (floor _pid); };
        if (!([_pid] call _isAthenaId)) then {
            ["sendViaOverwatch skip bio — fiche pas encore connue au registre", "WARN"] call comspec_sse_fnc_log;
            false
        } else {
            _extArgs = [_pid, _json];
            [format [
                "TX %1 names=%2/%3/%4 bytes=%5",
                _command,
                _payload getOrDefault ["first_name", ""],
                _payload getOrDefault ["last_name", ""],
                _payload getOrDefault ["alias", ""],
                count _json
            ]] call comspec_sse_fnc_log;
            private _raw = [_command, _extArgs] call comspec_sse_fnc_extensionCall;
            if ([_raw] call _extOk) exitWith {
                [format ["sendViaOverwatch OK ext %1", _command]] call comspec_sse_fnc_log;
                true
            };
            [format ["sendViaOverwatch FAIL ext %1 raw=%2", _command, _raw], "WARN"] call comspec_sse_fnc_log;
            false
        };
    } else {
        [format [
            "TX %1 names=%2/%3/%4 bytes=%5",
            _command,
            _payload getOrDefault ["first_name", ""],
            _payload getOrDefault ["last_name", ""],
            _payload getOrDefault ["alias", ""],
            count _json
        ]] call comspec_sse_fnc_log;
        private _raw = [_command, _extArgs] call comspec_sse_fnc_extensionCall;
        if ([_raw] call _extOk) exitWith {
            [format ["sendViaOverwatch OK ext %1", _command]] call comspec_sse_fnc_log;
            true
        };
        [format ["sendViaOverwatch FAIL ext %1 raw=%2", _command, _raw], "WARN"] call comspec_sse_fnc_log;
        false
    };
};

if (!_needsTypedApi && {!isNil "comspec_overwatch_connect_fnc_sendIntel"}) exitWith {
    private _sseUid = _payload getOrDefault ["sse_uid", ""];
    if (_sseUid isEqualTo "") then { _sseUid = _payload getOrDefault ["record_id", "?"]; };
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

[format ["sendViaOverwatch unavailable kind=%1 cmd=%2", _kind, _command], "WARN"] call comspec_sse_fnc_log;
false
