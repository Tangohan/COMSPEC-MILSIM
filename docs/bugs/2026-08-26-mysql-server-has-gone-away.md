# Session MySQL coupée — « server has gone away » sur GET /hub

## Contexte

Production `athena.ttrd.fr`, corrélation `098eb1d0be788b5f`, utilisateur 5, communauté 7.
`GET /hub` (chemin sanitizé `/public/hub`) → `SQLSTATE[HY000] General error: 2006 MySQL server has gone away`.
`PDOException` à `TenantRepository.php` (`prepare`), appelé par `TenantTypeModuleAccessMiddleware` (`findById`).

## Symptôme

L’opérateur ouvre le hub et reçoit un incident technique. Le rapport d’alerte montre une exception brute (`SQLSTATE`, `PDOException`) au lieu d’un message actionnable. Ce n’est pas une requête lourde : une lecture de communauté suffit à faire échouer `prepare()`.

## Cause

PDO conserve une session déjà fermée par l’hébergeur (`wait_timeout`, worker PHP-FPM resté idle, parfois un paquet trop gros). `getPdo()` rend cette connexion morte ; le premier `prepare()` plante au lieu de rouvrir la session. Le middleware renvoyait l’exception au visiteur hors API.

## Correctif

- `ReconnectingPdo` : une seule reconnexion in-place sur 2006 / 2013 / « server has gone away » / « Lost connection », puis relance de la requête. Pas de boucle infinie. Pas de connexion persistante.
- `Database::withReconnect()` autour des helpers `insert` / `fetchAll` / `fetchOne` / `execute`.
- Hub (et pages HTML) : 503 avec le bandeau métier existant, sans `SQLSTATE` ni nom d’exception.
- Alertes e-mail : même dédup / cadence que les autres coupures transitoires de base.

## Fichiers touchés

- `app/Core/ReconnectingPdo.php`
- `app/Core/Database.php`
- `app/Middleware/TenantTypeModuleAccessMiddleware.php`
- `app/Core/ExceptionHandler.php`
- `bootstrap/error_hint.php`
- `public/index.php`
- `app/Services/Monitoring/ErrorReportMailer.php`
- `tests/Unit/DatabaseLostConnectionTest.php`
- `tests/Unit/TenantTypeModuleAccessMiddlewareTest.php`

## Vérification

- Tests unitaires : détection 2006 / 2013 / messages, un seul retry `withReconnect`, hub 503 sans jargon.
- Recette : laisser une session hub ouverte plus longtemps que le `wait_timeout` hébergeur, puis recharger `/hub` — la page s’affiche sans incident ; si la base est vraiment injoignable, message « réessayez dans quelques instants » + référence d’incident.

## Statut

corrigé en code — à déployer
