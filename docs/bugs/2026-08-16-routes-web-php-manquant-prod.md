# routes/web.php manquant en production

## Contexte

`POST /api/atak/client-init` (et toute requête passant par `public/index.php`) en production Hostinger (`athena.ttrd.fr`).

## Symptôme

`RuntimeException` : « Fichier de routage manquant sur le serveur (routes/web.php). Redéployer… »

Pile : `public/index.php` (~ligne 261). Tempête d’e-mails d’alerte possible si les clients ATAK / navigateurs pollent.

## Cause

Le fichier **est présent et versionné** dans le dépôt, mais **absent sur le disque Hostinger** sous `public_html/routes/web.php`.

Causes typiques :

- déploiement FTP **interrompu** (`cancel-in-progress: true` tuait un sync mid-upload) ;
- sync incomplet / suppression manuelle du dossier `routes/` ;
- pas une exclusion volontaire du workflow (le fichier n’est pas dans `exclude`).

## Correctif

1. **Immédiat prod** : remonter `routes/web.php` (dossier `routes/`) à la racine applicative, ou relancer **Deploy Athena (Hostinger FTP)** jusqu’à succès complet.
2. **Code** : `is_file` avant `require` ; clients `/api/*` → JSON **503** `service_unavailable` + `Retry-After` ; humains → page HTML avec consigne de redéploiement ; alertes e-mail dédupliquées globalement (pas par IP).
3. **CI** : pré-check fichiers critiques ; `cancel-in-progress: false` ; commentaire « ne jamais exclure `routes/` ».

## Fichiers touchés

- `public/index.php`
- `.github/workflows/deploy-hostinger-ftp.yml`
- `bootstrap/error_hint.php`
- `app/Services/Monitoring/ErrorReportMailer.php`
- `DEPLOY.md`
- `docs/bugs/2026-08-16-routes-web-php-manquant-prod.md`

## Vérification

Sur le serveur (FTP / File Manager) :

- [ ] Existe : `/home/…/public_html/routes/web.php`
- [ ] `POST /api/atak/client-init` ne renvoie plus l’erreur de routage manquant
- [ ] Si le fichier manque encore : réponse JSON 503 (pas un crash opaque)

## Statut

identifié — correctif code/CI appliqué ; **remonter le fichier en prod** pour rétablir le service
