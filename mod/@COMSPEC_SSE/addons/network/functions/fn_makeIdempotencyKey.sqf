/*
    Clé d’idempotence stable (même enregistrement = même clé).
    [_prefix, _recordId] call comspec_sse_fnc_makeIdempotencyKey

    Pas de sel temporel : indispensable pour les retries offline / rejeux.
*/
params [
    ["_prefix", "SSE", [""]],
    ["_recordId", "", [""]]
];

private _p = toUpper (trim _prefix);
private _r = trim _recordId;
if (_r isEqualTo "") then {
    _r = format ["TMP-%1-%2", round (diag_tickTime * 1000), floor random 99999];
};

// Normaliser caractères fragiles
_r = _r replaceString [" ", "_"];
_r = _r replaceString ["/", "-"];

format ["%1-%2", _p, _r]
