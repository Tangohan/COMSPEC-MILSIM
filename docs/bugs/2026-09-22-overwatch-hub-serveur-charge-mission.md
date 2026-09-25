# Hub serveur Overwatch — charge mission déléguée

**Statut :** corrigé (sources)

## Contexte

Le pack tourne sur serveur dédié et dans le modpack joueur. Relais, zones, ordres IA et polls mission étaient souvent répétés par chaque client.

## Correctif

Hub serveur (`initServerHub`) dans connect 1.6.12 :

1. **File anti-spam** (`serverEnqueue` / `serverFlushQueue`) — une clé = une action, debounce.
2. **Relais** — boucle unique serveur ; clients ne resynchronisent plus si hub actif.
3. **Zones / roleplay** — poll Athena une fois (`serverPollMissionState`), marqueurs créés serveur, drapeaux diffusés aux clients.
4. **Ordres IA** — poll serveur ; clients s’abstiennent si le hub possède le poll.
5. **Marqueurs Zeus** — EH serveur + debounce avant envoi Athena.

Réglage CBA : **Hub serveur (charge mission)** (`comspec_overwatch_server_hub`, défaut activé).

Uplink dedicated : nécessite adresse + clé communauté CBA sur le serveur (sinon les clients gardent le fallback).

## Fichiers touchés

- `connect/functions/fn_initServerHub.sqf` (nouveau)
- `connect/functions/fn_serverEnqueue.sqf` (nouveau)
- `connect/functions/fn_serverFlushQueue.sqf` (nouveau)
- `connect/functions/fn_serverPollMissionState.sqf` (nouveau)
- `connect/functions/fn_pollRoleplayConfig.sqf`
- `connect/functions/fn_pollAiOrders.sqf`
- `connect/functions/fn_syncRoleplayZonesFromPortal.sqf`
- `connect/functions/fn_startSyncLoops.sqf`
- `connect/XEH_postInit.sqf`, `XEH_preInit.sqf`, `config.cpp`

## Vérification

1. Rebuild PBO connect 1.6.12, serveur + clients.
2. RPT serveur : `ServerHub` démarré ; un seul poll roleplay ~90 s.
3. Pose Zeus d’un relais : sync sans N clients.
4. Clients : position / téléphone inchangés.

## Statut

corrigé (sources) — rebuild PBO + déploiement serveur requis
