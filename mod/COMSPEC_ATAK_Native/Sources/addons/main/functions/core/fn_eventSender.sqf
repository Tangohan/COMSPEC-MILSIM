/*
    Expéditeur réel d'un événement CBA reçu par le serveur : le joueur dont la machine a envoyé l'appel distant
    (remoteExecutedOwner), jamais ce que l'événement annonce de lui. Sert à vérifier le camp et l'auteur des événements de
    camp (comspec_atak_native_sideEvent) et des entrées / sorties de zone (comspec_atak_native_zoneMove) : un client modifié ne
    peut plus écrire dans le flux d'un autre camp.
    Rend objNull quand l'appel n'est pas distant (serveur lui-même, hôte d'un serveur local) ou que la machine ne porte aucun
    joueur : l'événement est alors cru comme avant. À appeler directement dans le gestionnaire de l'événement (même appel).
    [] call comspec_atak_native_fnc_eventSender -> unité du joueur ou objNull
*/
if (!isServer || {!isRemoteExecuted}) exitWith { objNull };
private _owner = remoteExecutedOwner;
if (_owner in [0, 2]) exitWith { objNull };
(allPlayers select { (owner _x) isEqualTo _owner }) param [0, objNull]
