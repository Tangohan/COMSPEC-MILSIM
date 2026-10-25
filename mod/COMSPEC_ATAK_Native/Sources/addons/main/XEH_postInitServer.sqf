private _perfT0 = diag_tickTime;
// Profil : les écritures regroupées (fn_saveProfile, une par minute au plus) sont vidées en fin de mission.
{
    addMissionEventHandler [_x, { if (missionNamespace getVariable ["COMSPEC_ProfileSavePending", false]) then { [true] call comspec_atak_native_fnc_saveProfile; }; }];
} forEach ["Ended", "MPEnded"];
/*
    Mémoire serveur de l'état partagé du téléphone, pour les joueurs qui rejoignent en cours de partie :
    demandes logistiques, MEDEVAC et comptes rendus (journal des événements par camp, rejoué chez le nouveau venu)
    et itinéraires de groupe partagés. Les brouilleurs sont déjà une variable publique (COMSPEC_ATAK_Jammers).
    Un client demande la synchro à son arrivée (comspec_atak_native_syncReq) ; le serveur répond par comspec_atak_native_syncData.
*/
if (!isServer) exitWith {};
// Le serveur a le mod : il écrit le plan partagé de l'app Plan (sinon les clients gardent leurs tracés en brouillon).
missionNamespace setVariable ["COMSPEC_ATAK_PlanSrvOn", true, true];
// Téléphones de joueurs ramassés par l'IA ennemie, exploitation du BFT et compromissions (fn_captureServer).
["init"] call comspec_atak_native_fnc_captureServer;
// Socles de recharge CS-10 : mémoire, recharge et emplacements (fn_dockServer).
["init"] call comspec_atak_native_fnc_dockServer;
// Contrôle Zeus des ATAK : effets par joueur, brouilleurs de zone, report à la réapparition (fn_zeusServer).
["init"] call comspec_atak_native_fnc_zeusServer;
// API brouilleurs pour les missions et les autres mods, objets brouilleurs d'autres mods (fn_jammer).
["init"] call comspec_atak_native_fnc_jammer;
// Adresse publique du serveur, pour reconnaître le serveur de la communauté (fn_serverPresence).
["serverInit"] call comspec_atak_native_fnc_serverPresence;
// Téléphones accrochés aux gilets (fn_vestMount) restés seuls après une déconnexion.
[{ ["sweep"] call comspec_atak_native_fnc_vestMount; ["sweep"] call comspec_atak_native_fnc_jpcScreen; }, 30] call COMSPEC_ATAK_PfhSpread;
// Passerelles de véhicule : véhicules détruits retirés, PC mobile qui repart replié (fn_vehicleGateway).
[{ ["sweep"] call comspec_atak_native_fnc_vehicleGateway; }, 5] call COMSPEC_ATAK_PfhSpread;
// Pylônes relais 3D posés dans Eden dont l'init étendue n'aurait pas tourné : enregistrés comme relais (fn_relayTower).
[{ ["sweep"] call comspec_atak_native_fnc_relayTower; }, [], 2] call CBA_fnc_waitAndExecute;
// Relais de mission sur batterie (module « ATAK · Relais ») : charge qui baisse toutes les 30 s (fn_relayNet "sweep").
[{ ["sweep"] call comspec_atak_native_fnc_relayNet; }, 30] call COMSPEC_ATAK_PfhSpread;
COMSPEC_ATAK_SrvLog = createHashMap;      // camp -> [[événement, arguments]...] (200 au plus)
COMSPEC_ATAK_SrvRoutes = createHashMap;   // netId du groupe -> [points, auteur]
private _keep = {
    params ["_side", "_ev", "_args"];
    private _l = COMSPEC_ATAK_SrvLog getOrDefault [_side, []];
    _l pushBack [_ev, _args];
    if ((count _l) > 200) then { _l deleteAt 0; };
    COMSPEC_ATAK_SrvLog set [_side, _l];
};
missionNamespace setVariable ["COMSPEC_ATAK_SrvKeep", _keep];
["comspec_atak_native_logi", { [_this param [1, ""], "comspec_atak_native_logi", _this] call COMSPEC_ATAK_SrvKeep; }] call CBA_fnc_addEventHandler;
["comspec_atak_native_medevac", { [_this param [1, ""], "comspec_atak_native_medevac", _this] call COMSPEC_ATAK_SrvKeep; }] call CBA_fnc_addEventHandler;
["comspec_atak_native_bda", { [_this param [1, ""], "comspec_atak_native_bda", _this] call COMSPEC_ATAK_SrvKeep; }] call CBA_fnc_addEventHandler;
["comspec_atak_native_report", { [_this param [1, ""], "comspec_atak_native_report", _this] call COMSPEC_ATAK_SrvKeep; }] call CBA_fnc_addEventHandler;
["comspec_atak_native_reportRcpt", { [_this param [1, ""], "comspec_atak_native_reportRcpt", _this] call COMSPEC_ATAK_SrvKeep; }] call CBA_fnc_addEventHandler;
["comspec_atak_native_medevacStatus", { [_this param [3, ""], "comspec_atak_native_medevacStatus", _this] call COMSPEC_ATAK_SrvKeep; }] call CBA_fnc_addEventHandler;
// Réponses aux ordres (qui a vu, reçu, exécute) : rejouées aux arrivants comme les comptes rendus.
["comspec_atak_native_orderAck", { [_this param [2, ""], "comspec_atak_native_orderAck", _this] call COMSPEC_ATAK_SrvKeep; }] call CBA_fnc_addEventHandler;
// Événements de camp (logistique, MEDEVAC, BDA, comptes rendus, alertes) : relayés par le serveur aux seuls joueurs du camp.
// CBA_fnc_globalEvent les envoyait aussi au camp adverse, qui les jetait. Le serveur les reçoit comme avant (journal ci-dessus).
["comspec_atak_native_sideEvent", {
    params [["_ev", ""], ["_args", []], ["_side", ""]];
    if !(_ev in ["comspec_atak_native_logi", "comspec_atak_native_medevac", "comspec_atak_native_medevacStatus", "comspec_atak_native_bda", "comspec_atak_native_report", "comspec_atak_native_alert", "comspec_atak_native_orderAck", "comspec_atak_native_reportRcpt"]) exitWith {};
    [_ev, _args] call CBA_fnc_localEvent;
    private _to = allPlayers select { !local _x && {(str side group _x) isEqualTo _side} };
    if (_to isNotEqualTo []) then { [_ev, _args, _to] call CBA_fnc_targetEvent; };
}] call CBA_fnc_addEventHandler;
["comspec_atak_native_wpStore", {
    params ["_grp", "_pts", "_who"];
    // Itinéraire effacé par le groupe : plus rien à rejouer aux arrivants.
    if ((count _pts) isEqualTo 0) then { COMSPEC_ATAK_SrvRoutes deleteAt _grp; } else { COMSPEC_ATAK_SrvRoutes set [_grp, [_pts, _who]]; };
    // Groupes disparus retirés (une entrée par groupe ayant reçu un itinéraire, jamais supprimée sinon).
    { if (isNull (groupFromNetId _x)) then { COMSPEC_ATAK_SrvRoutes deleteAt _x; }; } forEach (keys COMSPEC_ATAK_SrvRoutes);
}] call CBA_fnc_addEventHandler;
// Équipes de feu : seul le serveur écrit l'état partagé (fn_ftServer), puis le diffuse au camp.
["comspec_atak_native_ft", { _this call comspec_atak_native_fnc_ftServer; }] call CBA_fnc_addEventHandler;
// Clé de la session (Athena range les escouades et équipes envoyées par mission jouée).
// Date sur deux chiffres (AAAAMMJJHHMM) : sans zéro, 9 h 03 donnait « 93 » et la date de la session était illisible.
missionNamespace setVariable ["COMSPEC_ATAK_MissionKey", format ["%1@%2#%3", missionName, worldName, ((systemTimeUTC select [0, 5]) apply { [str _x, "0" + str _x] select (_x < 10) }) joinString ""], true];
// Contrôle de cohérence : unités dont l'équipe n'existe plus dans leur groupe (changement de groupe hors téléphone,
// équipe dissoute) remises sans équipe. Toutes les 10 s, et à la demande (Inter-team > RESYNCHRONISER, arrivée d'un joueur).
COMSPEC_ATAK_FtCheck = {
    params [["_by", objNull]];
    private _fixed = 0;
    {
        private _u = _x;
        private _tid = _u getVariable ["COMSPEC_FT", ""];
        if (_tid isNotEqualTo "" && {((group _u getVariable ["COMSPEC_FireTeams", []]) findIf { (_x select 0) isEqualTo _tid }) < 0}) then {
            _u setVariable ["COMSPEC_FT", "", true];
            if ((_u getVariable ["COMSPEC_FTRole", ""]) isEqualTo "CDE") then { _u setVariable ["COMSPEC_FTRole", "FUS", true]; };
            [_u, "MAIN"] remoteExecCall ["assignTeam", 0];
            _fixed = _fixed + 1;
        };
    } forEach allUnits;
    if (_fixed > 0) then { missionNamespace setVariable ["COMSPEC_ATAK_SquadRev", (missionNamespace getVariable ["COMSPEC_ATAK_SquadRev", 0]) + 1, true]; };
    if (!isNull _by) then { ["comspec_atak_native_ftChanged", [group _by, format ["Synchronisation : %1 correction(s), révision %2", _fixed, missionNamespace getVariable ["COMSPEC_ATAK_SquadRev", 0]]], _by] call CBA_fnc_targetEvent; };
    _fixed
};
["comspec_atak_native_ftCheck", { _this call COMSPEC_ATAK_FtCheck; }] call CBA_fnc_addEventHandler;
[{ [] call COMSPEC_ATAK_FtCheck; }, 10] call COMSPEC_ATAK_PfhSpread;
["comspec_atak_native_syncReq", {
    params ["_unit"];
    if (isNull _unit) exitWith {};
    private _side = str side group _unit;
    ["comspec_atak_native_syncData", [COMSPEC_ATAK_SrvLog getOrDefault [_side, []], COMSPEC_ATAK_SrvRoutes getOrDefault [netId group _unit, []]], _unit] call CBA_fnc_targetEvent;
}] call CBA_fnc_addEventHandler;
// Temps de jeu et temps par rôle comptés ici pour chaque joueur, quel que soit l'état de son téléphone (fn_playTimeServer).
// Sonde au démarrage : si la DLL du serveur sait envoyer, les clients ne remontent plus eux-mêmes que l'écran et les apps.
[{ ["tick"] call comspec_atak_native_fnc_playTimeServer; }, 10] call COMSPEC_ATAK_PfhSpread;
[{
    private _r = "COMSPECExtension" callExtension ["ScreenTime.Batch", ["probe"]];
    if (_r isEqualType []) then { _r = _r param [0, ""]; };
    missionNamespace setVariable ["COMSPEC_ATAK_SrvPlayOK", (_r select [0, 3]) isEqualTo "OK|", true];
    ["INFO", "PLAYTIME", format ["Sonde ScreenTime.Batch : %1", [_r, "COMSPEC Link 2.0.63 requis"] select (_r isEqualTo "")]] call comspec_atak_native_fnc_log;
}, [], 15] call CBA_fnc_waitAndExecute;
// Distances parcourues et heures de pilote de chaque joueur (app Distances), envoyées à Athena toutes les 10 min (fn_distanceServer).
[{ ["tick"] call comspec_atak_native_fnc_distanceServer; }, 3] call COMSPEC_ATAK_PfhSpread;
// Ménage différé demandé par un client (caisses larguées...) : fait ici pour survivre à son départ.
["comspec_atak_native_deleteLater", {
    params [["_objs", []], ["_delay", 1800]];
    [{ { if (!isNull _x) then { deleteVehicle _x; }; } forEach _this; }, _objs, _delay] call CBA_fnc_waitAndExecute;
}] call CBA_fnc_addEventHandler;
addMissionEventHandler ["HandleDisconnect", {
    params [["_unit", objNull], "", "_uid"];
    ["leave", _uid] call comspec_atak_native_fnc_playTimeServer;
    ["leave", _uid] call comspec_atak_native_fnc_distanceServer;
    // Téléphone de gilet accroché au joueur qui part : retirés (ils restaient dans la partie).
    if (!isNull _unit) then {
        { if ((typeOf _x) isEqualTo "COMSPEC_ATAK_VestPhone") then { deleteVehicle _x; }; } forEach (attachedObjects _unit);
    };
    false
}];
addMissionEventHandler ["MPEnded", { ["flush"] call comspec_atak_native_fnc_playTimeServer; ["flush"] call comspec_atak_native_fnc_distanceServer; }];
diag_log format ["[COMSPEC ATAK NATIVE][PERF] %1 : %2 ms", "postInit serveur", round ((diag_tickTime - _perfT0) * 1000)];
