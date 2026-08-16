# routes/web.php manquant en production

## Contexte

GET `/api/atak/laser-codes` (et toute requête passant par `public/index.php`) en production Hostinger (`athena.ttrd.fr`).

## Symptôme

Erreur fatale :

`require(.../public_html/routes/web.php): Failed to open stream: No such file or directory`

Pile : `public/index.php` ligne du `require` des routes. Le bootstrap a déjà tourné (compte connecté, communauté active) : seule la table de routage manque.

## Cause

Le fichier **est présent et versionné** dans le dépôt (`routes/web.php`), mais **absent sur le disque Hostinger** sous `public_html/routes/web.php`.

Cause typique : déploiement FTP incomplet, sync interrompu, ou suppression manuelle du dossier `routes/` côté hébergeur — pas un bug de code applicatif sur l’endpoint laser.

## Correctif

1. **Immédiat prod** : remonter `routes/web.php` (et le dossier `routes/`) à la racine applicative (`public_html/`, à côté de `app/` et `public/`), ou relancer l’Action **Deploy Athena (Hostinger FTP)** depuis `main`.
2. **Détection** : `public/index.php` vérifie `is_file` avant le `require` et lève un message d’exploitation explicite.
3. **CI** : étape « Vérifier fichiers critiques avant FTP » dans `.github/workflows/deploy-hostinger-ftp.yml`.

## Fichiers touchés

- `public/index.php`
- `.github/workflows/deploy-hostinger-ftp.yml`
- `docs/bugs/2026-08-16-routes-web-php-manquant-prod.md`

## Vérification

Sur le serveur (FTP / File Manager) :

- [ ] Existe : `/home/…/public_html/routes/web.php`
- [ ] GET `/public/api/atak/laser-codes?mapId=1` ne renvoie plus l’erreur de require

## Statut

identifié — correctif code/CI appliqué ; **remonter le fichier en prod** pour rétablir le service
