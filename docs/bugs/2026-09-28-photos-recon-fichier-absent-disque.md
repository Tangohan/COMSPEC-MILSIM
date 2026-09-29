# Photos terrain — 404 : URL correcte, fichier absent du disque

## Contexte

Overwatch Beta / ATAK Photos. Après correction des URLs `/public/uploads` → `/uploads`, les vignettes pointent vers la bonne adresse (`https://athena.ttrd.fr/uploads/recon/recon_….jpg`) mais répondent encore 404.

## Symptôme

- `HEAD /uploads/recon/recon_20260928114955_YA1.jpg` → 404 (en-têtes du filet `public/index.php` : `nosniff` + `no-store`).
- `HEAD /uploads/` et `/uploads/login-accueil/` → 403 Nginx (dossiers présents).
- `HEAD /uploads/recon/` → 404 (dossier **absent** sur le VPS).
- La fiche reste listée côté API (URL construite depuis `image_path` en base).

## Cause

1. **URL** (corrigé en UPDATE #00727) : préfixe `/public` erroné sur VPS root=`…/public`.
2. **Fichier** : les médias étaient restés dans l’ancien arbre `athena.ttrd.fr.git-broke` après recreation du site live (voir `2026-09-28-photos-recon-git-broke-orphelins.md`). Le live en `root:root` empêchait aussi PHP de recréer `public/uploads/recon`.

## Correctif

- Rapatrier les uploads depuis `git-broke` (rsync) + `chown www-data` (ops VPS).
- `ReconImageStorage` : écriture `public/uploads/recon` puis repli `storage/uploads/recon`, vérification taille après copie.
- `public/index.php` : si `/uploads/…` absent de `public/`, servir depuis `storage/uploads/…`.
- `.gitkeep` recon/intel + `mkdir -p` + `chown`/`chmod` dans le déploiement VPS.
- Purge / transfert SSE utilisent le même resolver.

## Fichiers touchés

- `app/Support/ReconImageStorage.php`
- `app/Controllers/Api/AtakApiController.php`
- `app/Services/Tactical/AtakTenantDataService.php`
- `app/Support/PlatformStorageCatalog.php`
- `public/index.php`
- `public/uploads/recon/.gitkeep`, `public/uploads/intel/.gitkeep`, `storage/uploads/recon/.gitkeep`
- `.gitignore`, `.github/workflows/deploy-vps.yml`
- `tests/Unit/ReconImageStorageTest.php`
- `app/Support/DevDispatchCatalog.php` (UPDATE #00728)

## Vérification

1. Après déploiement : `ls public/uploads/recon` existe ; propriétaire `www-data`.
2. Nouvelle photo depuis le jeu → fichier sur disque + `https://athena.ttrd.fr/uploads/recon/…` → 200.
3. Les anciennes lignes sans fichier restent vides : **renvoyer** le cliché depuis le terrain.

## Statut

Corrigé (stockage + déploiement). Les photos déjà indexées sans fichier sur disque ne sont pas récupérables.
