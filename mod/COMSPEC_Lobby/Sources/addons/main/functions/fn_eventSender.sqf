/*
    Expéditeur réel d'un événement CBA reçu par le serveur : le joueur dont la machine a envoyé l'appel distant
    (remoteExecutedOwner), jamais ce que l'événement annonce de lui. objNull si l'appel n'est pas distant (serveur lui-même,
    hôte d'un serveur local) ou si la machine ne porte aucun joueur. À appeler directement dans le gestionnaire (même appel).
    [] call comspec_lobby_fnc_eventSender -> unité du joueur ou objNull
*/
if (!isServer || {!isRemoteExecuted}) exitWith { objNull };
private _owner = remoteExecutedOwner;
if (_owner in [0, 2]) exitWith { objNull };
(allPlayers select { (owner _x) isEqualTo _owner }) param [0, objNull]
