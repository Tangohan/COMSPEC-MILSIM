# ATAK : les comptes sans communauté recevaient les pistes des autres parties

## Contexte
Deux joueurs sans communauté (comptes de la communauté par défaut, `tenants.slug = default`), chacun sur sa propre mission et sur un terrain différent, dont un en ATAK natif 4.24.3.

## Symptôme
La carte du téléphone affichait des dizaines de pions d'unités amies, souvent pâles, éparpillés sur tout le terrain et en mer : « le même marqueur joueur partout ».

## Cause
Tous les comptes sans communauté écrivent dans la même communauté par défaut et sur la même carte (`mapId` 1, valeur par défaut du jeu). `GET /api/units` rendait donc à chacun les pistes de tous les autres (joueurs, IA alliées `ALLY-…`, balises), avec des coordonnées d'autres terrains, et des lignes vieilles de plusieurs semaines (seule règle de purge : 30 jours). Deux parties qui portaient le même indicatif ou le même identifiant d'IA se disputaient en plus la même ligne. Marqueurs, tracés, messages, ordres, photos et relais partageaient la même carte : même fuite.

## Correctif
- `App\Support\AtakPartyScope` : dans la communauté par défaut, la carte demandée par le client est remplacée côté serveur par une carte réservée à la partie. La clé de partie est `COMSPEC_ATAK_MissionKey` (`mission@terrain#AAAAMMJJHHMM`), posée par le serveur de jeu et déjà envoyée dans chaque position. Sans clé récente (moins de 15 minutes) : carte réservée au compte (ses propres pistes, IA et balises).
- Un seul point de passage : `AtakApiController::requireTenant` (toutes les routes de ce contrôleur), plus `mapId()` de `AtakOverwatchIntelController`, `AtakWaypointApiController` et `AtakAssignmentApiController`. Aucune migration : la partie est portée par `map_id`, et chaque piste est marquée dans `extra` (`party_owner`, `party_key`, `party_world`, `party_at`, `party_src`).
- `GET /api/units` : dans la communauté par défaut, plus de piste muette depuis plus de 15 minutes ; partout, plus de contact relayé (IA alliée, balise, téléphone) muet depuis plus de 12 heures, et plus de piste d'un autre terrain que celui du demandeur (`world_name` envoyé, sinon terrain de sa propre piste pour le jeu). Les joueurs hors liaison d'une communauté réelle et leurs notes TOC restent servis.
- Purge : la tâche « Entretien des données de joueurs » supprime chaque jour les pistes de la communauté par défaut muettes depuis 24 heures et les fichiers d'état des cartes réservées. Rattrapage : `php tools/atak-purge-default-units.php` (simulation), puis `--apply`.

## Fichiers touchés
`app/Support/AtakPartyScope.php`, `app/Support/AtakPartyPurgeCli.php`, `app/Controllers/Api/AtakApiController.php`, `app/Controllers/Api/AtakOverwatchIntelController.php`, `app/Controllers/Api/AtakWaypointApiController.php`, `app/Controllers/Api/AtakAssignmentApiController.php`, `app/Repositories/AtakDataRepository.php`, `app/Core/Request.php`, `app/Services/Cron/Jobs/PlayerDataRetentionCronJob.php`, `tools/atak-purge-default-units.php`, `tests/Unit/AtakPartyScopeTest.php`.

## Vérification
`tests/Unit/AtakPartyScopeTest.php` (sqlite, vrai jeton de jeu) : deux parties ne se voient pas, deux joueurs de la même partie se voient avec leurs IA, compte sans clé limité à ses pistes, carte du client ignorée, pistes périmées non servies, terrain différent filtré, communauté réelle inchangée, rattrapage en simulation puis `--apply`.

## Limites
- Serveur de jeu sans ATAK natif (aucune clé de partie) : dans la communauté par défaut, chaque joueur ne voit que ses propres pistes. Créer une communauté rend le partage.
- Téléphone web appairé par QR sans compte, dans la communauté par défaut : carte réservée à cet appairage (vide).
- Non couverts, toujours communs à la communauté par défaut : équipes de feu (`/api/atak/fire-teams`), plan de mission, réseau routier et lieux (`/api/atak/geo/*`), repères publiés par terrain. Les fonds de terrain rangés par carte ne suivent pas une carte réservée.
- La clé de partie vient du jeu : un client modifié qui connaît le nom de la mission et sa minute de lancement peut rejoindre la carte d'une partie.

## Statut
corrigé à vérifier en jeu ; rattrapage à lancer en production après déploiement.
