/*
    Serveur de la communauté : prévient le joueur relié à Athena qui joue sur un autre serveur que ceux déclarés
    par sa communauté (site : Gestion › Mod ATAK › Serveurs de jeu). Ses positions et comptes rendus partent quand
    même vers sa communauté, d'où l'avertissement.
      ["serverInit"]  serveur dédié ou hôte : demande à Athena son adresse publique (COMSPEC Link 2.0.75,
                      Server.PublicIp) et la diffuse (COMSPEC_ATAK_ServerIp). Sans DLL côté serveur (Linux) : le nom
                      du serveur sert de repli côté Athena.
      ["check"]       client : envoie adresse, nom du serveur, mission et carte (Server.Presence) ; toutes les 10 min.
    Résultat gardé dans COMSPEC_ATAK_ServerPresence [état, serveur reconnu, communauté, message, heure] (app Réseau).
    État : ok, away (autre serveur), none (aucun serveur déclaré), "" (inconnu : solo, DLL ancienne, hors liaison).
*/
params [["_act", "check"]];
switch (_act) do {
    case "serverInit": {
        if (!isServer || {!isMultiplayer}) exitWith {};
        [] spawn {
            // Le serveur dédié n'a pas le rappel des appels non bloquants du client : il le pose pour cette demande.
            private _eh = -1;
            if (!hasInterface) then {
                _eh = addMissionEventHandler ["ExtensionCallback", {
                    params ["_name", "_function", ["_payload", ""]];
                    if (_name isEqualTo "comspec" && {_function isEqualType ""} && {(_function select [0, 6]) isEqualTo "async:"}) then {
                        private _pending = missionNamespace getVariable ["COMSPEC_ATAK_ExtPending", createHashMap];
                        private _ticket = _function select [6];
                        if (_ticket in _pending) then { _pending set [_ticket, [_payload]]; };
                    };
                }];
                // fn_extAwait ne passe par l'appel non bloquant que si ce repère existe (sinon callExtension direct, jusqu'à 8 s figé).
                if (isNil {missionNamespace getVariable "COMSPEC_ATAK_ExtensionEH"}) then { missionNamespace setVariable ["COMSPEC_ATAK_ExtensionEH", _eh]; };
            };
            private _ip = "";
            for "_try" from 1 to 3 do {
                private _r = ["COMSPECExtension", "Server.PublicIp", ["https://athena.ttrd.fr/public"], 15] call comspec_atak_native_fnc_extAwait;
                if (_r isEqualType []) then { _r = _r param [0, ""]; };
                if (_r isEqualType "" && {(_r select [0, 3]) isEqualTo "OK|"}) exitWith { _ip = _r select [3]; };
                // DLL absente ou trop ancienne : inutile d'insister.
                if (_r isEqualTo "" || {(_r find "unknown") >= 0}) exitWith {};
                sleep 30;
            };
            if (_eh >= 0) then {
                if ((missionNamespace getVariable ["COMSPEC_ATAK_ExtensionEH", -1]) isEqualTo _eh) then { missionNamespace setVariable ["COMSPEC_ATAK_ExtensionEH", nil]; };
                removeMissionEventHandler ["ExtensionCallback", _eh];
            };
            if (_ip isNotEqualTo "") then {
                missionNamespace setVariable ["COMSPEC_ATAK_ServerIp", _ip, true];
                diag_log format ["[COMSPEC ATAK] Adresse publique du serveur : %1", _ip];
            };
        };
    };
    case "check": {
        if (!hasInterface || {!isMultiplayer}) exitWith {};
        if (diag_tickTime - (missionNamespace getVariable ["COMSPEC_ATAK_ServerPresenceAt", -1e9]) < 600) exitWith {};
        if !([] call comspec_atak_native_fnc_bridge) exitWith {};
        if !(missionNamespace getVariable ["COMSPEC_AthenaReady", false]) exitWith {};
        if (isNil "comspec_overwatch_connect_fnc_hashMapToJson") exitWith {};
        if (missionNamespace getVariable ["COMSPEC_ATAK_ServerPresenceNo", false]) exitWith {};
        if (missionNamespace getVariable ["COMSPEC_ATAK_ServerPresenceBusy", false]) exitWith {};
        missionNamespace setVariable ["COMSPEC_ATAK_ServerPresenceBusy", true];
        private _json = [createHashMapFromArray [
            ["server_ip", missionNamespace getVariable ["COMSPEC_ATAK_ServerIp", ""]], ["server_name", serverName],
            ["mission", briefingName], ["world", worldName], ["player_name", name player], ["steam_uid", getPlayerUID player]
        ]] call comspec_overwatch_connect_fnc_hashMapToJson;
        [_json] spawn {
            params ["_json"];
            private _r = ["COMSPECExtension", "Server.Presence", [_json]] call comspec_atak_native_fnc_extAwait;
            if (_r isEqualType []) then { _r = _r param [0, ""]; };
            missionNamespace setVariable ["COMSPEC_ATAK_ServerPresenceBusy", false];
            if !(_r isEqualType "") exitWith {};
            // COMSPEC Link plus ancien que 2.0.75 : plus redemandé de la partie.
            if (_r isEqualTo "" || {(_r find "unknown") >= 0}) exitWith { missionNamespace setVariable ["COMSPEC_ATAK_ServerPresenceNo", true]; };
            if ((_r select [0, 3]) isNotEqualTo "OK|") exitWith {};
            missionNamespace setVariable ["COMSPEC_ATAK_ServerPresenceAt", diag_tickTime];
            ((_r select [3]) splitString toString [9]) params [["_state", ""], ["_label", ""], ["_community", ""], ["_msg", ""]];
            private _prev = (missionNamespace getVariable ["COMSPEC_ATAK_ServerPresence", []]) param [0, ""];
            missionNamespace setVariable ["COMSPEC_ATAK_ServerPresence", [_state, _label, _community, _msg, [dayTime, "HH:MM"] call BIS_fnc_timeToString]];
            switch (_state) do {
                case "away": {
                    // À l'arrivée, puis rappel toutes les 30 min tant que le joueur reste sur ce serveur.
                    private _last = missionNamespace getVariable ["COMSPEC_ATAK_ServerAwayAt", -1e9];
                    if (_prev isNotEqualTo "away" || {diag_tickTime - _last > 1800}) then {
                        missionNamespace setVariable ["COMSPEC_ATAK_ServerAwayAt", diag_tickTime];
                        ["WARNING", ["Vous n'êtes pas sur un serveur de votre communauté", _msg] select (_msg isNotEqualTo ""), 10, 60] call comspec_atak_native_fnc_notify;
                        [] call comspec_atak_native_fnc_vibrate;
                    };
                };
                case "ok": {
                    if (_prev isEqualTo "away") then { ["SUCCESS", format ["Serveur de la communauté : %1", _label], 5, 30] call comspec_atak_native_fnc_notify; };
                };
            };
        };
    };
};
true
