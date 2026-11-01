# Surcharge des API pendant une partie à 40 joueurs

## Contexte
Une partie à 40 joueurs avec terminal : chaque joueur sonde Athena environ 4 fois par seconde (marqueurs 8 s, unités 3 s
ATAK ouvert, repères 1,6 s carte ouverte, ordres, alertes…), chaque poste web (carte ATAK web, Overwatch) une
quinzaine de lectures toutes les 3 à 5 s.

## Symptôme
- Poste web : les lectures d'un même tour partaient l'une après l'autre (plusieurs secondes) et occupaient chacune un
  processus PHP-FPM en attendant.
- Mêmes listes (unités, marqueurs, repères) relues en base par chaque joueur ; chaque position reçue relisait toute la
  liste des marqueurs.
- Onglet carte ou Overwatch laissé ouvert en arrière-plan : sondages à plein régime.
- Coupures roleplay simulées (« liaison perdue ») jamais déclenchées pour les joueurs.

## Cause
- Verrou du fichier de session : seules les lectures du jeu (clé / jeton) libéraient la session, pas celles du site.
- Aucune mémoire partagée entre requêtes pour les listes communes à une communauté.
- Carte web : alertes médicales et ordres demandés deux fois (tour général + boucle propre) ; aucune pause onglet caché
  ni garde contre deux requêtes identiques en route.
- Roleplay : l'échéance de la coupure était écrite en session, or les sondages du jeu lisent la session sans
  l'enregistrer.

## Correctif
- `Session::isReadOnlyRequest` : sondages GET du site (préfixes `/api/atak/`, `/api/units`, `/api/chat`…) en session
  lecture seule (appairage et relais gardés en écriture).
- `ShortCache` (APCu, sinon fichiers) : unités enrichies, marqueurs et repères gardés 2 s par communauté ; écriture d'un
  marqueur, d'un repère ou d'une unité depuis le poste → relu tout de suite.
- Plafond large des GET `/api/*` (900/min par joueur, 1500/min par compte web) → 429 + Retry-After.
- Refus 429 échantillonnés dans `request_telemetry` (plus une ligne SQL par refus pendant une rafale).
- Carte web / Overwatch : pause onglet caché, requêtes identiques partagées, pause sur 429/503.
- Roleplay : calendrier de coupures calculé sans état.
- Index : `scripts/sql/api-hot-indexes.sql`.

## Fichiers touchés
`app/Support/ShortCache.php`, `app/Core/Session.php`, `app/Repositories/AtakDataRepository.php`,
`app/Controllers/Api/AtakApiController.php`, `app/Middleware/RateLimitMiddleware.php`,
`app/Middleware/RequestTelemetryMiddleware.php`, `app/Services/Tactical/RoleplaySimulationService.php`,
`public/assets/js/atak-socket.js`, `atak-overwatch-beta.js`, `atak-medical-alerts.js`, `atak-orders.js`,
`atak-activity.js`, `views/atak.php`, `scripts/sql/api-hot-indexes.sql`.

## Vérification
PHPUnit : `ShortCacheTest`, `ApiPollRateLimitTest`, `SessionGameReadOnlyTest`, `RequestTelemetryMiddlewareTest`.
À tester en jeu : carte, BFT, marqueurs (pose / retrait), repères, tchat à plusieurs joueurs ; carte web et Overwatch
avec un onglet en arrière-plan puis revenu au premier plan.

## Statut
Corrigé (à valider en partie réelle).
