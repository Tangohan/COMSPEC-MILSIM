/*
    Restaure la file offline depuis le profil (dédupliquée par clé d’idempotence).
*/
private _serial = profileNamespace getVariable ["comspec_sse_txQueue_v1", []];
if (!(_serial isEqualType []) || {count _serial == 0}) exitWith {
    if (isNil "comspec_sse_txQueue") then { comspec_sse_txQueue = []; };
    0
};

private _restored = [];
private _seen = [];
private _stale = 0;
private _case = [] call comspec_sse_fnc_getCaseReference;
{
    private _hm = createHashMap;
    if (_x isEqualType []) then {
        { _x params ["_k", "_v"]; _hm set [_k, _v]; } forEach _x;
    };
    private _idem = _hm getOrDefault ["idempotency_key", ""];
    if (_idem isEqualTo "") then {
        private _pl = _hm getOrDefault ["payload", createHashMap];
        if (_pl isEqualType createHashMap) then {
            _idem = _pl getOrDefault ["idempotency_key", ""];
        };
    };
    // Entrée d'une autre mission ou d'un autre dossier (ou ancienne entrée sans origine) : abandonnée.
    private _fromHere = (_hm getOrDefault ["txMission", ""]) isEqualTo missionName
        && {_case isEqualTo "" || {(_hm getOrDefault ["txCase", ""]) isEqualTo _case}}
        && {(_hm getOrDefault ["txStatus", "QUEUED"]) isNotEqualTo "TRANSMITTED"};
    if (!_fromHere) then { _stale = _stale + 1; };
    if (!_fromHere || {_idem != "" && {_idem in _seen}}) then {
        // skip doublon / autre opération
    } else {
        if (_idem != "") then { _seen pushBack _idem; };
        _hm set ["txStatus", "QUEUED"];
        _restored pushBack _hm;
    };
} forEach _serial;

comspec_sse_txQueue = _restored;
missionNamespace setVariable ["comspec_sse_txQueue", comspec_sse_txQueue];
if (_stale > 0) then {
    [format ["restoreQueue : %1 envoi(s) d'une autre opération abandonné(s)", _stale], "WARN"] call comspec_sse_fnc_log;
    [] call comspec_sse_fnc_persistQueue;
};
[format ["restoreQueue n=%1", count _restored], "WARN"] call comspec_sse_fnc_log;
count _restored
