# ICD — App Session du téléphone ATAK (contrat v1)

Contrat entre le site Athena et l'app « Session » du téléphone ATAK natif (mod `COMSPEC_ATAK_Native`). Le site livre les
routes, les notifications et le code ; l'app est écrite côté mod dans un second temps. Tout est **additif** : les routes
`/api/atak/game-session/*` du temps de jeu, le pointage existant et la fiche de session gardent leur comportement.

Fichiers : `app/Controllers/Api/RoleplaySessionGameApiController.php`, `app/Services/Personnel/RoleplaySessionGameApp.php`,
`RoleplaySessionCodeService.php`, `RoleplaySessionNotifier.php`, `RoleplaySessionAttendance.php`, `RoleplaySessionLiveRoster.php`,
`app/Support/SessionJoinCode.php`. Tests : `tests/Unit/RoleplaySessionAppTest.php`, `tests/Contract/RoleplaySessionAppMysqlTest.php`.

## 1. Authentification

Comme toutes les routes `/api/atak/*` : protégées en amont par `ComspecApiKeyAuth` (clé ATAK / session de jeu). Ces quatre
routes exigent **la session de jeu du compte** (jeton court émis et rafraîchi par COMSPEC Link) : le code rattache un membre
précis. La communauté est celle du jeton ; le compte est celui du jeton (ou, à défaut, le compte relié au Steam du jeton).

| Cas | Réponse |
|---|---|
| Clé de communauté seule, sans compte | `401 account_link_required` |
| Pas de communauté | `403 tenant_context_required` |
| Compte inconnu dans la communauté, suspendu, ou d'une autre communauté | `403 not_allowed` |
| Tables absentes (serveur sans l'étape 4910) | `503 feature_unavailable` |

En-têtes de réponse : `Content-Type: application/json`, `Cache-Control: private, no-store`, `Retry-After` sur un 429.

## 2. Format commun (versionné, additif)

Toute réponse porte `"v": 1` et `"ok"`. Les champs peuvent **s'ajouter** dans une même version ; l'app ignore ce qu'elle ne
connaît pas. Un champ ne change jamais de sens sans passer à `"v": 2`. Dates en ISO 8601 avec fuseau (`2026-11-16T21:00:00+01:00`).

Erreur :

```json
{ "v": 1, "ok": false, "error": "code_unknown" }
```

Succès (corps commun de `current`, `join`, `checkin`, `leave`) :

```json
{
  "v": 1,
  "ok": true,
  "server_time": "2026-11-16T21:12:04+01:00",
  "app_state": "active",
  "session": {
    "id": 12,
    "name": "Opération Dague",
    "state": "live",
    "kind": "officielle",
    "category": "Opération",
    "planned_start": "2026-11-16T21:00:00+01:00",
    "planned_end": "2026-11-16T23:30:00+01:00",
    "started_at": "2026-11-16T21:02:00+01:00",
    "ended_at": null,
    "host": "ANIMA",
    "briefing": "Fréquence 45.0, tenue de nuit",
    "attendance_required": true,
    "min_minutes": 45,
    "gauge": { "joined": 14, "checked_in": 11, "playing": 9 }
  },
  "me": {
    "joined": true,
    "checked_in_at": "2026-11-16T21:03:10+01:00",
    "left_at": null,
    "playing": true,
    "raw_seconds": 540,
    "validated_seconds": 0,
    "counted": false,
    "attendance_status": "confirmed"
  }
}
```

- `session.state` : `upcoming` (prévue), `live` (enregistrement en cours), `ended`, `cancelled`.
- `session.host` : indicatif (sinon nom) de l'animateur qui a créé la session, ou `null`.
- `session.gauge` : comptes seulement (aucune liste de membres) ; `playing` = joueurs dont le temps court en ce moment.
- `me` : `null` tant que le joueur n'a pas rejoint la session.
- Le **code n'est jamais renvoyé** au joueur.

## 3. États de l'app (`app_state`)

| `app_state` | Quand | Écran attendu |
|---|---|---|
| `no_session` | aucune session rejointe ni proposée (`session: null`) | « Pas de session en cours » |
| `code_required` | une session de la communauté est en cours ou prévue dans les 24 h, le joueur ne l'a pas rejointe | « Saisissez le code annoncé » |
| `checkin` | rejointe, enregistrement en cours, pas encore pointé | bouton « Pointer ma présence » |
| `active` | rejointe (prévue, ou en cours et pointée) | session active : horaires, consignes, jauge, mes temps |
| `ended` | la session rejointe est terminée ou annulée depuis moins de 2 h | « Session terminée », temps retenus |

Hors ligne ou serveur sans la fonctionnalité : l'app garde le dernier état reçu (avec son `server_time`) et affiche
« hors ligne » ; sur `503 feature_unavailable` ou 404 de la route (ancien site), elle masque l'app Session. Jamais de popup
à l'écran du jeu : l'app vit dans le téléphone, les notifications passent par `fn_notify` / la boîte du téléphone.

## 4. Routes

### GET /api/atak/session/current

Session du joueur : d'abord celle qu'il a rejointe (ouverte, ou terminée depuis moins de 2 h), sinon celle de sa communauté
(en cours depuis moins de 12 h, ou prévue dans les 24 h) qui a un code actif. Réponse : corps commun. Appel conseillé :
toutes les 30 s quand l'app est ouverte, toutes les 2 min sinon. Effet de bord borné : déclenche au plus une fois par minute
et par communauté les rappels T-15 / T-5 dus (en plus de la tâche planifiée).

### POST /api/atak/session/join

Corps : `{"code": "K7M-4QX"}` (casse, espaces et tiret ignorés). Rattache le joueur (une seule ligne de participant,
`attendance_source = code`) ; session déjà en cours : **pointe aussi** (le code annoncé prouve la présence) et ouvre son
temps. Appel répété : sans effet. Réponse : corps commun + `"joined": {"already": false, "checked_in_now": true}`.

| Erreur | HTTP | Sens |
|---|---|---|
| `invalid_format` | 422 | la saisie ne peut pas être un code (6 caractères de l'alphabet) |
| `code_unknown` | 404 | code inconnu dans la communauté, révoqué ou remplacé (même réponse : pas d'énumération) |
| `code_expired` | 410 | le code a dépassé son heure (voir § 5) |
| `session_closed` | 409 | session terminée ou annulée (code encore dans sa marge) |
| `not_allowed` | 403 | compte non actif dans la communauté |
| `rate_limited` | 429 | trop d'essais ; `retry_after` (s) dans le corps et l'en-tête |

Limites : **10 essais par joueur et 30 par adresse en 10 minutes** (réussis ou non). Chaque refus est journalisé (motif, sans
le code saisi) ; chaque rattachement aussi (une fois).

### POST /api/atak/session/checkin

Corps : `{"session_id": 12}`. Pointe la présence (`checked_in_at`, plage `check_in` des segments de présence, motif
existant), ouvre le temps du joueur. Idempotent : `"checkin": {"already": true}`. Erreurs : `not_joined` (404 : session
inconnue, d'une autre communauté ou non rejointe — même réponse), `session_not_started` (409), `session_closed` (409),
`rate_limited` (429 : 30 actions par joueur en 10 min).

### POST /api/atak/session/leave

Corps : `{"session_id": 12}`. Ferme le temps ouvert du joueur (motif `leave`) et note l'heure de départ. Idempotent :
`"leave": {"already": true}`. Erreurs : comme `checkin` (sauf `session_not_started`).

## 5. Code de session

- 6 caractères tirés de `ABCDEFGHJKLMNPQRSTUVWXYZ23456789` (pas de 0/O ni 1/I), affiché `K7M-4QX` ; 32^6 ≈ 1,07 milliard.
- Unique dans la communauté sur tout l'historique (un code n'est jamais réutilisé) ; collision de tirage retirée.
- Émis à la création de la session, ou à la première ouverture de sa fiche si elle est prévue ou en cours.
- Actif jusqu'à : fin de session (clôture ou annulation) + 30 min ; au plus 24 h après le démarrage ; pour une session
  prévue, jusqu'à la fin prévue + 30 min (sinon début prévu + 12 h), au plus 30 jours après son émission.
- Régénérable (l'ancien passe « remplacé ») et révocable par l'animateur, avec confirmation, journal de la session et
  journal d'audit ; une révocation n'est jamais annulée toute seule. Expiration automatique par la tâche planifiée
  `roleplay_session_app` (toutes les 5 min) et, avant, refus dès l'heure passée.
- Gardé en clair (l'animateur doit pouvoir le relire toute la soirée) : il ne donne que le rattachement à une session de sa
  propre communauté ; la sécurité tient à l'entropie, à la limitation de débit et à l'absence d'énumération.

## 6. Notifications en jeu

Aucun nouveau canal : une ligne de la file existante des commandes web vers les téléphones (`atak_web_commands`,
kind `phone`, cmd `notify`), lue par le sondage des charges (`GET /api/atak/explosive-timers/commands`, ligne `@wc`) et
rangée par `fn_tocPhone` dans la boîte du téléphone et le journal de l'app Alertes (jamais d'alerte à l'écran). Fil :

```
WC1~phone~*~notify~session:12~lv=i~m=<texte codé>~ak=0~by=Session~src=ses~sid=12~ev=started
```

`*` = tous les téléphones de la communauté (une seule ligne par événement, durée de vie courte) ; la notification de test vise
le seul compte Steam de l'animateur. `m` est codé comme pour le poste TOC (`@code_` hors lettres, chiffres, espace et
`. , : -`), 160 caractères affichés au plus. Arguments ajoutés pour l'app Session (ignorés par les mods actuels) :
`src=ses`, `sid` (id de session), `ev` (événement). L'app peut s'en servir pour rafraîchir `current` à l'arrivée.

| `ev` | Niveau `lv` | Durée de vie | Quand |
|---|---|---|---|
| `opened` | i | 1 h | session prévue créée |
| `reminder_15` | i | 15 min | 15 min avant le début prévu (si activé) |
| `reminder_5` | a | 10 min | 5 min avant (si activé ; une tâche en retard n'envoie que le plus proche) |
| `started` | a | 30 min | démarrage (« Démarrer l'enregistrement ») |
| `resumed` | i | 20 min | session rouverte |
| `changed` | i | 30 min | horaire d'une session prévue modifié (une fois par nouvel horaire) |
| `checkin_call` | a | 15 min | « Appeler à pointer » de l'animateur (au plus un toutes les 2 min) |
| `ended` | i | 20 min | « Terminer l'enregistrement » / clôture |
| `cancelled` | i | 30 min | annulation |
| `test` | i | 10 min | « Envoyer une notification de test » (téléphone de l'animateur seul ; 5 en 10 min) |

Pause : il n'existe pas d'état « pause » des sessions ; `paused` est réservé et n'est pas émis en v1. Réglages par session
(fiche › Notifications en jeu) : envoi activé, rappels 15 / 5 min, consignes (400 caractères, renvoyées dans `briefing`).
Chaque envoi est journalisé ; les rappels et changements d'état portent une clé d'unicité (jamais deux fois).

## 7. Joueurs connectés et pointage par l'animateur (site)

La fiche de session (`/back-office/roleplay/sessions/{id}`, droits de la gestion des sessions) montre en direct les joueurs
ATAK de la communauté (`GET …/{id}/connectes`, JSON `no-store`, relu toutes les 15 s) : fusion de `atak_units` (liaison du
téléphone, connecté si mise à jour depuis moins de 3 min) et `tenant_game_server_presence` (serveur et mission, connecté si
signalé depuis moins de 12 min), compte retrouvé par `users.steam_id`. Pointage (`POST …/{id}/pointage` : sélection,
`mode=all` tous les connectés, `row=point:ID` / `row=remove:ID`) et enregistrement (`POST …/{id}/enregistrement`,
`action=start|stop`) suivent les règles de `RoleplayGameSessionService` ; le démarrage et la fin envoient `started` / `ended`.
Un joueur pointé pendant l'enregistrement (par l'animateur, le code ou `checkin`) voit son temps courir aussitôt.

## 8. Exemples

```
POST /api/atak/session/join        {"code":"k7m 4qx"}   → 200 {"v":1,"ok":true,"app_state":"active",…,"joined":{"already":false,"checked_in_now":true}}
POST /api/atak/session/join        {"code":"K7M-4Q0"}   → 422 {"v":1,"ok":false,"error":"invalid_format"}
POST /api/atak/session/join        {"code":"ZZZ-ZZZ"}   → 404 {"v":1,"ok":false,"error":"code_unknown"}
POST /api/atak/session/join  (11e essai en 10 min)      → 429 {"v":1,"ok":false,"error":"rate_limited","retry_after":412}
GET  /api/atak/session/current                          → 200 {"v":1,"ok":true,"app_state":"no_session","session":null,"me":null,…}
POST /api/atak/session/checkin     {"session_id":12}    → 200 {…,"checkin":{"already":false}}
POST /api/atak/session/leave       {"session_id":99}    → 404 {"v":1,"ok":false,"error":"not_joined"}
```
