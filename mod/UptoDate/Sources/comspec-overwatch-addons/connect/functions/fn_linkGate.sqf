/*
    Frein partagé des envois vers Athena quand le compte est refusé ou non lié (accès refusé, session refusée, fiche refusée).
    Une seule pause pour tous les canaux (position, marqueurs, fiche opérateur, ordres, messages, formes, relais) :
    60 s, 120 s, 240 s… 15 min au plus, doublée à chaque nouvel échec après la fin de la pause. Rien ne s'affiche à l'écran ;
    une ligne au journal par changement d'état (début du refus, nouvelle cause, reprise), jamais une par tentative.
    Les envois rédigés par le joueur restent en file (fn_outboxPush, fn_queueMapMarker) et repartent à la reprise.
    Params : [opération, argument]
      "active"            : true tant que la pause court
      "blocks", commande  : true si cette commande est retenue par la pause (liaison, lectures et commandes locales passent)
      "deny", cause       : note un refus, démarre ou prolonge la pause. Retour : true si l'état a changé (à journaliser)
      "clear"             : reprise immédiate (liaison réussie, compte lié, Resynch). Retour : true si une pause était en cours
      "left"              : secondes restantes
*/
params [["_op", "active", [""]], ["_arg", ""]];
if (!hasInterface) exitWith { if (_op isEqualTo "left") then { 0 } else { false } };
private _until = missionNamespace getVariable ["COMSPEC_LinkGateUntil", 0];
if !(_until isEqualType 0) then { _until = 0; };
switch (_op) do {
    case "active": { diag_tickTime < _until };
    case "left": { (_until - diag_tickTime) max 0 };
    case "blocks": {
        if !(diag_tickTime < _until) exitWith { false };
        private _c = if (_arg isEqualType "") then { _arg } else { "" };
        !(
            _c in ["Init", "Connect", "AuthSteam", "AuthPassword", "RequestOtp", "VerifyOtp", "RestoreSession", "GetSessionRestore", "SetSteamId", "LinkBySteam", "RedeemGameLink", "PairStart", "PairStatus", "GetClientIp", "Logout", "Disconnect", "ClientDisconnect", "RepairLocalData", "ConnectC2", "Async", "Ping", "LogWrite", "LogSessionStart", "HashText", "UplinkOff"]
            || {(_c select [0, 3]) in ["Get", "Set"]}
            || {(_c select [0, 5]) in ["Music", "Local"]}
        )
    };
    case "deny": {
        private _on = _until > 0;
        private _step = missionNamespace getVariable ["COMSPEC_LinkGateStep", 60];
        if !(_step isEqualType 0) then { _step = 60; };
        // Refus pendant la pause : même état, rien à changer. Refus après la pause : l'attente double (plafond 15 min).
        if (diag_tickTime < _until) exitWith { false };
        _step = [60, (_step * 2) min 900] select _on;
        missionNamespace setVariable ["COMSPEC_LinkGateStep", _step, false];
        missionNamespace setVariable ["COMSPEC_LinkGateUntil", diag_tickTime + _step, false];
        // Tous les canaux qui lisent le frein d'API (position, véhicules, météo, flux vidéo…) s'arrêtent avec lui.
        missionNamespace setVariable ["COMSPEC_ApiBackoffUntil", diag_tickTime + _step, false];
        missionNamespace setVariable ["COMSPEC_VideoFeedsBackoffUntil", diag_tickTime + _step, false];
        missionNamespace setVariable ["COMSPEC_SendBackoffSec", _step, false];
        private _why = if (_arg isEqualType "") then { _arg } else { "" };
        private _last = missionNamespace getVariable ["COMSPEC_LinkGateWhy", ""];
        missionNamespace setVariable ["COMSPEC_LinkGateWhy", _why, false];
        // Journal : au début du refus et quand la cause change, pas à chaque doublement ni à chaque tentative.
        private _changed = !_on || {_why isNotEqualTo _last};
        if (_changed) then {
            ["WARN", "Athena", format ["Accès refusé (%1) : envois en pause, nouvel essai dans %2 s puis de plus en plus espacé", ["compte non lié ou refusé", _why] select (_why isNotEqualTo ""), round _step]] call comspec_overwatch_connect_fnc_log;
        };
        _changed
    };
    case "clear": {
        if (_until <= 0) exitWith { false };
        missionNamespace setVariable ["COMSPEC_LinkGateUntil", 0, false];
        missionNamespace setVariable ["COMSPEC_LinkGateStep", 60, false];
        missionNamespace setVariable ["COMSPEC_LinkGateWhy", "", false];
        missionNamespace setVariable ["COMSPEC_ApiBackoffUntil", 0, false];
        missionNamespace setVariable ["COMSPEC_VideoFeedsBackoffUntil", 0, false];
        missionNamespace setVariable ["COMSPEC_SendBackoffSec", 0, false];
        missionNamespace setVariable ["COMSPEC_OperatorProfileBackoffUntil", 0, false];
        ["INFO", "Athena", "Liaison rétablie : envois repris, files d'attente rejouées"] call comspec_overwatch_connect_fnc_log;
        true
    };
    default { false };
};
