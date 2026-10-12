/*
    Appel à la DLL sans figer le jeu. callExtension bloque le thread du jeu le temps de la commande, même
    lancé depuis un spawn : une requête HTTP lente vers Athena (jusqu'à 8 s) figeait toute la partie.
    Avec une DLL qui connaît « Async » (COMSPEC Link 2.0.65+), la commande part sur un thread à part et son
    résultat revient par le rappel de la DLL (fn_extensionCallback) ; ce script attend sans bloquer.
    Sans elle (DLL plus ancienne) ou hors contexte suspendable : appel direct, comme avant.
      [extension, commande, args] call comspec_atak_native_fnc_extAwait
    Renvoie la même forme que callExtension : [résultat, 0, 0].
*/
params [["_ext", "COMSPECExtension"], ["_cmd", ""], ["_args", []], ["_timeout", 20]];
private _noAsync = missionNamespace getVariable ["COMSPEC_ATAK_ExtNoAsync", []];
if (!canSuspend || {_ext in _noAsync}) exitWith { _ext callExtension [_cmd, _args] };
private _n = (missionNamespace getVariable ["COMSPEC_ATAK_ExtTicketN", 0]) + 1;
missionNamespace setVariable ["COMSPEC_ATAK_ExtTicketN", _n];
private _ticket = format ["%1_%2", round diag_tickTime, _n];
// Ticket en attente : false ; le rappel y range [résultat] (un résultat arrivé après l'abandon est ignoré).
private _pending = missionNamespace getVariable ["COMSPEC_ATAK_ExtPending", createHashMap];
missionNamespace setVariable ["COMSPEC_ATAK_ExtPending", _pending];
_pending set [_ticket, false];
private _r = _ext callExtension ["Async", [_ticket, _cmd] + _args];
private _q = if (_r isEqualType []) then { _r param [0, ""] } else { _r };
if !(_q isEqualType "") then { _q = str _q; };
if ((_q select [0, 7]) isNotEqualTo "QUEUED|") exitWith {
    _pending deleteAt _ticket;
    // DLL sans « Async » (réponse vide) : retenu pour la partie, plus d'essai.
    if (_q isEqualTo "") then { _noAsync pushBackUnique _ext; missionNamespace setVariable ["COMSPEC_ATAK_ExtNoAsync", _noAsync]; };
    _ext callExtension [_cmd, _args]
};
private _t0 = diag_tickTime;
waitUntil { sleep 0.05; (_pending getOrDefault [_ticket, false]) isNotEqualTo false || {diag_tickTime - _t0 > _timeout} };
private _got = _pending getOrDefault [_ticket, false];
_pending deleteAt _ticket;
[if (_got isEqualTo false) then { "ERR|timeout" } else { _got select 0 }, 0, 0]
