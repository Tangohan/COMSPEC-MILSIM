# Audit réseau / backend — HTTP(S) DLL et PHP

Agent 4. Branche lue : `release/2.7.0`. Date : 2026-10-27. Aucun code modifié, aucun commit, aucun trafic vers `athena.ttrd.fr`, `athena-comspec.eu` ou un autre hôte réel.

Périmètre : client HTTP de la DLL (`mod/UptoDate/COMSPECExtension/`, COMSPEC Link et ATAK natif, même source), routes `/api/atak` et `/api/game/v1`, relais, positions BFT, messages, photos, drones (commandes web), tuiles, état de liaison. Caches, relances, délais, concurrence, doublons, reprise, frontières de confiance.

Statut de chaque problème : **confirmé** (lu dans le code, mécanisme certain), **hypothèse** (mécanisme lu, fréquence ou effet en session non mesuré), **facultatif** (écart réel mais faible ou déjà borné).

## Plancher de version (LIVE, non retesté)

Noté par l’orchestrateur le 2026-10-27, sans nouvelle sonde :

- `2.0.95` et `1.0.0-native` : refus `403` `MOD_OUTDATED` sur les deux domaines.
- `2.0.99` passe le plancher, puis `401` (session absente, comportement attendu).
- `GET /api/atak/version` reste ouvert et annonce `2.7.0` / `2.0.99`.

La DLL de cette branche annonce `ExtensionVersion = 2.0.99` (`Extension.cs:48`). Le portail refuse avant la session (`public/index.php` via `GameClientVersionGate`). Le corps des sondages refusés est `403` avec `error = unauthorized` et `code = MOD_OUTDATED` (`GameClientVersionGate.php:34-36`). `unauthorized` est dans les refus définitifs de la DLL (`Extension_RefusalLatch.cs:20-24`) : une DLL sous le plancher cesse de sonder au lieu de boucler. Ce comportement n’a pas été rejoué contre la production.

## Banc de charge

**Non exécuté.** Aucun chiffre de latence, de débit ou de perte n’est inventé.

`tools/` ne contient pas de banc capable de simuler latence, gigue, perte en rafales, coupures, saturation de relais et clients concurrents, ni de mesurer p50/p95/p99. `tools/sec001-bench/` est un harnais de non-fuite de secrets (boucle locale, réflexion sur la DLL). Il ne mesure pas la latence. Il n’a pas été lancé.

### Plan d’un banc local isolé (à construire, pas lancé)

Tout le trafic reste sur `127.0.0.1` (et éventuellement `127.0.0.2` pour un hôte « non Athena »). Aucune résolution DNS de `athena.ttrd.fr` ni `athena-comspec.eu`. La DLL n’accepte ces secrets en HTTP clair que si `COMSPEC_DEV_TRUST_LOOPBACK=1` est posé avant le chargement (`Extension.cs:3184-3223`). Le banc doit poser cette variable lui-même. Secrets factices. Espace réseau isolé si l’OS le permet (`unshare -n` sous Linux ; sous Windows, pare-feu sortant qui n’autorise que la boucle locale).

Processus :

1. PHP de cette branche, document root `public/`, écoute `127.0.0.1` sur un port haut, base locale jetable, plancher de version vide ou égal à `2.0.99`.
2. Proxy toxique local devant PHP (Toxiproxy ou équivalent) : latence fixe, gigue, coupure TCP, perte en rafales (par exemple 30 % pendant 5 s puis 0), limite de débit, fermeture de connexion au milieu d’une réponse.
3. Un ou N clients qui parlent le même HTTP que la DLL (ou la DLL managée chargée par réflexion, comme SEC-001, avec `_baseUrl` forcé sur la boucle locale). Jamais d’adresse de production dans la file disque.
4. Scénarios, chacun avec un journal horodaté des requêtes et des codes :
   - position coalescée à 1 Hz et à 250 ms, avec et sans lot `/api/atak/telemetry/batch` ;
   - coupure totale 30 s, 3 min, 12 min (au-delà du palier 600 s) puis retour ;
   - `429` avec `Retry-After` court, puis `503` d’une seule route ;
   - timeout serveur après écriture engagée (réponse coupée) pour voir les doublons ;
   - 8, 32 et 64 clients concurrents sur le même indicatif et sur des indicatifs distincts ;
   - photo PNG, tuile `/map-photo`, message `/api/chat`, commande drone, `POST /api/atak/disconnect` pendant la coupure ;
   - session de 2 h avec le minuteur de vidage réel.
5. Mesures à enregistrer, pas à estimer : p50/p95/p99 du temps client (la DLL a déjà une latence moyenne et un p95 dans le journal d’envoi, `Extension_SendJournal.cs`), débit requêtes/s et octets/s, mémoire du process client (`FormatMemSnapshot`, `Extension.cs:174`) et du process PHP, taux d’erreur par code, doublons (lignes `atak_units` par indicatif, messages chat, événements de lot rejoués, commandes drone exécutées deux fois), état `linked` / `delayed` / `offline` pendant la pause.

Le proxy toxique ne représente pas la saturation radio du relais en jeu. Celle-ci est calculée dans le mod (conditions de réseau), pas sur le fil HTTP. Le banc HTTP mesure Athena. Un second volet, hors HTTP, rejouerait les conditions de relais dans une mission locale ; il n’existe pas non plus dans `tools/`.

## Ce que le chemin fait déjà

Un process Arma, un `HttpClient` partagé, délai plafond 8 s (`Extension.cs:31`, `Extension.cs:64`). Photos : second client, 60 s (`Extension.cs:32`, `Extension.cs:66`). Tuiles publiques : troisième client, 30 s, redirections suivies, aucun secret (`Extension_MapPhoto.cs:82-85`). Les clients porteurs de secrets ne suivent pas les redirections (`Extension.cs:131-138`). Secrets uniquement vers `athena.ttrd.fr`, `athena-comspec.eu`, et la boucle locale si la variable de développement est posée (`Extension.cs:3208-3223`). File disque sans clé ni jeton (`Extension.cs:1866-1928`). Envoi repris vers un autre hôte abandonné (`Extension.cs:1638-1651`).

Positions : une seule en attente, la plus récente (`Extension.cs:73-77`, `Extension.cs:1162-1167`). Marqueurs : une entrée par nom. Lot télémétrie prioritaire, 24 événements, files bornées (`Extension_Telemetry.cs:18-20`). Vidage : au plus 3 envois par passage, chevauchement refusé (`Extension.cs:1748-1769`). Erreur 5xx d’une route : pause de cette route seulement, 30/60/120/300 s (`Extension.cs:1002-1059`). Sondages GET : cache 1,2 s, dernier succès conservé, paliers après refus (`Extension.cs:2603-2624`). Tuiles photo : 4 à la fois, environ 3 Mo/s, reprise par fichier déjà présent (`Extension_MapPhoto.cs:80-81`). `GamePostJson` / `GameGetJson` refusent un hôte non sûr avant d’attacher le Bearer (`GameAuth.cs:1027-1029`). Le corps gzip du lot est décompressé côté PHP dans la limite de 2 Mo (`HttpJsonBody.php:12`, `HttpJsonBody.php:65-74`). Les ordres de chat ont un identifiant externe. Les alertes médicales automatiques ont une dédup de 300 s (`AtakApiController.php:9150-9169`). Les relais HTTP ont une clé dupliquée à l’upsert (`AtakRelayRepository.php:53-58`). Les morceaux de terrain passent par `upsertChunk` (rejeu du même carreau remplaçant).

La saturation « radio » affichée au joueur (débit, gigue, pertes en rafales sur un relais) n’est pas le HTTP Athena. Le registre `POST /api/atak/relays` décrit les tours. Il ne porte pas le canal radio.

## Problèmes

### NET-001 — Pause d’envoi jusqu’à 10 min, carte BFT hors liaison à 3 min

- **Statut :** confirmé (mécanisme). Fréquence en session saine avec le lot : hypothèse.
- **Gravité :** haute.
- **Fichiers :** `mod/UptoDate/COMSPECExtension/Extension.cs:96` (échelle 45, 75, 150, 300, 600 s), `Extension.cs:1280-1284` et `Extension.cs:1745` (toute la file s’arrête), `app/Repositories/AtakDataRepository.php:23` (`UNIT_LIVE_TTL_SECONDS = 180`), `AtakDataRepository.php:305-309` (hors liaison après 180 s, signal différé après 108 s), `AtakDataRepository.php:589-592` (bascule à la lecture des unités).
- **Symptôme :** après plusieurs échecs ou `429`, le téléphone se tait. Au-delà d’environ 108 s sans position, l’opérateur passe en signal différé. Au-delà de 180 s, la prochaine lecture de la carte le passe hors liaison alors qu’il est encore en jeu. Au palier 300 s ou 600 s, la fiche reste morte plusieurs minutes après le retour du réseau.
- **Cause :** la pause est globale (position comprise). Le délai de vie de la fiche est plus court que les trois derniers paliers. Le lot télémétrie, quand il répond, ne compte qu’une écriture « telemetry » et peut ne jamais entrer dans l’échelle. Le repli unitaire, ou une vraie coupure, y entre.
- **Preuve :** lecture croisée des constantes et du `return` en tête de `DrainQueue`. Aucune mesure de durée réelle.
- **Impact :** le poste perd le BFT et peut clôturer la présence alors que la session Arma tient. Les photos attendent la même pause (NET-012).
- **Correction (non appliquée) :** ne pas soumettre la position coalescée au palier au-delà de 90 s, ou émettre un battement de présence exempté pendant la pause, et aligner le délai de vie (180 s) sur le palier le plus long seulement si le produit accepte une fiche « gelée » dix minutes. Le `Retry-After` annoncé ne doit pas être allongé par l’échelle au-delà du délai de vie.
- **Validation :** scénario « coupure 3 min / 12 min » du banc local. Vérifier `linked` pendant la pause et le retour de la dernière position, sans doublon de fiche.

### NET-002 — Plafond positions incompatible avec le rythme du client en repli unitaire

- **Statut :** confirmé.
- **Gravité :** haute dès que `/api/atak/telemetry/batch` est absent, en `404/405/501`, ou en erreur de route (repli unitaire). Moyenne tant que le lot absorbe le flux.
- **Fichiers :** `app/Support/AtakArmaWriteGuard.php:298-321` (150 écritures / 60 s toutes routes, 30 positions / 60 s, 10 positions / 5 s), `Extension.cs:81` et `Extension.cs:954-956` (vidage 1 s par défaut, borné 250 ms–2 s), `AtakApiController.php:6370` (le lot compte une fois en canal `telemetry`, pas `position`), `AtakApiController.php:6612` (`ingestPositionPayload` ne repasse pas le plafond position).
- **Symptôme :** en repli, une position par seconde dépasse 30/min. À 250 ms, la rafale 10/5 s cède en moins de 3 s. Athena répond `429` avec `Retry-After`. La DLL entre dans NET-001.
- **Cause :** le plafond a été écrit pour un client bavard. Le lot le contourne (une frappe de compteur pour jusqu’à 24 événements). Le repli retombe sur le plafond serré, au rythme que le minuteur est autorisé à tenir.
- **Preuve :** les trois seuils et le canal sont dans le garde. Le contrôleur de lot n’appelle pas le canal `position`.
- **Impact :** mission dont le lot est refusé : BFT en dents de scie, puis pause longue. Avec le lot : le plafond position ne protège plus un client qui enverrait beaucoup d’événements non coalescés dans le même lot (le global 150/60 s reste).
- **Correction (non appliquée) :** un seul budget, appliqué au lot et à l’unitaire, exprimé en positions/s et non en deux plafonds contradictoires. Le `429` du lot doit nommer le même `Retry-After` que l’unitaire.
- **Validation :** deux runs du banc, lot activé puis route batch désactivée, 1 Hz pendant 3 min. Compter les `429` et l’état de fiche. Aucun trafic hors boucle locale.

### NET-003 — Deux fiches BFT pour le même indicatif

- **Statut :** confirmé (course). Fréquence : hypothèse, surtout au premier contact ou si deux clients portent le même indicatif.
- **Gravité :** haute.
- **Fichiers :** `migrations/schema.sql:780-800` (index `map_callsign` non unique), `app/Repositories/AtakDataRepository.php:1396-1506` (`SELECT` puis `INSERT` sans transaction ni verrou).
- **Symptôme :** deux lignes `atak_units` pour le même tenant, la même carte et le même indicatif. Les lectures suivantes mettent à jour l’une ou l’autre. Le poste voit un fantôme, ou une position qui saute.
- **Cause :** deux requêtes simultanées ne trouvent rien, puis insèrent toutes les deux. Rien dans le schéma ne l’interdit. L’upsert des relais, lui, a une clé dupliquée.
- **Preuve :** schéma et ordre `SELECT` / `INSERT`. Pas de reproduction sous charge (banc non exécuté).
- **Impact :** BFT doublon, mauvais rattachement Steam / identifiant militaire, fiche hors liaison qui ne correspond pas au joueur vivant.
- **Correction (non appliquée) :** contrainte unique `(tenant_id, map_id, call_sign)` après fusion des doublons existants, et `INSERT ... ON DUPLICATE KEY UPDATE` dans la même requête. Ne pas inventer de fusion automatique sans regarder les fiches déjà en base.
- **Validation :** 32 clients locaux, même indicatif, premier insert parallèle. Une seule ligne. Rejeu d’une position plus ancienne ne doit pas créer une seconde ligne (horodatage client, voir NET-004).

### NET-004 — Le lot n’est pas idempotent, et un succès partiel est traité comme un succès total

- **Statut :** confirmé.
- **Gravité :** haute pour un événement joué une seule fois (alerte, combat, état). Basse pour une position déjà coalescée.
- **Fichiers :** `Extension_Telemetry.cs:543-548` (`seq` et `ts` envoyés), `Extension_Telemetry.cs:634-654` (tout code 2xx vide la file, `results` ignoré), `Extension_Telemetry.cs:656-665` (exception : les événements repartent), `AtakApiController.php:6412-6415` (HTTP 200 dès qu’un événement du lot est accepté, 422 seulement si tous sont refusés), `app/Services/Tactical/AtakTelemetryBatchIngest.php:99` (`seq` recopié dans la réponse, jamais comparé à un dernier `seq` par client).
- **Symptôme :** un lot coupé après écriture (délai 8 s, proxy qui coupe la réponse) est renvoyé. Les événements one-shot sont rejoués. À l’inverse, un lot HTTP 200 dont une position ou une alerte est dans `rejected` est oublié par la DLL.
- **Cause :** au-moins-une-fois sans clé d’idempotence. Le numéro de lot n’est pas une frontière de reprise. Le client ne lit pas le détail.
- **Preuve :** les trois sites ci-dessus. `upsertUnitPosition` écrase avec `updated_at = NOW()` sans comparer un horodatage client (`AtakDataRepository.php:1449`), donc un lot ancien arrivé après un lot récent recule la position.
- **Impact :** alertes ou messages de lot en double. Position qui revient en arrière après une reprise. Événement refusé (coordonnées, flux) perdu parce que son voisin a été accepté.
- **Correction (non appliquée) :** mémoriser le dernier `seq` accepté par client et ignorer `seq` inférieur ou égal. Réponse 207 ou 200 avec obligation pour la DLL de rejouer uniquement les index `ok: false` transitoires. Refuser une position dont l’horodatage client est plus vieux que la fiche.
- **Validation :** proxy qui accepte l’écriture puis coupe la réponse. Un seul événement one-shot en base. Un second run avec un événement invalide au milieu d’un lot valide : le valide reste, l’invalide est retenté ou abandonné explicitement, pas les deux.

### NET-005 — Message radio rejoué en double

- **Statut :** confirmé pour le chemin d’échec réseau. La dédup médicale 300 s ne couvre pas le texte libre.
- **Gravité :** moyenne.
- **Fichiers :** `Extension.cs:9244-9276` et `Extension.cs:9617-9623` (`SendChat` part tout de suite), `Extension.cs:1574-1575` et `Extension.cs:1587-1589` (timeout, 408, 425, 5xx : remise en file du même corps), `AtakApiController.php:9049-9172` (`addChatMessage` à chaque POST, dédup seulement pour inconscient / arrêt cardiaque).
- **Symptôme :** un message dont la réponse s’est perdue réapparaît deux fois dans le journal radio. Un `422` (ordre incomplet, auteur réservé) n’est pas rejoué : celui-là est perdu, ce qui est cohérent.
- **Cause :** pas d’identifiant de message client. La relance renvoie le même texte.
- **Preuve :** pas de champ d’idempotence dans `chatStore` (recherche `client_msg` / `message_uid` vide). La relance est inconditionnelle pour les codes listés.
- **Impact :** ordres lus deux fois, faux doublon d’alerte si le texte n’est pas le format médical reconnu.
- **Correction (non appliquée) :** identifiant client stable, contrainte unique, réponse 200 si déjà enregistré. Ne pas remettre en file un `4xx` de validation.
- **Validation :** un POST chat accepté dont la réponse est coupée, puis le rejeu. Une seule ligne.

### NET-006 — Commande drone (et charge) livrée sans réservation

- **Statut :** confirmé pour la lecture. Effet « exécutée deux fois » : hypothèse, selon que le téléphone accuse réception avant d’agir.
- **Gravité :** moyenne.
- **Fichiers :** `app/Repositories/AtakWebCommandRepository.php:169-176` (sélection `pending`, aucun passage à `accepted` à la lecture), `AtakWebCommandRepository.php:195-216` (le statut change à l’accusé), `routes/web.php:2700-2704`, commentaire de livraison dans `AtakDroneRemoteController.php:17-20`. La DLL n’appelle pas `/api/atak/web-commands` ; le jeu lit ces ordres via le sondage des commandes (fusion `wireRows`).
- **Symptôme :** l’ordre reste `pending` tant que l’accusé n’est pas arrivé. Un sondage suivant, ou un second téléphone du même filtre, revoit le même ordre. S’il a déjà été exécuté, le drone ou la charge repart.
- **Cause :** pas de prise en charge atomique à la lecture (`UPDATE ... WHERE status = pending` puis lecture de la ligne prise). L’expiration existe (`expireStale`) mais n’est pas faite sur le sondage chaud, volontairement (`AtakWebCommandRepository.php:167-168`).
- **Preuve :** le `SELECT` ne modifie pas le statut.
- **Impact :** ordre drone ou destruction rejoué après une perte de l’accusé. Fenêtre bornée par `ttl_seconds`.
- **Correction (non appliquée) :** réservation atomique au premier lecteur autorisé, accusé idempotent, second lecteur qui ne reçoit plus la ligne `pending`.
- **Validation :** deux sondages locaux avant l’accusé. Un seul reçoit l’ordre. L’accusé en double ne change plus le statut final.

### NET-007 — Météo et flux vidéo retirés de la file sans remise

- **Statut :** confirmé.
- **Gravité :** basse.
- **Fichier :** `Extension.cs:1808-1809`.
- **Symptôme :** un envoi météo ou vidéo déjà en file disparaît si la pause « best effort » est active au moment du vidage. Le prochain cycle du jeu peut le remplacer. Un échantillon isolé est perdu.
- **Cause :** `TryDequeue` a déjà retiré l’élément. La branche `continue` ne rappelle pas `EnqueueForRetry`. L’entrée immédiate, elle, abandonne aussi sans file si la pause est déjà là (`Extension.cs:2412-2423`).
- **Preuve :** les deux branches. Les positions, elles, sont remises (`Extension.cs:1810-1814`).
- **Impact :** trou dans la météo ou les flux, pas dans le BFT. Les flux sont conçus comme au mieux.
- **Correction (non appliquée) :** remettre l’élément, ou ne pas le retirer tant que la pause tient. Borner la file pour ne pas la remplir d’échantillons périmés.
- **Validation :** pause best-effort armée, un élément vidéo en file, un passage de `DrainQueue`. L’élément est encore là ou explicitement compté comme abandonné.

### NET-008 — Sauvegarde disque aveugle à un marqueur modifié

- **Statut :** confirmé.
- **Gravité :** moyenne (perte seulement si le process meurt dans la fenêtre).
- **Fichier :** `Extension.cs:1984-1989`.
- **Symptôme :** après un plantage ou un kill d’Arma, un marqueur mis à jour en mémoire n’est pas dans `queue.jsonl`, alors qu’une position coalescée ou la tête de file, inchangées, ont empêché l’écriture.
- **Cause :** la signature de « rien n’a changé » mélange le nombre d’entrées, l’URL et le corps de la position, et le corps en tête de file. Le corps des marqueurs n’entre pas dans la signature. Remplacer la valeur d’une clé existante ne change pas le nombre.
- **Preuve :** `HashCode.Combine` cité. L’écriture est de plus limitée à une fois toutes les 3 s (`Extension.cs:1970-1974`).
- **Impact :** reprise de mission sans le dernier déplacement de marqueur. La position, elle, a son fichier dédié.
- **Correction (non appliquée) :** inclure une empreinte du dictionnaire de marqueurs (ou un compteur monotone incrémenté à chaque `EnqueueForRetry`).
- **Validation :** test unitaire local de la DLL : mettre à jour un marqueur, forcer la persistance, tuer avant le cycle suivant, relire le fichier. Le nouveau corps est présent. Aucun hôte externe.

### NET-009 — Fin de liaison non reprise

- **Statut :** confirmé.
- **Gravité :** moyenne.
- **Fichiers :** `Extension.cs:829-857` (POST synchrone, 3 s, pas de file), `AtakApiController.php:4357-4416` (hors ligne seulement si cet appel réussit), `AtakDataRepository.php:23` et `AtakDataRepository.php:589-592` (sinon, attente du délai de vie à la prochaine lecture).
- **Symptôme :** quitter pendant un trou réseau laisse la fiche en liaison jusqu’à 180 s après le dernier mouvement, parfois plus si personne ne relit les unités. Les alertes médicales ouvertes ne sont clôturées que dans le chemin `disconnect` réussi.
- **Cause :** le délai court est voulu (le process meurt). L’échec n’est pas écrit dans la file disque, et la file ne sera de toute façon plus vidée.
- **Preuve :** `VerifyDisconnectSync` retourne l’erreur et s’arrête. Aucun `EnqueueForRetry`.
- **Impact :** opérateur encore « en liaison » au poste après avoir quitté. Inverse de NET-001.
- **Correction (non appliquée) :** côté serveur, accepter un battement manquant comme fin de liaison au TTL, y compris la clôture d’alertes, sans dépendre du POST de fin. Côté client, un dernier essai en file n’aide pas si le process est déjà mort.
- **Validation :** couper le proxy pendant le POST de fin. La fiche passe hors liaison au plus tard au TTL, une seule fois, sans second passage par un hôte réel.

### NET-010 — Limiteur de débit inopérant si le verrou fichier échoue

- **Statut :** confirmé pour le code. Déclenchement sous charge disque : hypothèse.
- **Gravité :** moyenne.
- **Fichier :** `app/Services/Security/FileRateLimiter.php:93-94` et `FileRateLimiter.php:118-119`.
- **Symptôme :** si `flock` échoue ou si l’écriture jette, le compteur retourné est 0. `tooManyAttempts` est alors faux. Les écritures jeu ne sont plus ralenties, précisément quand le disque de cache est en peine.
- **Cause :** échec ouvert. Le limiteur est un fichier JSON par clé sous `storage/cache/ratelimit`, un verrou par processus PHP. Plusieurs workers PHP se sérialisent sur ces fichiers à chaque position.
- **Preuve :** les deux `return 0`. Le garde traite `null` comme « pas de refus » (`AtakArmaWriteGuard.php:175-177`).
- **Impact :** rafale non bornée vers MySQL au moment où la machine est déjà chargée. Aggrave NET-003 et NET-011.
- **Correction (non appliquée) :** échec fermé (compter comme dépassement) quand le verrou ne peut pas être pris. Compteur en mémoire partagée ou `Redis` si le déploiement en a déjà un. Ne pas ajouter un service nouveau sans décision.
- **Validation :** test unitaire qui simule un `flock` impossible. La réponse est `429`. Banc local 64 clients : le taux de `429` reste celui du plafond, pas zéro.

### NET-011 — Chaque position relit tous les marqueurs de la carte

- **Statut :** confirmé.
- **Gravité :** haute dès que plusieurs opérateurs bougent sur une carte chargée en marqueurs.
- **Fichier :** `AtakApiController.php:6763-6798`. Le lot rappelle `ingestPositionPayload` pour chaque événement position (`AtakApiController.php:6395-6397`), donc le même coût par événement, pas par lot.
- **Symptôme :** le temps de `POST /api/atak/position` et du lot grandit avec le nombre de marqueurs, pas avec la position. Sous beaucoup de clients, les délais dépassent 8 s, la DLL classe ça en coupure (code 0) et entre dans NET-001.
- **Cause :** `getMarkers` charge la table, puis deux services de confirmation (arrivée sur point, détection) parcourent ces lignes. Les contacts proxy sortent avant la lecture. Un opérateur réel, non.
- **Preuve :** l’appel est dans le chemin synchrone de la requête, sans cache de requête ni index spatial.
- **Impact :** saturation PHP/MySQL corrélée au nombre de joueurs × marqueurs. P95/p99 non mesurés.
- **Correction (non appliquée) :** ne charger que les marqueurs dans un rayon, ou une structure déjà préparée, et une fois par lot plutôt qu’une fois par événement. Mesurer avant/après sur le banc local.
- **Validation :** carte locale avec un volume de marqueurs réaliste, 32 clients à 1 Hz. p95 du lot avant/après. Aucun hôte externe.

### NET-012 — Une seule file photo, bloquée par la pause globale

- **Statut :** confirmé.
- **Gravité :** moyenne.
- **Fichiers :** `Extension.cs:9884-9885` (le worker attend `IsRateLimitedNow`, qui est armé par la pause globale `Extension.cs:1280-1284`), `Extension.cs:312-324` (64 photos, 6 essais, dédup 300 s), `Extension.cs:65-66` (client d’envoi séparé, 60 s, ce qui est sain).
- **Symptôme :** pendant une pause BFT (429 ou coupure), les photos ne partent pas, alors que le commentaire d’architecture dit que la photo continue pendant la pause d’une route (`Extension.cs:1003-1005`). Au 6e essai ou si la file dépasse 64, la photo est abandonnée (`Extension.cs:9839-9844`).
- **Cause :** un worker, et la condition d’attente regarde la pause globale, pas seulement la pause de `/api/recon/images`.
- **Preuve :** la boucle `while (IsRateLimitedNow() || ...)`.
- **Impact :** photo de reco, visage ou pièce de fiche en retard de plusieurs minutes, ou perdue si la pause dure et que les essais sont consommés par des fichiers pas encore écrits (les attentes disque incrémentent aussi `Attempts`).
- **Correction (non appliquée) :** ne bloquer la photo que sur la pause de sa propre route et sur un `429` photo. Ne pas compter une attente de flush disque comme un échec HTTP.
- **Validation :** pause globale 45 s, une photo en file. Elle part pendant la pause BFT si la route photo répond. Pas de second envoi du même fichier dans les 300 s.

### NET-013 — Envois immédiats hors du plafond de 6 appels parallèles

- **Statut :** confirmé. Gravité réelle par client : facultatif (le vidage et le lot sont déjà bornés).
- **Gravité :** facultatif, devient moyenne si le script envoie beaucoup de chats ou de rapports hors lot.
- **Fichiers :** `Extension.cs:3417-3418` (`AsyncMaxParallel = 6` pour les commandes Async), `Extension.cs:2439` (`EnqueueOrSend` lance `SendAsync` sans ce sémaphore), `Extension.cs:2408-2409` (le correctif historique vise les relevés lourds, pas ce chemin).
- **Symptôme :** une rafale de `callExtension` hors file peut empiler des requêtes sur le même `HttpClient` (délai 8 s, pas de `MaxConnectionsPerServer` réduit, `Extension.cs:131-148`). Le commentaire d’époque décrit des timeouts de position classés à tort comme une saturation.
- **Cause :** deux politiques. Le vidage est borné à 3. Le chemin immédiat ne l’est pas.
- **Preuve :** absence de `AsyncGate.Wait` dans `EnqueueOrSend`.
- **Impact :** micro-coupures locales, entrée plus rapide dans NET-001. Un seul process Arma, donc pas une saturation multi-joueurs à lui seul.
- **Correction (non appliquée) :** le même budget que le vidage, ou mise en file de tout ce qui n’est pas le handshake.
- **Validation :** 100 POST immédiats locaux en rafale. Nombre de requêtes en vol ≤ budget. p99 du POST position du même process stable.

### NET-014 — Connexions HTTP gardées sans durée de vie

- **Statut :** hypothèse.
- **Gravité :** facultatif.
- **Fichier :** `Extension.cs:131-148`. `SocketsHttpHandler` sans `PooledConnectionLifetime` ni `ConnectTimeout`.
- **Symptôme :** une session de plusieurs heures peut garder une connexion vers une adresse IP qui n’est plus celle du nom, après un changement DNS ou un basculement entre les deux portails (le client ne change de nom que si le jeu rappelle l’adresse).
- **Cause :** durée de vie de pool laissée au défaut du runtime (connexion réutilisée jusqu’à l’oisiveté). Le délai de 8 s borne l’appel, pas la fraîcheur DNS.
- **Preuve :** les propriétés ne sont pas posées. Aucune session longue n’a été tenue dans cet audit.
- **Impact :** échecs jusqu’à ce que le pool recycle, puis reprise par le chemin réseau habituel. Les deux noms Athena restent une liste blanche, pas un basculement automatique.
- **Correction (non appliquée) :** `PooledConnectionLifetime` de quelques minutes et un délai de connexion explicite, inférieur à 8 s.
- **Validation :** banc local qui change l’adresse d’écoute derrière le même nom de boucle après 30 min. Le client suit sans redémarrage d’Arma.

### NET-015 — Registre des relais sans le garde d’écriture du jeu

- **Statut :** confirmé.
- **Gravité :** moyenne (frontière de confiance). Ce n’est pas la saturation radio.
- **Fichiers :** `AtakApiController.php:2227-2244` (`authArma` seulement, pas `guardArmaWrite`), `AtakRelayRepository.php:22-51` (IP, passerelle et certificat pris dans le corps, plafonnés en taille pour certains champs, pas de plafond de débit).
- **Symptôme :** une clé de communauté acceptée peut créer ou remplacer des tours sans plafond position/écriture et sans contrôle Steam du garde. Le corps peut porter une adresse ou un texte de certificat arbitraire, stockés tels quels.
- **Cause :** le garde utilisé pour la position, le chat et la déconnexion n’est pas appelé ici.
- **Preuve :** le corps de `relaysStore` cité. `relaysIndex` et `relaysDelete` ne passent pas non plus par ce garde (`AtakApiController.php:2211-2261`) : la suppression dépend de `requireTenant`.
- **Impact :** registre de tours falsifié ou spammé avec une clé volée. N’ouvre pas le canal radio réel. Ne pas confondre avec les pertes simulées du mod.
- **Correction (non appliquée) :** même garde que les autres écritures Arma, canal de plafond dédié, champs réseau limités à ce que le poste affiche vraiment.
- **Validation :** clé de test locale, 100 upserts en 10 s, attente d’un `429`. Corps avec certificat énorme refusé avant l’écriture.

### NET-016 — Envoi du fond carte lu en entier en mémoire

- **Statut :** confirmé. Exploitation : facultatif (la clé plateforme est exigée avant la lecture).
- **Gravité :** facultatif.
- **Fichier :** `app/Controllers/Api/AtakMapPhotoController.php:112-128`.
- **Symptôme :** `file_get_contents('php://input')` charge tout le corps, puis compare à 64 Mo. Un corps plus gros a déjà été lu. Les tuiles publiques, elles, ont un cache navigateur d’un jour et un `ETag` (`AtakMapPhotoController.php:42-54`).
- **Cause :** le plafond est appliqué après la lecture. Le commentaire de `HttpJsonBody` explique que ce schéma a déjà saturé PHP sur une capture.
- **Preuve :** ordre des lignes 125-127. L’authentification plateforme est avant (lignes 115-118), donc un anonyme ne déclenche pas la lecture.
- **Impact :** process PHP gonflé si la clé plateforme est présentée avec un gros corps. Les tuiles jouées en jeu passent par le client séparé, sans secret, avec reprise disque : ce chemin-là est sain.
- **Correction (non appliquée) :** refuser sur `CONTENT_LENGTH` avant de lire, et lire vers un fichier temporaire avec un compteur d’octets.
- **Validation :** requête locale avec `Content-Length` au-dessus du plafond. Réponse 413 sans allocation du corps. Clé factice.

## Frontières de confiance (tenu)

- Un seul point pose l’adresse du portail, hôtes Athena en HTTPS, boucle locale seulement en développement (`Extension.cs:3228-3240`).
- Secrets retirés si l’URI n’est pas sûre, y compris après redirection manuelle des images (`Extension.cs:3244-3253`, `Extension.cs:2492-2520`).
- File disque : secrets retirés à l’écriture, hôte non Athena abandonné au rejeu (`Extension.cs:1862-1928`, `Extension.cs:1634-1651`).
- Plancher LIVE rappelé en tête. Non retesté ici.
- `/api/game/v1` : même filtre d’hôte, Bearer du compte, délai 8 s (`GameAuth.cs:1021-1032`). `MOD_OUTDATED` est reconnu comme état de session (`GameAuth.cs:1007`).
- Tuiles `/map-photo` publiques, CORS ouvert, cache un jour : voulu pour le fond de carte. L’upload n’accepte que la clé plateforme.

## Hors périmètre de mesure

La gigue, les pertes en rafales et le débit réduit « quand plusieurs téléphones partagent un relais » sont une simulation dans le mod. Cet audit ne les a pas exécutées et ne leur attribue aucun chiffre. Le registre HTTP des relais est décrit en NET-015.

## Ordre suggéré si une correction est décidée plus tard

1. NET-003 (unicité BFT) et NET-004 (idempotence du lot) : intégrité des données.
2. NET-001 et NET-002 : présence réelle sous coupure et sous repli.
3. NET-011 et NET-010 : coût par position et limiteur qui lâche.
4. NET-005, NET-006, NET-009, NET-012 : doublons et fins de liaison.
5. NET-007, NET-008, NET-013, NET-014, NET-015, NET-016.

Aucune de ces corrections n’a été appliquée.

---

## Append — A4 reseau (27/10 soir, reprise)

Agents A4 ([reseau](a8f9943c-189c-4c0d-877e-c7ba623e8874)) : NET-003 / NET-004 restent **ouverts** (preuve schema + ingest). NET-001 partiel (echelle 45/75/90 + Retry-After 600 vs TTL BFT 180). LOT-NET : **document open only** sans decision produit (migration UNIQUE + idempotence lot). Pas de banc execute.
