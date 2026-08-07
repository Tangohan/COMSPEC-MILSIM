/*
    Adapter Overwatch / extension — V0.4.
    Accepte soit un payload plat (legacy submitRecord), soit une envelope {kind,command,payload}.
    [_payloadOrEnvelope] call comspec_sse_fnc_sendViaOverwatch
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

// 1) Extension typée
if (_preferExt) then {
    private _raw = [_command, [_json]] call comspec_sse_fnc_extensionCall;
    if (_raw isEqualType "" && {_raw != ""} ) then {
        private _low = toLower _raw;
        if ((_low find "error") < 0 && {(_low find "fail") < 0} && {(_low find "unknown") < 0} && {(_low find "not implemented") < 0}) exitWith {
            [format ["sendViaOverwatch OK ext %1", _command]] call comspec_sse_fnc_log;
            true
        };
    };
};

// 2) Overwatch sendIntel (toujours disponible si Overwatch chargé)
if (!isNil "comspec_overwatch_connect_fnc_sendIntel") exitWith {
    private _text = format [
        "SSE|%1|%2|%3|%4",
        _kind,
        _payload getOrDefault ["sse_uid", _payload getOrDefault ["record_id", "?"]],
        _payload getOrDefault ["case_reference", [] call comspec_sse_fnc_getCaseReference],
        _payload getOrDefault ["quality", _payload getOrDefault ["match_confidence", 0]]
    ];
    private _score = ((_payload getOrDefault ["quality", 70]) / 100) max 0.1 min 1;
    [player, "HUMINT", _text, _payload getOrDefault ["idempotency_key", ""], "INFANTRY", _score] call comspec_overwatch_connect_fnc_sendIntel;
    ["sendViaOverwatch OK via sendIntel"] call comspec_sse_fnc_log;
    true
};

// 3) Extension générique SendSSE
private _raw2 = ["SendSSE", [_json]] call comspec_sse_fnc_extensionCall;
if (_raw2 isEqualType "" && {_raw2 != ""} && {((toLower _raw2) find "error") < 0} && {((toLower _raw2) find "unknown") < 0}) exitWith {
    true
};

[format ["sendViaOverwatch unavailable kind=%1", _kind], "WARN"] call comspec_sse_fnc_log;
false
