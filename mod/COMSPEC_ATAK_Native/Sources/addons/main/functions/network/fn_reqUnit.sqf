/*
    Unité du poste qui a lancé un appel distant, à partir de son identifiant de propriétaire (remoteExecutedOwner).
    À appeler sur le serveur, en tête d'une fonction ouverte aux clients (CfgRemoteExec), POUR REMPLACER toute unité
    « demandeur » reçue dans les arguments : un client choisit ce qu'il met dans un message (l'unité d'un Zeus, d'un
    chef de groupe, ou n'importe quelle IA locale au serveur), mais il ne choisit pas l'identifiant de son poste.
    Même modèle que functions/vehicle/fn_vehicleGateway.sqf « srv » (l. 90-99) et que
    mod/@COMSPEC_SSE/addons/campaign/functions/fn_campaignOp.sqf:32-35.
    L'identifiant est passé en argument (et non lu ici) pour que l'appelant le lise dans SA portée : isRemoteExecuted
    et remoteExecutedOwner décrivent l'exécution en cours, et l'appelant est le seul à savoir s'il est appelé à distance.
    Params : [identifiant de propriétaire (remoteExecutedOwner)]
    Renvoie l'unité du joueur de ce poste, ou objNull :
      - partie solo : player (il n'y a qu'un poste) ;
      - hôte d'une partie locale qui s'appelle lui-même : player ;
      - poste inconnu, serveur (2), appel local (0) ou joueur sans corps (parti, réapparition en cours) : objNull.
    objNull veut dire « pas de demandeur distant identifié » : l'appelant doit refuser, jamais retomber sur
    l'unité reçue dans la demande.
    [remoteExecutedOwner] call comspec_atak_native_fnc_reqUnit -> Object
*/
params [["_owner", 0, [0]]];
if (!isServer) exitWith { objNull };
if (!isMultiplayer) exitWith { player };
// 0 = appel local (pas de remoteExec), 2 = le serveur lui-même : dans les deux cas l'appelant est du code serveur.
if (_owner <= 0 || {_owner isEqualTo 2}) exitWith { objNull };
if (hasInterface && {_owner isEqualTo clientOwner}) exitWith { player };
(allPlayers select { (owner _x) isEqualTo _owner }) param [0, objNull]
