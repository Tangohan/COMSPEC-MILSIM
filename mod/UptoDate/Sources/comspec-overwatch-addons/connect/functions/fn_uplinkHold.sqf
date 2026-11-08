/*
    Coupure centrale des transmissions vers Athena (appelée par fn_extAwait de connect et de l'ATAK natif).
    Params : [commande DLL]. Retour : true = ne rien envoyer.
      - liaison Athena désactivée (réglage d'addon « comspec_overwatch_enabled » ou service « Non » / full local) :
        tout est coupé sauf les commandes purement locales (journal, empreinte, version) ;
      - joueur sans téléphone ATAK (ni balise téléphone Zeus) : tout est coupé sauf la connexion au compte (dont le code
        de connexion PairStart / PairStatus : bloqué, il laissait « Serveur Athena : injoignable » après la connexion).
    Le changement d'état est écrit une seule fois au journal (pas de spam).
*/
params [["_cmd", ""]];
if (!hasInterface) exitWith { false };
if (_cmd in ["LogWrite", "LogSessionStart", "HashText", "GetExtensionVersion", "GetVersion", "Ping", "Async"]) exitWith { false };
if ([] call comspec_overwatch_connect_fnc_uplinkOff) exitWith {
    if !(missionNamespace getVariable ["COMSPEC_UplinkHoldOff", false]) then {
        missionNamespace setVariable ["COMSPEC_UplinkHoldOff", true];
        // « Oui » puis « Non » en cours de session : la DLL oublie adresse et clés, sa file ne repart plus.
        "COMSPECExtension" callExtension ["UplinkOff", []];
        ["INFO", "Athena", "Liaison Athena désactivée : mod en local, aucune transmission"] call comspec_overwatch_connect_fnc_log;
    };
    true
};
missionNamespace setVariable ["COMSPEC_UplinkHoldOff", false];
if (_cmd in ["Init", "Connect", "AuthSteam", "AuthPassword", "RequestOtp", "VerifyOtp", "RestoreSession", "GetSessionRestore", "SetSteamId", "LinkBySteam", "RedeemGameLink", "PairStart", "PairStatus", "GetClientIp", "Logout", "Disconnect"]) exitWith { false };
// Téléphone : relu au plus une fois par seconde (appelé à chaque envoi).
private _cache = missionNamespace getVariable ["COMSPEC_UplinkHoldCache", [-1e9, false]];
if ((diag_tickTime - (_cache select 0)) < 1) exitWith { _cache select 1 };
private _hold = !isNull player && {alive player} && {!(
    ([player] call comspec_overwatch_connect_fnc_hasTerminal)
    || {[player, "COMSPEC_PhoneTrack"] call comspec_overwatch_connect_fnc_isObjectFlag}
)};
if (_hold isNotEqualTo (_cache select 1)) then {
    ["INFO", "Athena", ["Téléphone repris : transmissions reprises", "Plus de téléphone ATAK : plus aucune transmission pour ce joueur"] select _hold] call comspec_overwatch_connect_fnc_log;
};
missionNamespace setVariable ["COMSPEC_UplinkHoldCache", [diag_tickTime, _hold]];
_hold
