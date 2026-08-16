# DemoNdaVisitRepository dans la pile des erreurs ATAK (PDO eager)

## Contexte

Production `athena.ttrd.fr`, utilisateur id 5 / tenant 7. Polls ATAK :

- `GET /api/atak/air-assets`
- `GET /api/atak/weather`
- `GET /api/atak/stats`

## Symptôme

`Database connection failed: SQLSTATE[HY000] [2002] Operation not permitted` avec pile :

`Application->run()` → `Container::get/build` → `DemoNdaVisitRepository->__construct()` → `Database::getPdo()`

Alors que ces endpoints n’ont aucun besoin métier du portail d’engagement démo (NDA).

## Cause

1. **DI eager** : `Application::run()` appelait `Container::get(DemoNdaGateMiddleware)` pour *chaque* requête, ce qui construisait immédiatement `DemoNdaGateService` → `DemoNdaVisitRepository` → `Database::getPdo()` **avant** le `__invoke` du middleware (où `/api/atak/*` est pourtant exempté).
2. **Hostinger** : `DB_HOST=localhost` tente souvent un socket Unix → erreur 2002 « Operation not permitted » ; préférer `127.0.0.1`. Une panne brève ou un deploy FTP mid-flight amplifie le bruit.

## Correctif

- Middleware Demo NDA **lazy** : pas de `Container::get` si le gate est off via env, ou si le chemin bypasse déjà le gate (APIs machine / assets).
- `DemoNdaVisitRepository` : PDO **à la première utilisation**, pas au constructeur.
- Middleware : test d’exemption **avant** unlock / lecture BDD.
- Connexion MySQL : défaut / remap `localhost` → `127.0.0.1` + message d’aide ; réponses API **503** JSON (`database_unavailable`) ; alertes e-mail dédupliquées sans IP pour les erreurs infra.

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

- [ ] Avec gate démo désactivé : un poll `/api/atak/stats` ne passe plus par `DemoNdaVisitRepository` si la BDD est volontairement coupée au boot middleware (échec éventuel plus bas, dans le contrôleur ATAK).
- [ ] Prod : `DB_HOST=127.0.0.1` dans `.env` Hostinger.
- [ ] Après déploiement du correctif : plus de stack DemoNda sur les alertes air-assets/weather/stats.

## Statut

corrigé en code — **à déployer** ; prod encore exposée tant que le correctif + `DB_HOST` / `routes/web.php` ne sont pas en place
