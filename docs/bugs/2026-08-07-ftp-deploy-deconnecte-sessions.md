# Déconnexions de session au déploiement FTP Hostinger

## Contexte

Après un `git push` sur `main`, l’Action `deploy-hostinger-ftp.yml` synchronise le code via FTP.
Les utilisateurs connectés (portail + bureau SSE) se retrouvaient déconnectés.

## Symptôme

- Connexion active avant le déploiement.
- Après le sync FTP : retour au login / sas SSE, session perdue.
- Ressenti : « push git → FTP = ça déconnecte ».

## Cause

1. Les fichiers de session PHP vivent par défaut sous `storage/sessions` **dans** l’arbre déployé.
2. L’exclusion FTP ne ciblait que `**/storage/sessions/**` (contenu). Selon le sync SamKirkland, exclure aussi le **dossier** (`storage/sessions` + variantes) évite de recréer / toucher le répertoire runtime.
3. Même avec une bonne exclusion, un chemin de session **hors** `public_html` est plus robuste sur Hostinger mutualisé.

## Correctif

- Workflow FTP : exclure dossier **et** contenu pour `sessions`, `cache`, `logs`, `uploads`.
- `SESSION_SAVE_PATH` (config auth + `Session::start`) pour stocker les sessions hors arbre FTP.
- Doc : `DEPLOY.md`, `.env.example`.

## Fichiers touchés

- `.github/workflows/deploy-hostinger-ftp.yml`
- `app/Core/Session.php`
- `app/Config/auth.php`
- `.env.example`
- `DEPLOY.md`

## Vérification

- [ ] Exclusion présente pour `storage/sessions` et `storage/sessions/**`
- [ ] `php -l app/Core/Session.php` OK
- [ ] En prod : optionnel `SESSION_SAVE_PATH=/home/uXXXX/tmp/athena_sessions` puis reconnexion ; un push ne doit plus couper la session

## Statut

corrigé
