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
if (missionNamespace getVariable ["COMSPEC_ATAK_ServerInitDone", false]) exitWith {
    diag_log "[COMSPEC ATAK NATIVE][WARNING][BOOT] Initialisation serveur déjà faite : deux copies du mod chargées, la seconde est ignorée";
};
missionNamespace setVariable ["COMSPEC_ATAK_ServerInitDone", true];
// Le serveur a le mod : il écrit le plan partagé de l'app Plan (sinon les clients gardent leurs tracés en brouillon).
missionNamespace setVariable ["COMSPEC_ATAK_PlanSrvOn", true, true];
// Téléphones de joueurs ramassés par l'IA ennemie, exploitation du BFT et compromissions (fn_captureServer).
["init"] call comspec_atak_native_fnc_captureServer;
// Socles de recharge CS-10 : mémoire, recharge et emplacements (fn_dockServer).
["init"] call comspec_atak_native_fnc_dockServer;
// Contrôle Zeus des ATAK : effets par joueur, brouilleurs de zone, report à la réapparition (fn_zeusServer).
["init"] call comspec_atak_native_fnc_zeusServer;
// CR de fin d'opération imposé en jeu : droits, envoi aux joueurs visés, suivi (fn_debriefServer).
["init"] call comspec_atak_native_fnc_debriefServer;
// Traces civiles des HVT : un contrôle par minute, désactivées par défaut (fn_hvtTrace).
["initSrv"] call comspec_atak_native_fnc_hvtTrace;
// Téléphones personnels : codes, essais et données, côté serveur (fn_phoneServer).
["init"] call comspec_atak_native_fnc_phoneServer;
// App Admin du téléphone : relevé de la partie et debug de l'IA à la demande d'un Zeus ou d'un gestionnaire (fn_adminServer).
["init"] call comspec_atak_native_fnc_adminServer;
// Centre Zeus : relevé à la demande d'un Zeus (fn_zeusState). Rien ne tourne sans demande.
["init"] call comspec_atak_native_fnc_zeusState;
["initServer"] call comspec_atak_native_fnc_zeusObject;
// API brouilleurs pour les missions et les autres mods, objets brouilleurs d'autres mods (fn_jammer).
["init"] call comspec_atak_native_fnc_jammer;
// Écrans COMSPEC : registre des écrans où l'app Briefing diffuse ses diapositives (fn_screenCast).
["initServer"] call comspec_atak_native_fnc_screenCast;
// Écrans TOC : registre, données du réseau calculées ici pour tous (fn_tocFeed), écrans gardés d'une partie à l'autre.
["initServer"] call comspec_atak_native_fnc_tocScreen;
// IA et ATAK : IA équipées par le réglage ou la mission, leurs points PLI envoyés par le serveur (fn_aiAtak).
["init"] call comspec_atak_native_fnc_aiAtak;
// Graphe de référence du maillage (fn_meshGraph) : une seconde entre deux regards, rien sans la liaison réaliste. La variable dit
// aux clients que ce serveur a le mod : sans elle, ils calculent le graphe eux-mêmes.
missionNamespace setVariable ["COMSPEC_ATAK_MeshSrvOn", true, true];
[{ ["serverTick"] call comspec_atak_native_fnc_meshGraph; }, 1] call COMSPEC_ATAK_PfhSpread;
// Objet obligatoire pour le réseau (fn_netRequire) : le serveur lit les inventaires, rien à faire quand le réglage est vide.
[{ ["serverTick"] call comspec_atak_native_fnc_netRequire; }, 2] call COMSPEC_ATAK_PfhSpread;
// Bilan [PERF] sur un serveur dédié (le client le fait de son côté) : rien n'est écrit si rien n'a été mesuré.
if (!hasInterface) then { [{ ["report"] call comspec_atak_native_fnc_perfAcc; }, 30] call COMSPEC_ATAK_PfhSpread; };
// Registre des clés du maillage : mécanique de jeu, décochée par défaut (fn_meshKeys).
["init"] call comspec_atak_native_fnc_meshKeys;
// Actions Zeus sur le maillage, droits vérifiés ici (fn_meshTools).
["init"] call comspec_atak_native_fnc_meshTools;
// Nœuds simulés du maillage : événement refusé tant que le mode développeur est décoché (fn_devMeshSim).
["init"] call comspec_atak_native_fnc_devMeshSim;
// Adresse publique du serveur, pour reconnaître le serveur de la communauté (fn_serverPresence).
["serverInit"] call comspec_atak_native_fnc_serverPresence;
// Téléphones accrochés aux gilets (fn_vestMount) restés seuls après une déconnexion.
[{ ["sweep"] call comspec_atak_native_fnc_vestMount; ["sweep"] call comspec_atak_native_fnc_jpcScreen; }, 30] call COMSPEC_ATAK_PfhSpread;
// Passerelles de véhicule : véhicules détruits retirés, PC mobile qui repart replié (fn_vehicleGateway).
[{ ["sweep"] call comspec_atak_native_fnc_vehicleGateway; }, 5] call COMSPEC_ATAK_PfhSpread;
// Pylônes relais 3D posés dans Eden dont l'init étendue n'aurait pas tourné : enregistrés comme relais (fn_relayTower).
[{ ["sweep"] call comspec_atak_native_fnc_relayTower; }, [], 2] call comspec_atak_native_fnc_waitAndExec;
// Antennes du jeu (pylônes, mâts, antennes omnidirectionnelles, paraboles ; décor de la carte en option) converties en
// relais ATAK (fn_relayConvert) : au début, puis toutes les 60 s pour les objets posés par le Zeus ; antennes du décor
// détruites et antennes MRH (mod facultatif) vérifiées toutes les 15 s.
[{ ["sweep"] call comspec_atak_native_fnc_relayConvert; }, [], 4] call comspec_atak_native_fnc_waitAndExec;
[{ ["sweep"] call comspec_atak_native_fnc_relayConvert; }, 60] call COMSPEC_ATAK_PfhSpread;
[{ ["check"] call comspec_atak_native_fnc_relayConvert; }, 15] call COMSPEC_ATAK_PfhSpread;
// Antennes transportables (fn_antennaKit) : événements de déploiement et de repli.
["init"] call comspec_atak_native_fnc_antennaKit;
// Déclencheur réseau des explosifs (fn_exploNet) : suivi des poses, liste publique des charges liées.
["init"] call comspec_atak_native_fnc_exploNet;
// Relais de mission sur batterie (module « Poser un relais ») : charge qui baisse toutes les 30 s (fn_relayNet "sweep").
[{ ["sweep"] call comspec_atak_native_fnc_relayNet; }, 30] call COMSPEC_ATAK_PfhSpread;
// Conditions d'activation en code des relais et passerelles : évaluées ici seulement, résultat publié (fn_relayCond "srv").
[{ ["srv"] call comspec_atak_native_fnc_relayCond; }, 2] call COMSPEC_ATAK_PfhSpread;
COMSPEC_ATAK_SrvLog = createHashMap;      // camp -> [[événement, arguments]...] (200 au plus)
COMSPEC_ATAK_SrvRoutes = createHashMap;   // netId du groupe -> [points, auteur]
COMSPEC_ATAK_SrvRte = createHashMap;      // app Routes : id -> [route (paires), portée 1-3, camp, netId du groupe, équipe de feu, auteur]
COMSPEC_ATAK_SrvZn = createHashMap;       // app Zones : id -> [plan (paires), portée 1-3, camp, netId du groupe, équipe de feu, auteur]
COMSPEC_ATAK_SrvZnSit = createHashMap;    // app Zones : id -> HashMap id de zone -> [état, horodatage, auteur, signalements]
// Accusés LU/ACK des comptes rendus : envoyés au seul auteur et gardés à part, un par lecteur (le dernier l'emporte),
// pour que le journal ci-dessus garde ses 200 événements utiles. Rejoués à l'auteur seul quand il revient.
COMSPEC_ATAK_SrvRepBy = createHashMap;    // id du C.R. -> UID de son auteur
COMSPEC_ATAK_SrvRcpt = createHashMap;     // id du C.R. -> (UID du lecteur -> arguments de l'accusé)
// « VU » des ordres (un par joueur et par ordre) : file à part, rejouée après le journal.
COMSPEC_ATAK_SrvSeen = createHashMap;     // camp -> [[événement, arguments]...] (300 au plus)
private _keep = {
    params ["_side", "_ev", "_args"];
    private _l = COMSPEC_ATAK_SrvLog getOrDefault [_side, []];
    _l pushBack [_ev, _args];
    // Accusés et états (réception des comptes rendus, réponses aux ordres, état des MEDEVAC) : 100 au plus, à part des
    // demandes elles-mêmes (200 au plus), sinon quelques comptes rendus suffisaient à chasser MEDEVAC et logistique.
    // Une seule liste pour garder l'ordre de la relecture ; la plus ancienne entrée de la même sorte part.
    private _minor = ["comspec_atak_native_reportRcpt", "comspec_atak_native_orderAck", "comspec_atak_native_medevacStatus"];
    private _isMinor = _ev in _minor;
    if (({ ((_x select 0) in _minor) isEqualTo _isMinor } count _l) > ([200, 100] select _isMinor)) then {
        (_l deleteAt (_l findIf { ((_x select 0) in _minor) isEqualTo _isMinor })) params ["_oldEv", "_oldArgs"];
        // C.R. sorti du journal : ses accusés ne servent plus (l'arrivant ne le reçoit plus).
        private _oldR = [createHashMap, _oldArgs param [0, createHashMap]] select (_oldEv isEqualTo "comspec_atak_native_report");
        if (_oldR isEqualType createHashMap && {(count _oldR) > 0}) then {
            private _oid = _oldR getOrDefault ["id", ""];
            COMSPEC_ATAK_SrvRepBy deleteAt _oid;
            COMSPEC_ATAK_SrvRcpt deleteAt _oid;
        };
    };
    COMSPEC_ATAK_SrvLog set [_side, _l];
};
missionNamespace setVariable ["COMSPEC_ATAK_SrvKeep", _keep];
// Marqueurs « réseau ATAK uniquement » (fn_markerNet) : mémoire du serveur et remise aux joueurs du canal.
["comspec_atak_native_tacMkSrv", { ["srv", _this] call comspec_atak_native_fnc_markerNet; }] call CBA_fnc_addEventHandler;
// Heure et auteur des marqueurs Arma (carte d'Arma ou téléphone), redonnés aux joueurs qui rejoignent (fiche du marqueur).
addMissionEventHandler ["MarkerCreated", { params ["_m", "", ["_owner", objNull]]; if (!isNull _owner) then { ["srvMeta", [_m, _owner]] call comspec_atak_native_fnc_markerNet; }; }];
addMissionEventHandler ["MarkerDeleted", { params ["_m"]; ["srvMeta", [_m, objNull]] call comspec_atak_native_fnc_markerNet; }];
["comspec_atak_native_logi", { [_this param [1, ""], "comspec_atak_native_logi", _this] call COMSPEC_ATAK_SrvKeep; }] call CBA_fnc_addEventHandler;
["comspec_atak_native_medevac", { [_this param [1, ""], "comspec_atak_native_medevac", _this] call COMSPEC_ATAK_SrvKeep; }] call CBA_fnc_addEventHandler;
["comspec_atak_native_bda", { [_this param [1, ""], "comspec_atak_native_bda", _this] call COMSPEC_ATAK_SrvKeep; }] call CBA_fnc_addEventHandler;
["comspec_atak_native_report", { [_this param [1, ""], "comspec_atak_native_report", _this] call COMSPEC_ATAK_SrvKeep; }] call CBA_fnc_addEventHandler;
["comspec_atak_native_medevacStatus", { [_this param [3, ""], "comspec_atak_native_medevacStatus", _this] call COMSPEC_ATAK_SrvKeep; }] call CBA_fnc_addEventHandler;
// Réponses aux ordres (qui a vu, reçu, exécute) : rejouées aux arrivants comme les comptes rendus.
// Les « VU » vont dans leur propre file : ils remplissaient le journal à raison d'un par joueur et par ordre.
["comspec_atak_native_orderAck", {
    private _side = _this param [2, ""];
    if ((toUpper (_this param [1, ""])) isNotEqualTo "SEEN") exitWith { [_side, "comspec_atak_native_orderAck", _this] call COMSPEC_ATAK_SrvKeep; };
    private _l = COMSPEC_ATAK_SrvSeen getOrDefault [_side, []];
    _l pushBack ["comspec_atak_native_orderAck", _this];
    if ((count _l) > 300) then { _l deleteAt 0; };
    COMSPEC_ATAK_SrvSeen set [_side, _l];
}] call CBA_fnc_addEventHandler;
// Mode local et poste TOC : messages du poste et ordres émis sans Athena, rejoués aux arrivants.
["comspec_atak_native_tocMsg", { [_this param [4, ""], "comspec_atak_native_tocMsg", _this] call COMSPEC_ATAK_SrvKeep; }] call CBA_fnc_addEventHandler;
["comspec_atak_native_localOrder", { [_this param [1, ""], "comspec_atak_native_localOrder", _this] call COMSPEC_ATAK_SrvKeep; }] call CBA_fnc_addEventHandler;
// Messagerie sans Athena (canaux général, commandement, groupe : fn_chatSend), rejouée aux arrivants.
["comspec_atak_native_chatLocal", { [_this param [5, ""], "comspec_atak_native_chatLocal", _this] call COMSPEC_ATAK_SrvKeep; }] call CBA_fnc_addEventHandler;
// Ordres aux groupes IA (fn_aiOrder) : gestionnaires du serveur branchés dès XEH_preInit (COMSPEC_ATAK_SrvAiOrd).
// Événements de camp (logistique, MEDEVAC, BDA, comptes rendus, alertes) : relayés par le serveur aux seuls joueurs du camp.
// CBA_fnc_globalEvent les envoyait aussi au camp adverse, qui les jetait. Le serveur les reçoit comme avant (journal ci-dessus).
["comspec_atak_native_sideEvent", {
    params [["_ev", ""], ["_args", []], ["_side", ""]];
    if !(_ev in ["comspec_atak_native_logi", "comspec_atak_native_medevac", "comspec_atak_native_medevacStatus", "comspec_atak_native_bda", "comspec_atak_native_report", "comspec_atak_native_alert", "comspec_atak_native_orderAck", "comspec_atak_native_reportRcpt", "comspec_atak_native_tocMsg", "comspec_atak_native_localOrder", "comspec_atak_native_chatLocal", "comspec_atak_native_aiOrdState", "comspec_atak_native_fsState"]) exitWith {};
    [_ev, _args] call CBA_fnc_localEvent;
    private _to = allPlayers select { !local _x && {(str side group _x) isEqualTo _side} };
    // Compte rendu : auteur retenu pour ses accusés ; « MON GROUPE » ne part qu'aux joueurs du groupe (le client filtre aussi).
    if (_ev isEqualTo "comspec_atak_native_report") then {
        private _r = _args param [0, createHashMap];
        if (_r isEqualType createHashMap) then {
            COMSPEC_ATAK_SrvRepBy set [_r getOrDefault ["id", ""], _r getOrDefault ["uid", ""]];
            if ((_r getOrDefault ["dest", "CAMP"]) isEqualTo "MON GROUPE") then {
                private _grp = _r getOrDefault ["grp", ""];
                _to = _to select { (netId group _x) isEqualTo _grp };
            };
        };
    };
    // Accusé LU/ACK : seul l'auteur le garde (les autres le jetaient), il ne part donc qu'à lui.
    // Auteur inconnu (C.R. sorti du journal) : diffusé au camp comme avant.
    if (_ev isEqualTo "comspec_atak_native_reportRcpt") then {
        _args params [["_id", ""], "", ["_uid", ""], "", ["_kind", "LU"]];
        private _byUid = COMSPEC_ATAK_SrvRepBy getOrDefault [_id, ""];
        if (_byUid isNotEqualTo "") then {
            private _rc = COMSPEC_ATAK_SrvRcpt getOrDefault [_id, createHashMap];
            // Un « lu » n'efface jamais un accusé de réception (même règle que chez l'auteur).
            if !(((_rc getOrDefault [_uid, []]) param [4, ""]) isEqualTo "ACK" && {_kind isEqualTo "LU"}) then { _rc set [_uid, _args]; };
            COMSPEC_ATAK_SrvRcpt set [_id, _rc];
            _to = _to select { (getPlayerUID _x) isEqualTo _byUid };
        };
    };
    if (_to isNotEqualTo []) then { [_ev, _args, _to] call CBA_fnc_targetEvent; };
}] call CBA_fnc_addEventHandler;
// App Routes : routes partagées gardées pour les joueurs qui arrivent ensuite (rejouées par syncReq), 300 au plus.
["comspec_atak_native_routeSrv", {
    params [["_op", ""], ["_id", ""], ["_pk", []], ["_sc", 2], ["_side", ""], ["_grp", ""], ["_ft", ""], ["_who", ""]];
    if !(_id isEqualType "" && {_id isNotEqualTo ""}) exitWith {};
    if (_op isEqualTo "del") exitWith { COMSPEC_ATAK_SrvRte deleteAt _id; };
    COMSPEC_ATAK_SrvRte set [_id, [_pk, _sc, _side, _grp, _ft, _who]];
    { if (isNull (groupFromNetId ((COMSPEC_ATAK_SrvRte get _x) select 3)) && {((COMSPEC_ATAK_SrvRte get _x) select 1) < 3}) then { COMSPEC_ATAK_SrvRte deleteAt _x; }; } forEach (keys COMSPEC_ATAK_SrvRte);
    while { (count COMSPEC_ATAK_SrvRte) > 300 } do { COMSPEC_ATAK_SrvRte deleteAt ((keys COMSPEC_ATAK_SrvRte) select 0); };
}] call CBA_fnc_addEventHandler;
// App Zones : plans partagés et situation des zones gardés pour les joueurs qui arrivent ensuite (rejoués par syncReq),
// 100 plans au plus ; dernier état de chaque zone (le plus récent l'emporte) et ses 12 derniers signalements.
["comspec_atak_native_zoneSrv", {
    params [["_op", ""], ["_id", ""]];
    if !(_id isEqualType "" && {_id isNotEqualTo ""}) exitWith {};
    switch (_op) do {
        case "del": { COMSPEC_ATAK_SrvZn deleteAt _id; COMSPEC_ATAK_SrvZnSit deleteAt _id; };
        case "set": {
            params ["", "", ["_pk", []], ["_sc", 2], ["_side", ""], ["_grp", ""], ["_ft", ""], ["_who", ""]];
            COMSPEC_ATAK_SrvZn set [_id, [_pk, _sc, _side, _grp, _ft, _who]];
            { if (isNull (groupFromNetId ((COMSPEC_ATAK_SrvZn get _x) select 3)) && {((COMSPEC_ATAK_SrvZn get _x) select 1) < 3}) then { COMSPEC_ATAK_SrvZn deleteAt _x; COMSPEC_ATAK_SrvZnSit deleteAt _x; }; } forEach (keys COMSPEC_ATAK_SrvZn);
            while { (count COMSPEC_ATAK_SrvZn) > 100 } do { private _k = (keys COMSPEC_ATAK_SrvZn) select 0; COMSPEC_ATAK_SrvZn deleteAt _k; COMSPEC_ATAK_SrvZnSit deleteAt _k; };
        };
        case "sit": {
            params ["", "", ["_zid", ""], ["_st", ""], ["_stamp", 0], ["_by", ""], ["_reps", []]];
            if !(_zid isEqualType "" && {_stamp isEqualType 0} && {_reps isEqualType []}) exitWith {};
            private _h = COMSPEC_ATAK_SrvZnSit getOrDefault [_id, createHashMap];
            COMSPEC_ATAK_SrvZnSit set [_id, _h];
            (_h getOrDefault [_zid, ["", -1e9, "", []]]) params ["_os", "_ot", "_ob", "_or"];
            if (_st isNotEqualTo "" && {_stamp >= _ot}) then { _os = _st; _ot = _stamp; _ob = _by; };
            _or = (_or + (_reps select [0, 12])) select [((count _or) + ((count _reps) min 12) - 12) max 0, 12];
            _h set [_zid, [_os, _ot, _ob, _or]];
        };
        case "reset": {
            params ["", "", ["_t", 0]];
            private _h = COMSPEC_ATAK_SrvZnSit getOrDefault [_id, createHashMap];
            { _h set [_x, ["UNREC", _t, "", []]]; } forEach (keys _h);
        };
    };
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
// Nom du serveur (détection des parties sur Athena) : champ à part « server_name » des relevés, la clé ci-dessus garde
// son format (le site s'en sert pour regrouper). Vide en solo et dans l'éditeur.
missionNamespace setVariable ["COMSPEC_ATAK_ServerName", [serverName, ""] select (!isMultiplayer || {is3DENPreview}), true];
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
    // Annonce seulement s'il y a eu des corrections (sinon une notification vide de sens à chaque arrivée) ; les pages
    // sont redessinées dans tous les cas. RESYNCHRONISER confirme déjà la demande côté client (fn_interTeamAction).
    if (!isNull _by) then { ["comspec_atak_native_ftChanged", [group _by, ["", format ["Synchronisation : %1 correction(s), révision %2", _fixed, missionNamespace getVariable ["COMSPEC_ATAK_SquadRev", 0]]] select (_fixed > 0)], _by] call CBA_fnc_targetEvent; };
    _fixed
};
["comspec_atak_native_ftCheck", { _this call COMSPEC_ATAK_FtCheck; }] call CBA_fnc_addEventHandler;
[{ [] call COMSPEC_ATAK_FtCheck; }, 10] call COMSPEC_ATAK_PfhSpread;
["comspec_atak_native_syncReq", {
    params ["_unit"];
    if (isNull _unit) exitWith {};
    private _side = str side group _unit;
    // Journal du camp, puis les « VU » des ordres, puis les accusés des C.R. dont l'arrivant est l'auteur (après leurs C.R.).
    private _log = (COMSPEC_ATAK_SrvLog getOrDefault [_side, []]) + (COMSPEC_ATAK_SrvSeen getOrDefault [_side, []]);
    // Dernier état des ordres aux groupes IA du camp (fn_aiOrder) : un par groupe.
    { _log pushBack ["comspec_atak_native_aiOrdState", [_x, (keys _y) apply { [_x, _y get _x] }, _side]]; } forEach (COMSPEC_ATAK_SrvAiOrd getOrDefault [_side, createHashMap]);
    // Missions de tir du camp (fn_fireSupport) : dernier état de chacune.
    { _log pushBack ["comspec_atak_native_fsState", [_x, (keys _y) apply { [_x, _y get _x] }, _side]]; } forEach (COMSPEC_ATAK_SrvFs getOrDefault [_side, createHashMap]);
    private _me = getPlayerUID _unit;
    {
        if (_y isEqualTo _me) then {
            { _log pushBack ["comspec_atak_native_reportRcpt", _y]; } forEach (COMSPEC_ATAK_SrvRcpt getOrDefault [_x, createHashMap]);
        };
    } forEach COMSPEC_ATAK_SrvRepBy;
    // Routes partagées (app Routes) qui concernent l'arrivant : son camp, son groupe, son équipe de feu.
    {
        _y params ["_pk", "_sc", "_rs", "_rg", "_rf", "_who"];
        if (_rs isEqualTo _side && {_sc >= 3 || {_rg isEqualTo (netId group _unit) && {_sc >= 2 || {_rf isEqualTo "" || {_rf isEqualTo (_unit getVariable ["COMSPEC_FT", ""])}}}}}) then {
            _log pushBack ["comspec_atak_native_route", ["set", _pk, _who, _sc]];
        };
    } forEach COMSPEC_ATAK_SrvRte;
    // Plans de zones partagés qui concernent l'arrivant (même règle que les routes), puis la situation de leurs zones.
    {
        _y params ["_pk", "_sc", "_zs", "_zg", "_zf", "_who"];
        if (_zs isEqualTo _side && {_sc >= 3 || {_zg isEqualTo (netId group _unit) && {_sc >= 2 || {_zf isEqualTo "" || {_zf isEqualTo (_unit getVariable ["COMSPEC_FT", ""])}}}}}) then {
            _log pushBack ["comspec_atak_native_zone", ["set", _pk, _who, _sc]];
            private _zid0 = _x;
            { _log pushBack ["comspec_atak_native_zone", ["sitAll", _zid0, _x, _y select 0, _y select 1, _y select 2, _y select 3]]; } forEach (COMSPEC_ATAK_SrvZnSit getOrDefault [_x, createHashMap]);
        };
    } forEach COMSPEC_ATAK_SrvZn;
    ["comspec_atak_native_syncData", [_log, COMSPEC_ATAK_SrvRoutes getOrDefault [netId group _unit, []]], _unit] call CBA_fnc_targetEvent;
    // Plans de briefing des camps (fn_planServer) : plus dans la file JIP, envoyés ici à l'arrivant (tous, comme la file
    // JIP le faisait : un joueur qui change de camp retrouve le plan de son nouveau camp).
    if (!local _unit) then {
        {
            private _planVar = format ["COMSPEC_ATAK_Plan_%1", _x];
            if (!isNil { missionNamespace getVariable _planVar }) then { (owner _unit) publicVariableClient _planVar; };
        } forEach [west, east, independent, civilian];
    };
}] call CBA_fnc_addEventHandler;
// Temps de jeu et temps par rôle comptés ici pour chaque joueur, quel que soit l'état de son téléphone (fn_playTimeServer).
// Sonde au démarrage : si la DLL du serveur sait envoyer, les clients ne remontent plus eux-mêmes que l'écran et les apps.
[{ ["tick"] call comspec_atak_native_fnc_playTimeServer; }, 10] call COMSPEC_ATAK_PfhSpread;
[{
    private _r = (["COMSPECExtension", "ScreenTime.Batch", ["probe"]] call comspec_overwatch_connect_fnc_extDirect);
    if (_r isEqualType []) then { _r = _r param [0, ""]; };
    missionNamespace setVariable ["COMSPEC_ATAK_SrvPlayOK", (_r select [0, 3]) isEqualTo "OK|", true];
    ["INFO", "PLAYTIME", format ["Sonde ScreenTime.Batch : %1", [_r, "COMSPEC Link 2.0.63 requis"] select (_r isEqualTo "")]] call comspec_atak_native_fnc_log;
}, [], 15] call comspec_atak_native_fnc_waitAndExec;
// Distances parcourues et heures de pilote de chaque joueur (app Distances), envoyées à Athena toutes les 10 min (fn_distanceServer).
[{ ["tick"] call comspec_atak_native_fnc_distanceServer; }, 3] call COMSPEC_ATAK_PfhSpread;
// Ménage différé demandé par un client (caisses larguées...) : fait ici pour survivre à son départ.
["comspec_atak_native_deleteLater", {
    params [["_objs", []], ["_delay", 1800]];
    [{ { if (!isNull _x) then { deleteVehicle _x; }; } forEach _this; }, _objs, _delay] call comspec_atak_native_fnc_waitAndExec;
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
