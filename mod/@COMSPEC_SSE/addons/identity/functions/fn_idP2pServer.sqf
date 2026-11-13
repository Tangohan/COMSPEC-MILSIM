/*
    Contrôle et présentation des papiers entre joueurs (SERVEUR). Entrée : fn_idServer (ops ask sur un joueur, p2pAnswer,
    p2pPresent, p2pZeus), qui a déjà vérifié le poste demandeur, l'anti-flood et la durée de la barre de progression.
    Revérifié ici : le poste qui appelle est celui de l'unité (remoteExecutedOwner), rien n'est cru sur parole.
    [_op, _args] call comspec_sse_fnc_idP2pServer
      ask      [contrôleur, contrôlé]                        demande : fenêtre chez le contrôlé, délai de réponse
      answer   [contrôlé, contrôleur, accept | refuse, classes, show | give, motif]
      present  [porteur, destinataire, classes, show | give]   présentation d'initiative (ou réponse à une demande en attente)
      legacy   [porteur, destinataire]      -> Boolean : la présentation d'un seul document (menu ACE) est-elle permise ?
                                            Une demande en attente de ce destinataire est alors close « acceptée ».
      tick     [contrôleur, contrôlé, clé, numéro]   échéance (appel local seulement) : « pas de réponse »
      zeus     [zeus, 0 | 1 | 2]            choix du Zeus pour la mission : suivre le réglage, autoriser, interdire
    Règles (fn_idP2pRules) : tous deux à pied, distance du réglage (plus 1 m de latence), vue dégagée entre les deux
    têtes, papiers réellement portés, une demande à la fois par paire, 8 s entre deux demandes (30 s après un refus),
    10 s entre deux présentations d'initiative à la même personne.
    Réponses (fn_idClient « p2p », fn_idP2pClient) : request · asked · result · shown · sent · denied · expired.
    Rien de diffusé : chaque message part au seul poste concerné (pas de file JIP) ; le choix du Zeus est une variable
    publique (comspec_sse_idP2pZeus), donc connue d'un joueur qui arrive en cours de partie.
    Évènements CBA serveur repris du contrôle d'identité : comspec_sse_idPresented, comspec_sse_idRefused.
*/
params [["_op", "", [""]], ["_a", [], [[]]]];
if (!isServer) exitWith { false };
_a params [["_me", objNull, [objNull]]];
if (isNull _me) exitWith { false };
if (isRemoteExecuted && {isMultiplayer} && {_op isEqualTo "tick" || {(owner _me) isNotEqualTo remoteExecutedOwner}}) exitWith { false };
private _reg = ["init"] call comspec_sse_fnc_idRegistry;
private _p2p = _reg getOrDefault ["p2p", createHashMap];
_reg set ["p2p", _p2p];
private _rules = { _this call comspec_sse_fnc_idP2pRules };
private _on = ["enabled", [missionNamespace getVariable ["comspec_sse_id_p2pEnabled", true], missionNamespace getVariable ["comspec_sse_idP2pZeus", 0]]] call _rules;
private _canRefuse = (missionNamespace getVariable ["comspec_sse_id_p2pRefuse", true]) isEqualTo true;
private _canPresent = (missionNamespace getVariable ["comspec_sse_id_p2pPresent", true]) isEqualTo true;
private _timeout = ["timeout", missionNamespace getVariable ["comspec_sse_id_p2pTimeout", 20]] call _rules;
private _maxDist = ["distance", missionNamespace getVariable ["comspec_sse_id_p2pDistance", 3]] call _rules;
private _tokenTime = missionNamespace getVariable ["comspec_sse_id_tokenTime", 120];

private _send = {
    params ["_unit", "_sub", "_data"];
    if (isNull _unit || {!isPlayer _unit}) exitWith {};
    if (local _unit && {hasInterface}) then { ["p2p", [_sub, _data]] call comspec_sse_fnc_idClient; } else { ["p2p", [_sub, _data]] remoteExecCall ["comspec_sse_fnc_idClient", owner _unit]; };
};
private _rangeOf = {
    params ["_x1", "_x2"];
    private _vis = [_x1, "VIEW", _x2] checkVisibility [eyePos _x1, eyePos _x2];
    ["range", [_x1 distance _x2, _maxDist + 1, _vis, isNull objectParent _x1 && {isNull objectParent _x2}]] call _rules
};
// Demande en attente de _from vers _to : [fiche, vrai si encore dans les temps (1,5 s de latence)].
private _pendingOf = {
    params ["_from", "_to"];
    private _r = _p2p getOrDefault [format ["%1|%2", netId _from, netId _to], []];
    [_r, _r isNotEqualTo [] && {(_r select 0) isEqualTo "pending"} && {((_r select 1) + 1.5) >= time}]
};
// Papiers tendus ou remis : jeton de présentation par document (fn_idServer « view » et « check »), exemplaire déplacé
// si remis. -> [[docId, libellé, type, pays, numéro, nom, prénom, naissance]…] : ce qui est imprimé, jamais la vérité.
private _hand = {
    params ["_from", "_to", "_classes", "_mode"];
    private _owned = ([_from, 0] call comspec_sse_fnc_idDocsOf) apply { _x select 0 };
    private _out = [];
    {
        private _d = ["ofClass", [_x]] call comspec_sse_fnc_idRegistry;
        if ((count _d) > 0) then {
            (_reg get "tokens") set [format ["%1|%2", owner _to, _d get "id"], [time + _tokenTime, _from]];
            if (_mode isEqualTo "give") then { [_x, _from, _to] call comspec_sse_fnc_idTransfer; };
            _out pushBack [_d get "id", format ["%1 %2 · %3 %4", ["label", _d get "type"] call comspec_sse_fnc_idDocTypes, _d get "country", toUpper (_d get "last"), _d get "first"],
                _d getOrDefault ["type", ""], _d getOrDefault ["country", ""], _d getOrDefault ["number", ""], _d getOrDefault ["last", ""], _d getOrDefault ["first", ""], _d getOrDefault ["dob", ""]];
            ["comspec_sse_idPresented", [_from, _d get "id", _to]] call CBA_fnc_localEvent;
        };
    } forEach (["subset", [_classes, _owned, 12]] call _rules);
    // Demande d'une IA (checkpoint) à qui je viens de répondre : close, comme la présentation du menu ACE.
    if (_out isNotEqualTo []) then {
        private _req = _reg get "requests";
        _req set [netId _from, (_req getOrDefault [netId _from, []]) select { (_x select 2) > time && {(_x select 1) isNotEqualTo (netId _to)} }];
    };
    _out
};

switch (_op) do {
    case "ask": {
        _a params ["", ["_t", objNull, [objNull]]];
        if (isNull _t || {_t isEqualTo _me} || {!alive _t} || {!isPlayer _t}) exitWith { false };
        if (!_on) exitWith { [_me, "denied", [_t, "off"]] call _send; false };
        if (_t getVariable ["ACE_isUnconscious", false]) exitWith { [_me, "denied", [_t, "unable"]] call _send; false };
        private _why = [_me, _t] call _rangeOf;
        if (_why isNotEqualTo "") exitWith { [_me, "denied", [_t, _why]] call _send; false };
        private _key = format ["%1|%2", netId _me, netId _t];
        private _r = _p2p getOrDefault [_key, []];
        if (([_me, _t] call _pendingOf) select 1) exitWith { [_me, "denied", [_t, "pending"]] call _send; false };
        if (_r isNotEqualTo [] && {!(["rate", [time, _r select 3, _r select 0]] call _rules)}) exitWith { [_me, "denied", [_t, "rate"]] call _send; false };
        private _serial = (_reg getOrDefault ["p2pSerial", 0]) + 1;
        _reg set ["p2pSerial", _serial];
        // [état, échéance, numéro, fin (début tant qu'elle court)]
        _p2p set [_key, ["pending", time + _timeout, _serial, time]];
        [_t, "request", [_me, _timeout, _canRefuse, _serial]] call _send;
        [_me, "asked", [_t, _timeout, _serial]] call _send;
        [{ ["tick", _this] call comspec_sse_fnc_idP2pServer; }, [_me, _t, _key, _serial], _timeout + 1.6] call CBA_fnc_waitAndExecute;
        true
    };

    case "tick": {
        _a params ["", ["_t", objNull, [objNull]], ["_key", "", [""]], ["_serial", 0, [0]]];
        private _r = _p2p getOrDefault [_key, []];
        if (_r isEqualTo [] || {(_r select 2) isNotEqualTo _serial}) exitWith { false };
        if ((["next", [_r select 0, "tick", time, (_r select 1) + 1.5, _canRefuse]] call _rules) isNotEqualTo "timeout") exitWith { false };
        _r set [0, "timeout"];
        _r set [3, time];
        [_me, "result", [_t, "timeout", "", [], "show", _serial]] call _send;
        [_t, "expired", [_me, _serial]] call _send;
        true
    };

    case "answer": {
        _a params ["", ["_c", objNull, [objNull]], ["_ans", "", [""]], ["_classes", [], [[]]], ["_mode", "show", [""]], ["_reason", "", [""]]];
        if (isNull _c) exitWith { false };
        private _r = _p2p getOrDefault [format ["%1|%2", netId _c, netId _me], []];
        if (_r isEqualTo []) exitWith { false };
        private _next = ["next", [_r select 0, ["refuse", "accept"] select (_ans isEqualTo "accept"), time, (_r select 1) + 1.5, _canRefuse]] call _rules;
        // Refus interdit par la mission : dit au contrôlé, la demande court toujours.
        if (_next isEqualTo "" && {(_r select 0) isEqualTo "pending"}) exitWith { [_me, "denied", [_c, "noRefuse"]] call _send; false };
        if !(_next in ["accepted", "refused"]) exitWith { false };
        if (_next isEqualTo "refused") exitWith {
            _r set [0, "refused"];
            _r set [3, time];
            [_c, "result", [_me, "refused", ["reason", _reason] call _rules, [], "show", _r select 2]] call _send;
            [_me, "sent", [_c, 0, "refused"]] call _send;
            ["comspec_sse_idRefused", [_me, _c]] call CBA_fnc_localEvent;
            true
        };
        // Accepté : encore face à face, et des papiers réellement portés. Sinon la demande court toujours.
        private _why = [_me, _c] call _rangeOf;
        if (_why isNotEqualTo "") exitWith { [_me, "denied", [_c, _why]] call _send; false };
        _mode = ["mode", _mode] call _rules;
        private _docs = [_me, _c, _classes, _mode] call _hand;
        if (_docs isEqualTo []) exitWith { [_me, "denied", [_c, "docs"]] call _send; false };
        _r set [0, "accepted"];
        _r set [3, time];
        [_c, "result", [_me, "accepted", "", _docs, _mode, _r select 2]] call _send;
        [_me, "sent", [_c, count _docs, _mode]] call _send;
        true
    };

    case "present": {
        _a params ["", ["_to", objNull, [objNull]], ["_classes", [], [[]]], ["_mode", "show", [""]]];
        if (isNull _to || {_to isEqualTo _me} || {!alive _to}) exitWith { false };
        ([_to, _me] call _pendingOf) params ["_r", "_pending"];
        // Vers un joueur : réglages de la mission, sauf pour répondre à sa demande. Vers une IA (checkpoint) : toujours.
        if (isPlayer _to && {!_pending} && {!_on || {!_canPresent}}) exitWith { [_me, "denied", [_to, "noPresent"]] call _send; false };
        private _why = [_me, _to] call _rangeOf;
        if (_why isNotEqualTo "") exitWith { [_me, "denied", [_to, _why]] call _send; false };
        private _shown = _reg getOrDefault ["p2pShown", createHashMap];
        _reg set ["p2pShown", _shown];
        private _pair = format ["%1|%2", netId _me, netId _to];
        if (!_pending && {(time - (_shown getOrDefault [_pair, -1e6])) < 10}) exitWith { [_me, "denied", [_to, "rate"]] call _send; false };
        _mode = ["mode", _mode] call _rules;
        private _docs = [_me, _to, _classes, _mode] call _hand;
        if (_docs isEqualTo []) exitWith { [_me, "denied", [_to, "docs"]] call _send; false };
        _shown set [_pair, time];
        if (_pending) then {
            _r set [0, "accepted"];
            _r set [3, time];
            [_to, "result", [_me, "accepted", "", _docs, _mode, _r select 2]] call _send;
        } else {
            [_to, "shown", [_me, _docs, _mode]] call _send;
        };
        [_me, "sent", [_to, count _docs, _mode]] call _send;
        true
    };

    case "legacy": {
        _a params ["", ["_to", objNull, [objNull]]];
        if (isNull _to || {!isPlayer _to}) exitWith { true };
        ([_to, _me] call _pendingOf) params ["_r", "_pending"];
        if (_pending) exitWith {
            _r set [0, "accepted"];
            _r set [3, time];
            // Le document part par le jeton du menu ACE (fn_idServer « present ») : rien à rouvrir chez le contrôleur.
            [_to, "result", [_me, "accepted", "", [], "show", _r select 2]] call _send;
            true
        };
        _on && {_canPresent}
    };

    case "zeus": {
        _a params ["", ["_v", 0, [0]]];
        if !([_me] call comspec_sse_fnc_isAuthority) exitWith {
            diag_log text format ["[COMSPEC SSE] Identité : choix du Zeus sur les contrôles entre joueurs refusé à %1.", name _me];
            false
        };
        _v = ((round _v) max 0) min 2;
        missionNamespace setVariable ["comspec_sse_idP2pZeus", _v, true];
        [_me, "zeus", [_v]] call _send;
        true
    };
    default { false };
};
