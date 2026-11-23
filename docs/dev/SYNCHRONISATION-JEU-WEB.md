# Synchronisation jeu ⇄ serveur ⇄ web et téléphone

Audit du 16/11/2026, fait en lisant le code (main 78a5a259d, ATAK natif 4.31.0, Overwatch connect). Rien n'a été
mesuré en jeu ni en production. Les chemins SQF sont relatifs à `mod/` : **AN** = `COMSPEC_ATAK_Native/Sources/addons/main`,
**OW** = `UptoDate/Sources/comspec-overwatch-addons/connect/functions`. « Ctrl » = `app/Controllers/Api/AtakApiController.php`.

## 1. Le cas signalé : l'indicatif de l'avion reste « ALPHA 1-3 »

**Symptôme.** Sur la page 9-LINE ouverte sur un téléphone (QR code de l'app JTAC), l'indicatif de l'avion reste
« ALPHA 1-3 » alors que le pilote l'a changé.

**Chemin de la donnée.**

1. Le pilote donne un indicatif à son appareil dans l'ATAK natif (espace BRIEFS AIR, `AN/functions/tactical/fn_airBrief.sqf`
   `setAirCs`). La variable `COMSPEC_ATAK_AirCs` est posée sur le véhicule et partagée à tous les joueurs.
2. `OW/fn_reportCrewedAirAssets.sqf` tourne toutes les 3 s chez un seul joueur relié. Il lit `COMSPEC_ATAK_AirCs`, puis
   `COMSPEC_Callsign`, puis `COMSPEC_GpsCallsign`, et à défaut le **nom du groupe Arma**. Le groupe par défaut d'Arma
   s'appelle « Alpha 1-3 ». Il envoie ensuite `SendFlightManifest` avec `source = occupancy` et `vehicle_id = netId`.
3. Côté serveur, `POST /api/atak/flight-manifest` (Ctrl `flightManifestStore`) ou le lot de télémétrie de type `flight`
   (`AtakTelemetryBatchIngest::handleFlight`) appelle `AtakDataRepository::upsertAirAsset`, qui écrit dans `atak_air_assets`
   (clé unique : communauté, carte, indicatif).
4. La page lit `GET /9-line/appareils` toutes les 5 s (`no-store`). `NineLineBoardService::aircraft` renvoie l'indicatif
   reçu, ou l'indicatif affiché choisi sur le site (`atak_brief_callsigns`).

**Cause (prouvée par le code).** Dans `upsertAirAsset`, pour une remontée d'occupation, la règle
`if ($prevCs !== '' && ($prevSource === 'manifest' || $prevCs !== $callsign)) $keepCallsign = $prevCs;` gardait
**toujours** l'indicatif déjà connu. Le champ `callsign` n'était d'ailleurs jamais mis à jour en mode occupation. La
première remontée (le groupe Arma « Alpha 1-3 », envoyée avant que le pilote choisisse son indicatif) restait donc figée
pour toute la session, et la page affichait la valeur juste mais périmée de la base. Ni le cache ni le JS n'étaient en
cause.

Ce n'est pas `MissionPlanningTemplate.php:39` : « ALPHA 1-3 » y sert seulement de valeur d'exemple pour les nouveaux
plans de mission et n'est pas lu par la page 9-LINE. L'**indicatif JTAC** (`POST /9-line/indicatif`, `jtacCallsign`)
est celui du rédacteur. Il n'a rien à voir avec l'indicatif de l'appareil (`/9-line/appareils/indicatif` côté site,
`COMSPEC_ATAK_AirCs` en jeu).

**Correctifs livrés.**

- Serveur, `AtakDataRepository::occupancyMayRename`. Une remontée d'occupation renomme l'appareil quand c'est le
  **même appareil** (même `vehicle_id`) et que l'une de ces deux conditions est vraie :
  - l'indicatif vient du pilote (`callsign_explicit`) ;
  - l'indicatif connu venait lui-même de l'occupation.

  Un rapprochement par la seule position ne renomme jamais rien. Un indicatif encore porté par un autre appareil actif
  (moins de 30 s) n'est pas pris. Une ligne périmée qui le porte est supprimée (`freeAirCallsign`) : avant, l'écriture
  aurait échoué sur la clé unique.
- Mod, `OW/fn_reportCrewedAirAssets.sqf` :
  - le champ `callsign_explicit` est envoyé quand l'indicatif vient de l'appareil (pilote ou `fn_setCallsign`) ;
  - l'indicatif du pilote entre dans la signature : il remonte au prochain passage (3 s) au lieu d'attendre le battement
    de 7 s.

  Sans ce mod, le correctif serveur suffit pour le cas courant (indicatif connu venu de l'occupation).
- Page 9-LINE (`atak-nine-line.js`) :
  - la carte de l'appareil affiche « Indicatif changé (avant : …) · il y a Xs » pendant 10 min ;
  - un destinataire coché suit le nouvel indicatif (suivi par l'`id` de l'appareil) ;
  - les lectures se font en `cache: 'no-store'` et repartent tout de suite au retour sur l'onglet, à la reprise du
    réseau ou au retour arrière.
- Tests :
  - `tests/Unit/AtakAirCallsignRenameTest.php` : sqlite, relu par `NineLineBoardService` comme la page ;
  - `tests/Contract/AtakAirAssetRenameMysqlTest.php` : clé unique, MySQL 8 et MariaDB.

## 2. Flux par flux

Délai nominal = du changement en jeu à la donnée lisible sur le web, sans panne. « Une seule source » = un seul client
envoie pour tout le monde. S'il est bloqué, personne n'envoie.

| Flux | Sens | Déclencheur et délai nominal | Mécanisme | Cache | Risques de désynchronisation |
|---|---|---|---|---|---|
| Fiche opérateur (Identity) | jeu → serveur | empreinte relue toutes les 8 s, changement d'équipement +3 s (8 à 16 s) | `OperatorSync` (OW/fn_operatorProfileTick, fn_syncOperatorProfile) | — | L'empreinte (OW/fn_operatorProfileFingerprint) ignore l'arme secondaire, l'arme de poing, les objets, les chargeurs et la description du slot, et le battement de 15 min a été retiré : ces changements ne repartent jamais seuls. Le délai d'attente après échec monte en minutes. Sans SteamID, rien n'est envoyé. |
| Profil serveur → jeu | serveur → jeu | `SyncProfile` toutes les 300 s | sondage | — | Une modification sur le site met jusqu'à 5 min à arriver. |
| Indicatif du joueur | jeu → serveur | position (battement ≤ 30 s), fiche, `Squad.Sync` forcé | UpdatePosition, OperatorSync | — | Deux joueurs de même indicatif sont fusionnés dans la liste des unités (`AtakUnitRowDedupe::byCallSign`). |
| Indicatif du joueur (site → téléphone) | serveur → jeu | **à l'arrivée seulement** (AN/teams/fn_ftRoleSync « load ») | GetFireTeams | — | Un changement fait sur le site en cours de mission n'arrive jamais. Au tout premier passage, la valeur du site est ignorée (fn_callsignAction:94). |
| Rôle, slot, équipes | jeu → serveur | sondage 30 s, à chaque changement (20 s au plus tôt), au moins toutes les 5 min | `Squad.Sync`, une seule source (le chef) | — | La signature était posée avant l'envoi : un échec réseau n'était repris qu'au bout de 5 min. **Corrigé** (fn_squadSync : nouvel essai au prochain passage). |
| Position / BFT | jeu → serveur → web | 5 s mini, battement 30 s ; pages : mobile 3,5 s, TOC 3 s | UpdatePosition (mis en file dans la DLL) | ShortCache 2 s | Le battement s'allonge jusqu'à 600 s avec `COMSPEC_SendBackoffSec`. Rien ne part pendant le délai de grâce après réapparition, sans téléphone (fn_uplinkHold) ou pendant un envoi en cours (30 s). « Retardé » après 108 s, retiré après 900 s. |
| Aéronefs | jeu → serveur → web | 3 s (écart 2,5 s, battement 7 s) ; page 9-LINE 5 s | SendFlightManifest, **une seule source** | aucun | L'indicatif restait figé : **corrigé** (§ 1). Si le joueur choisi comme source est bloqué (délai d'attente, poignée de main, réapparition, flux « véhicules » coupé), personne n'envoie. L'appareil est masqué après 30 s côté API, 10 min sur la page 9-LINE. Le flux `vehicles` coupé côté communauté jette les lots `flight` sans erreur visible (`flow_off`). |
| Marqueurs | jeu → serveur → web | événements +0,4 s, file toutes les 5 s, reprise complète toutes les 90 s ; TOC 10 s | SendMarker, netSend du téléphone | ShortCache 2 s | Le même contenu n'est pas renvoyé pendant 60 s. Avec la liaison par relais sans relais à portée, l'envoi attend dans la file du téléphone sans limite. |
| Marqueurs, tracés (site → jeu) | serveur → jeu | GetMarkers 8 s, GetMapShapes 10 s | sondage | ETag (COMSPEC Link) | — |
| Ordres | dans les deux sens | GetOrders 8 s ; envoi à l'événement ; TOC 15 s | sondage, netSend | ETag | Ordre « en transit radio » jusqu'à `deliver_at` pour les destinataires (voulu). Un ordre sauté est repris après 45 s. |
| Messages (chat) | dans les deux sens | GetChatMessages 6 s ; mobile 3 s | sondage, netSend | aucun | Un message non urgent peut être évincé de la file du téléphone, plafonnée à 50 envois. Une rafale se lit en plusieurs passages. |
| Comptes rendus (BDA, SITREP) | jeu → serveur → TOC | à l'événement ; TOC 10 s | netSend, SubmitTacticalReport | ETag (TOC) | Hypothèse : « BDA » ne fait pas partie des envois urgents de netSend. |
| Photos | jeu → serveur | à l'événement, selon la taille (600 Ko) | netSend découpé, StageCapture, NotifyNewPhoto | aucun | Sur liaison dégradée (≤ 1 barre ou < 96 kbit/s), un envoi de 100 Ko et plus est **retenu sans limite de temps**. Les photos sont les premières évincées de la file. Le calque Photos du TOC est éteint par défaut. |
| 9-LINE, briefs air → pilote | serveur → jeu | sondage 12 s **seulement à bord d'un aéronef** | GetCASForCallsign | no-store (page) | Un pilote à pied ne reçoit rien. État de livraison : PR `fix/pilote-notification-9line` (autre agent). |
| Accusés du pilote (WILCO…) | jeu → serveur | à l'événement | netSend → SendCASState | — | Si Athena n'est pas prête au moment du départ, l'accusé est **perdu** sans nouvel essai (fn_airBrief:62). |
| MEDEVAC, alertes médicales | dans les deux sens | événement urgent ; sondage 8 s | TriageMedicalAlert, GetMedicalAlerts | ShortCache 3 s | Hypothèse : le côté jeu passe par la signature médicale d'UpdatePosition, avec les mêmes blocages. |
| TOC | jeu → serveur → TOC web | ≤ 3 s si changement, battement 60 s ; TOC web 3 s | BriefingPlan.Push, une source par camp | ETag, tranche de 5 s | La relève de la source ne se fait qu'après 90 à 120 s de silence. |
| Sessions, heures de jeu | jeu → serveur | envoi groupé toutes les 5 min | ReportPlaytime | — | Rien n'est envoyé à la déconnexion : jusqu'à 5 min perdues par session. Le résultat n'est pas relu et l'envoi n'est pas repris. |
| Qualifications (téléphone) | serveur → jeu | au rendu de la page | GetPlayerAvatarInfo | uiNamespace | Relues **une fois par session de jeu**, si bien qu'une qualification accordée en cours de partie n'apparaissait jamais. **Corrigé** : relecture au plus toutes les 15 min (fn_avatarPath). |
| SSE : avis, signalements, alertes | dans les deux sens | GetTacticalAlerts 10 s ; fiches 20 s, alertes 20 s | sondage | Pack identité SSE : TTL 300 s | Le pack n'est invalidé que par une écriture HTTP dont le chemin correspond à `SseIdentityPackCache` : une autre écriture attend jusqu'à 5 min. Les fiches n'ont pas de génération : elles attendent la fin du TTL. |
| État des relais | jeu → serveur | 12 s, ≤ 120 s si rien ne change | UpdateRelay | — | Sauté si le hub serveur est actif. Le calque du TOC est éteint par défaut. |
| Réglages serveur (flux de données, modules…) | serveur → jeu | DataFlows 30 s, modules 45 s, plan 30 s | sondage | fichier par communauté | En cas d'échec du sondage, la dernière valeur est gardée. Un flux coupé jette silencieusement ses événements (véhicules, combat, contexte). |
| Miroir des notifications | jeu → serveur | 10 s | webNotifMirror | — | Coupé par le flux `phone_mirror`. |

**Sauvegarde différée du profil Arma (`fn_profileSave`).** Elle n'écrit que le fichier du profil sur le disque, au plus
tôt 30 s après le changement et au plus tard après 10 min. **Aucun envoi au serveur n'attend cette sauvegarde** :
chaque lecture de `profileNamespace` lit la mémoire, à jour tout de suite. Le seul risque est un plantage du jeu avant
l'écriture : l'indicatif ou le rôle choisis sur le téléphone sont alors perdus pour la session suivante.

**Coupures communes.** Elles touchent presque tous les flux :
- `COMSPEC_AthenaReady` à faux. Après un refus définitif (OW/fn_extensionCallback:164-183), rien ne la remet à vrai
  sans reconnexion.
- `COMSPEC_ApiBackoffUntil` et `COMSPEC_SendBackoffSec`, jusqu'à 600 s.
- `fn_uplinkHold` : joueur sans téléphone ou liaison désactivée. **Rien ne part.**
- File du téléphone (`fn_netSend`) : à 0 barre, les envois attendent. La file est plafonnée à 50 envois et perdue à la
  déconnexion.
- Mode de liaison « par relais » sans relais à portée : 0 barre tant que dure la situation.

## 3. Flux qui peuvent dépasser une minute ou ne jamais arriver

Du plus grave au moins grave.

| # | Flux | Pourquoi | État | Estimation |
|---|---|---|---|---|
| 1 | Indicatif d'appareil | figé à la première remontée | **corrigé** | — |
| 2 | Tout Athena | refus définitif de session : plus rien ne part, sans reprise automatique | à faire : nouvel essai espacé (5 min) ou message dans le téléphone (`fn_notify`) | 0,5 j |
| 3 | File du téléphone | liaison par relais sans relais : attente sans limite, plafond de 50 envois, perte à la déconnexion | à faire : afficher l'âge du plus vieil envoi en attente (fait en partie : compteur dans Debug) ; garder la file au-delà de 50 pour les urgents | 1 j |
| 4 | Photos et gros envois | retenus sans limite sur liaison dégradée | à faire : délai maximal puis envoi par morceaux, ou notification « photo en attente » | 0,5 j |
| 5 | Aéronefs | une seule source : si elle est bloquée, personne n'envoie | à faire : un second client prend le relais quand la ligne serveur a plus de 15 s (âge lisible par `GET /9-line/appareils`) | 1 j |
| 6 | Accusés WILCO / UNABLE | perdus si Athena n'est pas prête au départ (fn_airBrief:62) | à faire dans la PR des briefs air (fichier tenu par un autre agent) : remettre en file au lieu de jeter | 0,25 j |
| 7 | Briefs air → pilote | lus seulement à bord d'un aéronef | à faire : sondage lent (60 s) à pied | 0,25 j |
| 8 | Fiche opérateur | champs hors empreinte jamais renvoyés | à faire : ajouter armes secondaires et objets à l'empreinte, ou battement de 15 min | 0,25 j |
| 9 | Qualifications au téléphone | une fois par session | **corrigé** (15 min) | — |
| 10 | Indicatif changé sur le site | lu à l'arrivée seulement | à faire : relire avec `SyncProfile` (300 s) | 0,5 j |
| 11 | Battement de position et d'aéronef | jusqu'à 600 s avec `SendBackoffSec` | à voir : borner le battement à 60 s même en délai d'attente | 0,25 j |
| 12 | Squad.Sync | 5 min après un échec | **corrigé** | — |
| 13 | Heures de jeu | jusqu'à 5 min perdues par session | à faire : envoi au `HandleDisconnect` ou à l'`onUnload` de la mission (hors DLL) | 0,5 j |
| 14 | Pack identité SSE | jusqu'à 300 s si l'écriture ne passe pas par un chemin reconnu | à faire : invalider depuis les services d'écriture et non depuis le chemin HTTP | 0,5 j |
| 15 | Fuseau des horodatages (hypothèse) | `upsertAirAsset` écrit `date()` (PHP) alors que `airAssetsPilotStatus` écrit `NOW()` (base en UTC) : si PHP ne tourne pas en UTC, l'appareil disparaît après un statut pilote | à vérifier en production (`php -r 'echo date_default_timezone_get();'`) | 0,25 j |

Aucun changement de DLL n'a été fait ni n'est nécessaire pour ces correctifs. Pour les points 3 et 4, une file
persistante dans COMSPEC Link serait la solution propre. C'est à décider à part, avec un numéro de DLL.

## 4. Indicateurs ajoutés

- **Téléphone**, app Debug, onglet TRANSFERTS, section « Synchronisation par flux » (`AN/functions/pages/fn_pageDebug.sqf`).
  - Contenu :
    - la liaison : mode satellite ou relais, état, Athena prête ;
    - les écritures en attente : requêtes en cours, file sans réseau, profil à enregistrer ;
    - pour chaque flux, « remis il y a Xs », « lu il y a Xs », « échec il y a Xs (motif) » et « retenu il y a Xs »
      quand un envoi a été bloqué par `fn_uplinkHold` ;
    - les autres commandes vues.
  - Source : `OW/fn_syncMark.sqf`, appelée par `fn_extDirect` et `fn_extAwait` (connect et ATAK natif). Chaque appel
    fait une écriture de table, sans boucle et sans rien afficher à l'écran.
  - « Remis » veut dire pris en charge par COMSPEC Link (file de la DLL), pas forcément encore reçu par Athena.
- **Web** : bandeau discret « Données du jeu il y a Xs » sur la page 9-LINE, le TOC web et les outils opérateur
  (`public/assets/js/game-sync-freshness.js`, `public/assets/css/game-sync-freshness.css`).
  - Il lit `GET /synchro-jeu` toutes les 15 s (`no-store`) et relit tout de suite au retour sur l'onglet ou à la
    reprise du réseau.
  - Un clic ouvre le détail par flux.
  - Pastille verte : moins d'une minute. Orange : moins de 15 min. Rouge : au-delà.
  - Le bandeau reste caché pour un compte sans ATAK.
- **Diagnostic** : `GET /synchro-jeu/joueurs`, en lecture et réservé aux responsables ATAK (`AtakNineLineViewController::isManager`).
  - Il liste les joueurs vus ces deux dernières heures (`atak_ingest_player_daily`) avec l'âge de leur dernière donnée :
    requête du jeu, position, message, compte rendu.
  - Service : `App\Services\Tactical\GameSyncFreshnessService`, sans migration.

## 5. À valider en jeu

1. Monter dans un hélicoptère (groupe « Alpha 1-3 »), donner l'indicatif VIPER 1 dans BRIEFS AIR, puis vérifier que la
   page 9-LINE ouverte sur un téléphone passe à VIPER 1 en moins de 10 s avec « Indicatif changé (avant : Alpha 1-3) ».
2. Même essai avec deux appareils, dont un qui porte déjà VIPER 1 : le second doit garder son ancien indicatif.
3. Ouvrir l'app Debug, onglet TRANSFERTS : les flux doivent afficher « remis il y a … ». Retirer le téléphone de
   l'inventaire puis le reprendre : les envois de l'intervalle doivent apparaître « retenu ».
4. Couper le réseau du PC une minute : le bandeau web doit passer à l'orange, puis revenir au vert à la reprise.
5. Accorder une qualification sur le site en cours de partie : elle doit apparaître au téléphone en 15 min au plus.
