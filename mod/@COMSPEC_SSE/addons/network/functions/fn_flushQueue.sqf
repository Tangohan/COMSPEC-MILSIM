/*
    Vide la file offline vers Overwatch / Athena.
    Batch limité + backoff + plafond de tentatives.
*/
if (isNil "comspec_sse_txQueue") exitWith { 0 };
if (count comspec_sse_txQueue == 0) exitWith { 0 };

private _maxAttempts = 5;
// Trois envois par passage au plus, arrêt au premier échec : avec un Athena lent, huit envois d'affilée figeaient le jeu.
private _batchMax = 3;
private _remaining = [];
private _sent = 0;
private _dropped = 0;
private _processed = 0;
// Parcours d'une copie superficielle (mêmes HashMap) : sendViaOverwatch se suspend (extAwait) et queueOffline peut
// entre-temps ajouter ou retirer (deleteAt) des entrées ; parcourir le tableau vivant sautait ou écrasait ces entrées.
private _work = comspec_sse_txQueue apply { _x };

// Biométrie sans id Athena (fiche PERSON pas encore enregistrée) : sendViaOverwatch la refuse sans appel réseau.
// Elle attend (WAIT_PERSON) sans compter de tentative ni stopper le lot ; personTransmitted lui rend l'id.
private _bioWaitsPerson = {
    params ["_e"];
    if ((toUpper (_e getOrDefault ["kind", ""])) isNotEqualTo "BIOMETRICS") exitWith { false };
    private _p = _e getOrDefault ["payload", createHashMap];
    if (!(_p isEqualType createHashMap)) exitWith { false };
    private _pid = _p getOrDefault ["athena_person_id", ""];
    if (_pid isEqualTo "") then { _pid = _p getOrDefault ["person_id", ""]; };
    if (_pid isEqualTo "") then { _pid = _p getOrDefault ["id", ""]; };
    if (!(_pid isEqualType "")) then { _pid = str (floor _pid); };
    if (_pid isEqualTo "" || {_pid isEqualTo "0"}) exitWith { true };
    ((toArray _pid) findIf { _x < 48 || {_x > 57} }) >= 0
};

{
    if (_processed >= _batchMax) then {
        _remaining pushBack _x;
    } else {
        if ([_x] call _bioWaitsPerson) then {
            _x set ["txStatus", "WAIT_PERSON"];
            _remaining pushBack _x;
        } else {
            private _attempts = _x getOrDefault ["txAttempts", 0];
            private _retryAfter = _x getOrDefault ["txRetryAfter", 0];
            if (_retryAfter > time) then {
                _remaining pushBack _x;
            } else {
                _processed = _processed + 1;
                if (_attempts >= _maxAttempts) then {
                    _x set ["txStatus", "FAILED"];
                    _dropped = _dropped + 1;
                    [format ["flushQueue drop after %1 attempts kind=%2", _attempts, _x getOrDefault ["kind", "?"]], "WARN"] call comspec_sse_fnc_log;
                } else {
                    private _ok = [_x] call comspec_sse_fnc_sendViaOverwatch;
                    if (_ok) then {
                        _sent = _sent + 1;
                        _x set ["txStatus", "TRANSMITTED"];
                        _x set ["txAttempts", _attempts + 1];
                        // Fiche personne partie depuis la file : même suite que submitPersonRecord (TRANSMITTED, id Athena,
                        // biométries en attente débloquées).
                        if ((toUpper (_x getOrDefault ["kind", ""])) isEqualTo "PERSON") then {
                            private _nid = _x getOrDefault ["entity_netid", ""];
                            private _ent = if (_nid isEqualType "" && {_nid isNotEqualTo ""}) then { objectFromNetId _nid } else { objNull };
                            private _rid = _x getOrDefault ["record_id", "?"];
                            if (!(_rid isEqualType "")) then { _rid = str _rid; };
                            [_ent, _rid] call comspec_sse_fnc_personTransmitted;
                        };
                    } else {
                        private _next = _attempts + 1;
                        private _exp = (_next - 1) min 3;
                        private _delay = 15 * (2 ^ _exp);
                        _x set ["txAttempts", _next];
                        _x set ["txStatus", "QUEUED"];
                        _x set ["txRetryAfter", time + _delay];
                        _remaining pushBack _x;
                        _processed = _batchMax;
                    };
                };
            };
        };
    };
} forEach _work;

// Fusion avec la file vivante : une entrée de _remaining retirée entre-temps par queueOffline (remplacée par un
// envoi plus récent, ou plafond) reste retirée ; les entrées ajoutées pendant le passage sont gardées à la suite.
private _live = comspec_sse_txQueue;
_remaining = _remaining select { private _e = _x; (_live findIf { _x isEqualRef _e }) >= 0 };
private _fresh = _live select { private _e = _x; (_work findIf { _x isEqualRef _e }) < 0 };
comspec_sse_txQueue = _remaining + _fresh;
missionNamespace setVariable ["comspec_sse_txQueue", comspec_sse_txQueue];
// Profil réécrit dès que la file change, même vide : sinon les envois déjà transmis
// restaient sur le disque et repartaient à la mission suivante.
if ((_sent + _dropped) > 0) then { [] call comspec_sse_fnc_persistQueue; };
if (_dropped > 0) then {
    ["comspec_sse_txDropped", [_dropped]] call CBA_fnc_localEvent;
};
[format ["flushQueue sent=%1 remaining=%2 dropped=%3 batch=%4", _sent, count _remaining, _dropped, _processed]] call comspec_sse_fnc_log;
_sent
