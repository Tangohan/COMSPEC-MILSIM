# ICD-SSE-ALERTES-001 : avis, notices et fiches de vigilance depuis le jeu

Contrat entre le site (Athena), COMSPEC Link (`COMSPECExtension`, **sans nouvelle DLL**) et les apps du téléphone ATAK. Version 1.
Complète `ICD-SSE-FICHES.md` (lecture seule des fiches) : ici le jeu lit les **alertes** (avis de recherche, notices de couleur, fiches de
vigilance), en **émet**, les **renouvelle**, les **lève**, en **accuse lecture** et envoie des **signalements**.

Vocabulaire : une *alerte* est soit un avis de recherche (`doc = avis`, table `sse_wanted_notices`), soit une fiche de vigilance émise
(`doc = vigilance`, table `sse_doc_issues`, modèle `vigilance`). Une *notice de couleur* est un avis dont le type porte une couleur (§ 3.1).

## 1. Canal, authentification, gardes

- Canal du jeu : `POST /api/sse/identity/checks` (commande `SubmitIdCheck`), corps JSON `{"kind":"sse_alertes","op":"…", …}`.
  Réponse : `{id, ref, page, next, pages}` ; `ref` est une page `parseSimpleArray` de **7 400 octets au plus** (`SseFichesGameService::PAGE_BYTES`).
  `\p` est une barre verticale, `\n` un retour à la ligne (mêmes règles que `sse_fiches`, `SseLoreService::sqf`).
- Routes REST équivalentes (JSON) : § 5.
- Authentification : clé de communauté ou jeton de jeu (garde de `sse_fiches`). La communauté est **celle de la clé**, jamais un champ du corps.
- Niveau de lecture : celui du compte du jeton (`SseMemberClearance`) ; sans compte, le plus bas (`interne`).
- **Écritures** (`create`, `report`, `renew`, `lift`, `ack`) : un jeton de jeu relié à un compte est obligatoire (clé de communauté seule : `403 account_required`).
- Débit par compte : lectures 120/min ; `report` 12 / 10 min ; `create` 6 / 10 min ; `renew` + `lift` 12 / 10 min ; `ack` 60 / 10 min. Dépassement : `429` + `retry_after`.
- Maintenance : `503`. Étape de base 4830 absente : lectures servies (sans adresse de signalement, `url` et `token` vides), écritures `503 unavailable`.
- Journal `sse_audit_log`, `object_type` `game_alert` : `alertes.read` (une fois par 10 min), `alertes.detail`, `alertes.create`, `alertes.proposal`,
  `alertes.report`, `alertes.renew`, `alertes.lift`, `alertes.ack`, et les refus `alertes.refused`.
- Jamais envoyé : identité de membre (`sse_identities.user_id > 0`), image, état civil, rien au-dessus de l'habilitation du compte. Une alerte hors
  habilitation répond comme une référence inconnue (`404 not_found`), pour toutes les opérations.

Erreurs : statut HTTP et `{"error": code, "message": texte}`. Codes : `unauthorized` 401, `account_required` 403, `forbidden` 403, `not_found` 404,
`invalid` 422 (+ `errors: [texte…]`), `closed` 409 (alerte levée ou expirée), `rate_limited` 429, `unavailable` 503, `maintenance` 503.

## 2. Idempotence

`create`, `report`, `renew` et `lift` acceptent `cid` : identifiant client de 8 à 64 caractères `[A-Za-z0-9_-]`, unique par communauté, choisi par le poste
(ex. `<uid>-<horloge>-<n>`). Un renvoi avec le même `cid` ne crée rien et **rend la même réponse** que le premier envoi (drapeau `replay` à `true`).
Sans `cid`, pas de protection contre les doublons.

## 3. Opérations

### 3.1 `catalog`

Corps : `{"kind":"sse_alertes","op":"catalog","rev":"","page":0,"lang":"fr"}`.

```
[1, "catalog", "révision", page, page suivante (0 = fin), [ligne…], [peut émettre vigilance, peut placer avis, peut lever, peut signaler]]
```

Révision déjà connue (`rev`) : `[1, "catalog", "révision", 0, 0, [], [droits…], true]`. La révision est l'empreinte du catalogue de la communauté (16 caractères) ;
les droits sont ceux du compte et n'entrent pas dans l'empreinte. Une ligne commence par sa section :

| Section | Colonnes après `"section"` |
|---|---|
| `level` | `clé, fr, en, rang (1 à 4), couleur #RRGGBB, forme` : niveaux de vigilance (§ 3.1.1) |
| `type` | `clé, fr, en, couleur de notice (clé ou ""), titre fr, titre en, sous-titre fr, sous-titre en` : types d'avis de la communauté |
| `color` | `clé, fr, en, #RRGGBB` : couleurs de notice (`red, blue, green, yellow, orange, black`) |
| `reason` | `code M-…, fr, en, famille, menace suggérée` |
| `measure` | `code A-…, fr, en, famille, menace d'usage` |
| `service` | `code SVC-…, fr, en, sigle, #RRGGBB, forme de l'emblème générique, référence d'emblème ("" ou "svc:<code>:<version>"), par défaut ?, canal` : services à informer |
| `delay` | `clé, fr, en` : délais de compte rendu (`now, 15m, 1h, 4h, 24h`) |
| `sure` | `clé, fr, en` : certitude d'un signalement (`low, medium, high`) |
| `threat` | `clé, fr, en` ; `armed` : `clé, fr, en` |

La référence d'emblème n'est qu'une étiquette de cache : **aucune image ne passe par le canal**, le téléphone dessine l'emblème générique (forme + couleur + sigle).

#### 3.1.1 Niveaux de vigilance

Une seule échelle, celle de la menace des avis (`SseWantedCatalog::THREATS`), relevée par l'armement (`App\Support\SseAlertLevel`) :

| Rang | Clé | Titre FR / EN | Couleur | Forme | Règle |
|---|---|---|---|---|---|
| 1 | `watch` | VIGILANCE / BE ON THE LOOKOUT | jaune `#F2C200` | `triangle` | menace faible ou non précisée |
| 2 | `reinforced` | VIGILANCE RENFORCÉE / HEIGHTENED VIGILANCE | ambre `#F08C00` | `triangle` | menace moyenne |
| 3 | `caution` | APPROCHE AVEC PRÉCAUTION / APPROACH WITH CAUTION | orange foncé `#D9480F` | `diamond` | menace élevée ou critique |
| 4 | `danger` | DANGER — ARMÉ ET DANGEREUX / DANGER — ARMED AND DANGEROUS | rouge `#B0121F` | `octagon` | armé (`armed = yes`), quelle que soit la menace |

Consigne dominante (deuxième ligne du bloc d'alerte), déduite des mesures : `A-NOI` ou `A-OBS` sans `A-ARR`/`A-DET`/`A-LIV`/`A-NEU` : « NE PAS APPRÉHENDER — SIGNALER » /
« DO NOT APPREHEND — REPORT » (`hold`) ; avec une mesure d'intervention : « INTERVENTION SUR ORDRE » / « ACTION ON ORDER » (`order`) ; sinon vide.
Le niveau ne dépend jamais de la seule couleur : rang, titre et forme l'écrivent aussi.

### 3.2 `list`

Corps : `{"kind":"sse_alertes","op":"list","rev":"","page":0,"lang":"fr","doc":""}` (`doc` : `avis`, `vigilance` ou vide).

```
[1, "list", "révision", "généré le", ttl, page, page suivante, [alerte…], [niveau de lecture, marquage, total, tronqué]]
```

Révision inchangée : `[1, "list", "révision", "généré le", ttl, 0, 0, [], [], true]`. Tri : rang décroissant, puis mise à jour. 80 alertes au plus.
Une alerte est le tableau des colonnes (`SseAlertGameService::COLUMNS`), dans l'ordre :

| # | Colonne | Contenu |
|---|---|---|
| 0 | `ref` | référence (`A-12/2026`, `FV-2026-0007`) |
| 1 | `doc` | `avis` ou `vigilance` |
| 2 | `type` | clé du type d'avis ; `vigilance` pour une fiche |
| 3 | `color` | couleur de notice (`red`…), `""` si l'avis n'est pas une notice de couleur ou pour une fiche |
| 4 | `rank` | rang 1 à 4 |
| 5 | `level` | clé du niveau (`watch`…) |
| 6 | `order` | consigne dominante : `hold`, `order` ou `""` |
| 7 | `name` | sujet selon habilitation (barre de caviardage si masqué) |
| 8 | `alias` | alias (3 au plus) |
| 9 | `threat` | `low, medium, high, critical` ou `""` |
| 10 | `armed` | `yes, no, unknown` |
| 11 | `conduct` | conduite à tenir : liste de `"A-XXX libellé"` (6 au plus, 90 caractères, langue demandée) puis lignes libres |
| 12 | `reason` | motif court (`"M-… libellé"` puis texte), 260 caractères |
| 13 | `zone` | zone |
| 14 | `place` | dernier lieu connu |
| 15 | `issued` | `AAAA-MM-JJ` |
| 16 | `until` | `AAAA-MM-JJ` ou `""` |
| 17 | `soon` | `true` : échéance dans 72 h (« PÉRISSABLE ») |
| 18 | `service` | service à informer : `"Nom (SIGLE)"` |
| 19 | `svc` | code du service (`SVC-…`) ou `""` |
| 20 | `channel` | canal de compte rendu (indicatif, fréquence, ligne `555-01xx`) |
| 21 | `delay` | clé du délai (`now`…) |
| 22 | `url` | adresse de signalement absolue |
| 23 | `token` | jeton de signalement (dernier segment de `url`) |
| 24 | `state` | `active` (`pending` n'est jamais listé : § 3.4) |
| 25 | `acked` | `true` si ce compte en a accusé lecture |
| 26 | `updated` | `AAAA-MM-JJ HH:MM` (UTC) |
| 27 | `cls` | niveau de diffusion (`interne`…) |
| 28 | `mark` | marquage de bandeau (`SseUsClassification::banner`) |
| 29 | `cut` | `true` : un texte a été raccourci |

### 3.3 `detail`

Corps : `{…,"op":"detail","ref":"FV-2026-0007","lang":"fr"}`.

```
[1, "detail", [alerte…], [motifs…], [véhicules…], [personnes liées…], signalement, nombre de signalements, levée le, motif de levée, à ne pas faire]
```

Mêmes colonnes, textes longs (`reason` 800 caractères, `conduct` 10 lignes). `motifs` : liste de `"M-… libellé"`. Véhicules et personnes liées : libellés courts,
3 au plus, selon l'habilitation (règles de `sse_fiches`). `signalement` : ligne du signalement physique. Une alerte **levée ou expirée** reste lisible en détail par sa
référence pendant 30 jours (`state` : `lifted` ou `expired`) : le téléphone affiche « levée le … ».

### 3.4 `create`

```
{"kind":"sse_alertes","op":"create","doc":"vigilance"|"avis","subject":"<référence d'identité ou de personne>","unknown":"<description d'un inconnu>",
 "type":"<clé de type d'avis>","threat":"low|medium|high|critical","armed":"yes|no|unknown","reasons":"M-TRF-01,M-INS-02","measures":"A-OBS,A-NOI",
 "zone":"","grid":"","place":"","days":30,"text":"","service":"SVC-…","delay":"now","lang":"fr","cid":"…"}
```

- `subject` : référence d'une identité du registre (`sse_identities.ref`) ou d'une personne (`sse_persons.subject_id`) de la communauté ; **ou** `unknown`
  (description de 10 à 400 caractères) quand le sujet n'est pas au registre. Les deux vides : `422`.
- `reasons`, `measures` : codes du catalogue séparés par des virgules (6 au plus chacun, codes inconnus ignorés). `text` : 400 caractères, sans organisation réelle
  (liste de refus `SseWantedRealNames`, sinon `422`). `days` : 7, 14, 30, 60, 90 ou 180 (30 par défaut). `service` : code du catalogue (défaut de la communauté).
- Droits (ceux du site, d'après le compte du jeton) : fiche de vigilance = écrire dans « Fichiers et sujets » **et** exporter de « Papiers » ; avis = écrire dans
  « Fichiers et sujets ». **Sans ce droit**, ou avec un sujet `unknown`, ou si la communauté a activé le circuit de validation des avis, l'opération crée une
  **proposition** (file « À valider » du portail) au lieu d'une alerte active.

Réponse : `[1, "create", état, référence, adresse de signalement, jeton, message, replay]`, `état` = `active` ou `proposal`.
Pour une proposition : référence `PROP-<n>`, adresse et jeton vides ; la proposition n'apparaît dans `list` qu'une fois acceptée (sous sa référence définitive).

### 3.5 `report`

```
{"kind":"sse_alertes","op":"report","ref":"<référence>"|"token":"<jeton>","what":"seen|info","when":"AAAA-MM-JJ HH:MM"|"","place":"","grid":"",
 "pos":[x, y],"text":"","dir":"","vehicle":"","sure":"low|medium|high","cid":"…"}
```

`when` vide : maintenant. `text` : 3 à 600 caractères. `pos` : position du joueur sur la carte (facultative). Pas de photo par ce canal.
Réponse : `[1, "report", numéro (SIG-AAAA-NNNN), référence de l'alerte, "new", replay]`. Alerte levée ou expirée : `409 closed`.
Le signalement entre dans l'onglet « Signalements » de l'alerte, dans « À trier », et notifie les gestionnaires.

### 3.6 `renew`, `lift`, `ack`

- `{…,"op":"renew","ref":"…","days":30,"reason":"…","cid":"…"}` → `[1, "renew", nouvelle référence, valable jusqu'au, adresse de signalement, jeton, replay]`.
  Droit : celui d'émettre le document. La précédente est close (« renouvelée »), son adresse de signalement reste lisible et renvoie vers la nouvelle.
- `{…,"op":"lift","ref":"…","reason":"…","cid":"…"}` (motif de 3 à 500 caractères, obligatoire) → `[1, "lift", référence, levée le, replay]`.
  Droit : écrire dans « Fichiers et sujets » (gestion des dossiers SSE), comme la levée d'un avis sur le site.
- `{…,"op":"ack","ref":"…"}` → `[1, "ack", référence, accusé le]`. Idempotent par nature (un accusé par compte et par alerte).

## 4. Adresse de signalement et QR code

Chaque alerte active a une adresse stable `https://<site>/atak/sse/signalement/<jeton>` : jeton opaque de 22 caractères `[A-Za-z0-9]`, tiré au hasard, sans lien avec
l'identifiant. Elle est imprimée sous le bloc « Service à informer » des PDF et encodée dans leur QR code. La page demande un compte de la communauté (sinon
connexion puis retour) et ne révèle rien sans droit. Alerte levée : la page reste lisible (« levée le … ») sans formulaire. Jeton révocable par un gestionnaire.

## 5. Routes REST (JSON, mêmes gardes)

| Route | Opération |
|---|---|
| `GET /api/game/v1/sse/alertes/catalog?since_rev=` | `catalog` (objet JSON par section, `ETag`) |
| `GET /api/game/v1/sse/alertes?since_rev=&doc=&format=sqf&page=` | `list` |
| `GET /api/game/v1/sse/alertes/{ref}` (`/` écrit `%2F`) | `detail` |
| `POST /api/game/v1/sse/alertes` | `create` |
| `POST /api/game/v1/sse/alertes/{ref}/report` · `/renew` · `/lift` · `/ack` | `report`, `renew`, `lift`, `ack` |

## 6. Côté téléphone (à faire dans les mods, hors de ce dépôt de site)

- App Fiches / nouvelle app Alertes : bloc d'alerte en tête de carte = bande pleine de la couleur du niveau (`level` du catalogue), forme (`triangle`, `diamond`,
  `octagon`) dessinée en contrôle, titre du niveau en capitales, rang `n/4` en barres, consigne dominante (`order`), puis `conduct` numérotée.
- Notice de couleur : cadre de la couleur `color` autour de la carte, titre « NOTICE ROUGE » du type (`type` du catalogue).
- Bloc « Service à informer » : `service`, `channel`, délai (`delay` du catalogue), bouton « Signaler » (op `report`, `pos` et `grid` du joueur préremplis).
- Émission : formulaire à listes (types, niveaux, motifs, mesures, services du catalogue), envoi `create` avec `cid` ; réponse `proposal` : « transmis au bureau ».
- File hors ligne : `create` et `report` rejoués avec le même `cid`. Aucun toast, hint ni systemChat : retours dans la ligne d'état de l'écran.

## 7. Additif à `sse_fiches`

Les colonnes de `sse_fiches` ne changent pas. Une fiche de vigilance y garde ses 25 colonnes ; le niveau, la consigne et l'adresse de signalement se lisent par `sse_alertes`
(même référence).
