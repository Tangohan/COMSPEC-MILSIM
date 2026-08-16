# DemoNdaVisitRepository dans la pile des erreurs ATAK (PDO eager)

## Contexte

Production `athena.ttrd.fr`, utilisateur id 5 / tenant 7. Polls ATAK :

- `GET /api/atak/air-assets`
- `GET /api/atak/weather`
- `GET /api/atak/stats`
- `GET /api/atak/orders` (récidive 2026-08-16 ~20:40)

## Symptôme

`Database connection failed: SQLSTATE[HY000] [2002] Operation not permitted` avec pile :

`Application->run()` → `Container::get/build` → `DemoNdaVisitRepository->__construct()` → `Database::getPdo()`

Alors que ces endpoints n’ont aucun besoin métier du portail d’engagement démo (NDA).

Sur `/api/atak/orders`, l’échec peut aussi survenir **dans** le contrôleur (BDD réellement requise) : même code SQLSTATE, cause infra.

## Cause

1. **DI eager** (corrigé) : `Application::run()` appelait `Container::get(DemoNdaGateMiddleware)` pour *chaque* requête, ce qui construisait immédiatement `DemoNdaGateService` → `DemoNdaVisitRepository` → `Database::getPdo()` **avant** le `__invoke` du middleware (où `/api/atak/*` est pourtant exempté).
2. **Hostinger** : `DB_HOST=localhost` tente souvent un socket Unix → erreur 2002 « Operation not permitted » ; préférer `127.0.0.1`. Une panne brève MySQL ou un **deploy FTP mid-flight** provoque le même message même avec `127.0.0.1`.
3. Les polls ATAK (orders, units, …) multiplient les connexions : une micro-coupure → alerte `ERROR_ALERT_EMAIL`.

## Correctif

- Middleware Demo NDA **lazy** : pas de `Container::get` si le gate est off via env, ou si le chemin bypasse déjà le gate (APIs machine / assets).
- `DemoNdaVisitRepository` : PDO **à la première utilisation**, pas au constructeur.
- Middleware : test d’exemption **avant** unlock / lecture BDD.
- Connexion MySQL : défaut / remap `localhost` → `127.0.0.1` + message d’aide ; **1 retry** après 80 ms sur 2002 ; réponses API **503** JSON (`database_unavailable`) ; alertes e-mail dédupliquées sans IP pour les erreurs infra.

## Fichiers touchés

- `app/Core/Application.php`
- `app/Middleware/DemoNdaGateMiddleware.php`
- `app/Services/DemoNda/DemoNdaGateService.php`
- `app/Repositories/DemoNdaVisitRepository.php`
- `app/Core/Database.php`
- `app/Core/ExceptionHandler.php`
- `app/Services/Monitoring/ErrorReportMailer.php`
- `public/index.php`
- `bootstrap/error_hint.php`
- `.env.example`, `DEPLOY.md`, `app/Config/database.local.php.example`

## Vérification

- [x] Sonde 2026-08-16 20:42 : `GET /public/api/atak/orders` → **401** JSON (PHP + routage OK ; plus de 500 DB au moment du test).
- [ ] Prod : `DB_HOST=127.0.0.1` dans `.env` Hostinger (et `database.local.php` si utilisé).
- [ ] Après déploiement du retry PDO : moins d’alertes pour micro-coupures.

## Que faire si ça revient

1. Vérifier qu’aucun déploiement FTP n’était en cours.
2. hPanel Hostinger → MySQL online.
3. Confirmer `DB_HOST=127.0.0.1` (pas `localhost`).
4. Si isolé et site OK juste après : ignorer (infra transitoire) ; si en rafale : regarder `storage/logs/error-alerts.log`.

## Statut

corrigé en code (+ retry 2002) — **à déployer** sur Hostinger
