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
// Liaison désactivée ou plus de téléphone : rien ne part (fn_uplinkHold de connect, embarqué).
if (_ext isEqualTo "COMSPECExtension" && {!isNil "comspec_overwatch_connect_fnc_uplinkHold"} && {[_cmd] call comspec_overwatch_connect_fnc_uplinkHold}) exitWith { ["ERR|uplink_hold", 0, 0] };
private _noAsync = missionNamespace getVariable ["COMSPEC_ATAK_ExtNoAsync", []];
// Hors contexte suspendable, seule une commande rapide (fn_extFast de connect) part en direct. Une lente (HTTP, disque :
// jusqu'à 8 s figé) repart en tâche de fond et l'appelant reçoit tout de suite ERR|unscheduled (signalé une fois).
if (!canSuspend && {!(_ext in _noAsync)} && {!isNil {missionNamespace getVariable "COMSPEC_ATAK_ExtensionEH"}}
    && {!isNil "comspec_overwatch_connect_fnc_extFast"} && {!([_cmd] call comspec_overwatch_connect_fnc_extFast)}) exitWith {
    private _seen = missionNamespace getVariable ["COMSPEC_ATAK_ExtUnscheduled", createHashMap];
    if !(_cmd in _seen) then {
        _seen set [_cmd, true];
        missionNamespace setVariable ["COMSPEC_ATAK_ExtUnscheduled", _seen];
        ["WARN", "EXT", format ["%1 demandé hors contexte suspendable : relancé en tâche de fond", _cmd]] call comspec_atak_native_fnc_log;
    };
    [_ext, _cmd, _args, _timeout] spawn comspec_atak_native_fnc_extAwait;
    ["ERR|unscheduled", 0, 0]
};
// Sans gestionnaire de rappel (menu principal, avant le postInit) la réponse ne reviendrait jamais : appel direct.
private _pt = diag_tickTime;
if (!canSuspend || {_ext in _noAsync} || {isNil {missionNamespace getVariable "COMSPEC_ATAK_ExtensionEH"}}) exitWith { private _r = _ext callExtension [_cmd, _args]; if (missionNamespace getVariable ["COMSPEC_ATAK_Prof", false]) then { ["DLL " + _cmd, _pt] call comspec_atak_native_fnc_perfAcc; }; _r };
// Bloc non ordonnancé (isNil {}), comme dans fn_extAwait de connect : deux scripts interrompus entre la lecture et
// l'écriture du compteur obtenaient le même ticket (l'un recevait la réponse de l'autre, l'autre attendait jusqu'au délai),
// et deux tables d'attente créées en même temps en perdaient une (son rappel n'arrivait jamais).
private _n = 0;
private _pending = createHashMap;
private _ticket = "";
isNil {
    _n = (missionNamespace getVariable ["COMSPEC_ATAK_ExtTicketN", 0]) + 1;
    missionNamespace setVariable ["COMSPEC_ATAK_ExtTicketN", _n];
    _pending = missionNamespace getVariable ["COMSPEC_ATAK_ExtPending", _pending];
    missionNamespace setVariable ["COMSPEC_ATAK_ExtPending", _pending];
    _ticket = format ["%1_%2", round diag_tickTime, _n];
    // Ticket en attente : false ; le rappel y range [résultat] (un résultat arrivé après l'abandon est ignoré).
    _pending set [_ticket, false];
};
private _r = _ext callExtension ["Async", [_ticket, _cmd] + _args];
if (missionNamespace getVariable ["COMSPEC_ATAK_Prof", false]) then { ["DLL Async " + _cmd, _pt] call comspec_atak_native_fnc_perfAcc; };
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
