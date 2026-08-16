# Polls ATAK — 2002 via TenantTypeModuleAccessMiddleware

## Contexte

Production `athena.ttrd.fr`, 2026-08-16 ~21:34. Tempête `ERROR_ALERT` sur :

- `GET /api/map-shapes`
- `GET /api/atak/orders`
- `GET /api/recon/images`
- `GET /api/iff/assets`
- `GET /api/pings`
- `GET /api/atak/medical-alerts`

Client IPv6 perso ; user 5 / tenant 7.

## Symptôme

`Database connection failed: SQLSTATE[HY000] [2002] Operation not permitted`

Pile (prod encore sans LazyDatabaseConnection déployé) :

`Application->run()` → `new TenantTypeModuleAccessMiddleware()` → `TenantRepository->__construct()` → `Database::getPdo()`

## Cause

1. **Infra Hostinger** : micro-coupure MySQL / worker FPM (même message qu’avec `127.0.0.1`).
2. **PDO au boot middleware** : le middleware global construisait `TenantRepository` **avant** le dispatch ; l’ancien constructeur appelait `getPdo()` immédiatement → chaque poll ATAK échoue en « exception non gérée » + mail.
3. Correctifs locaux (lazy repos, retry 3×, throttle mails) **pas encore sur Hostinger**.

## Correctif

- `Application` : wrapper lazy — pas de middleware profil si `tenant_id` absent ; sinon instanciation **au moment** de l’appel.
- `TenantTypeModuleAccessMiddleware` : repo lazy ; sur panne BDD → **503 JSON** pour `/api/*` (pas d’exception → pas de mail par poll).
- Conserver lazy PDO + retry + throttle (voir `2026-08-16-atak-perstat-pdo-2002.md`).

## Fichiers touchés

- `app/Core/Application.php`
- `app/Middleware/TenantTypeModuleAccessMiddleware.php`

## Vérification

1. Déployer PHP sur Hostinger (ce patch + lazy `TenantRepository` + `Database` retry + `ErrorReportMailer`).
2. Poll ATAK pendant une micro-coupure : 503 `database_unavailable` / Retry-After, **pas** une tempête de mails.
3. `.env` : `DB_HOST=127.0.0.1`.

## Statut

corrigé en code — **à déployer**
