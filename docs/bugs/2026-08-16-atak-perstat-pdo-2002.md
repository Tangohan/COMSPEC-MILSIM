# Alerte ERROR 2002 sur GET /api/atak/perstat

## Contexte

Production `athena.ttrd.fr`, 2026-08-16 ~21:15. Deux mails `ERROR_ALERT` identiques :

`GET /public/api/atak/perstat?mapId=1` → `Database connection failed: SQLSTATE[HY000] [2002] Operation not permitted`

Client IPv6 perso ; session user/tenant vides dans le mail (poll navigateur ATAK / cookie).

## Symptôme

Exception non gérée `RuntimeException` dans `Database.php` (ligne throw après échec de connexion). Site souvent OK juste après (micro-coupure Hostinger).

## Cause

1. **Infra Hostinger** : `2002 Operation not permitted` = MySQL brièvement injoignable (TCP / FTP mid-deploy / worker FPM), même avec `DB_HOST=127.0.0.1`.
2. **PDO eager** : `AtakApiController` construit de nombreux repositories qui appelaient `Database::getPdo()` **dans leur constructeur** → une seule micro-coupure fait échouer le boot de n’importe quel poll ATAK.
3. **Retry insuffisant** : 1 seule reprise à 80 ms.
4. **Alertes** : cooldown trop court pour des polls répétés → double mail quasi simultané.

## Correctif

- Trait `LazyDatabaseConnection` : PDO à la **première requête SQL**, pas au constructeur (dépôts ATAK critiques).
- `Database::getPdo()` : **3 tentatives** (80 / 200 / 450 ms) sur erreurs transitoires 2002 / 2006 ; `ATTR_TIMEOUT=3`.
- `ErrorReportMailer` : pour erreurs BDD transient, cooldown **15 min** / max **4 / h** (`ERROR_ALERT_DB_COOLDOWN_SECONDS`, `ERROR_ALERT_DB_MAX_PER_HOUR`).

## Fichiers touchés

- `app/Support/LazyDatabaseConnection.php`
- `app/Core/Database.php`
- `app/Services/Monitoring/ErrorReportMailer.php`
- Repositories ATAK (lazy) : `AtakDataRepository`, `TenantRepository`, `UserRepository`, ordres, config, slides, etc.

## Vérification

1. Déployer PHP sur Hostinger.
2. Confirmer `.env` : `DB_HOST=127.0.0.1`.
3. Poll `/public/api/atak/perstat` : en cas de micro-coupure, retry silencieux ; sinon 503 JSON `database_unavailable` + au plus 1 mail / 15 min.
4. Pas de FTP concurrent pendant le test.

## Statut

corrigé en code — **à déployer** sur Hostinger
