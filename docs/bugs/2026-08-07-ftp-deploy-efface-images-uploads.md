# Images uploadées effacées à chaque sync Git→FTP

## Contexte

Médias déposés via le site (avatars, logos communauté, photos recon/SSE, forum, cartes…)
vivaient sous `public/uploads` dans l’arbre déployé par FTP Hostinger.

## Symptôme

Après un `git push` / déploiement FTP, les images déjà uploadées sur athena.ttrd.fr
disparaissent ou renvoient 404.

## Cause

Le sync FTP opère dans `public_html`. Même avec des exclusions, stocker les fichiers
runtime **dans** l’arbre synchronisé reste fragile (recréation de dossiers, état de sync,
déploiements manuels).

## Correctif

1. Variable `PUBLIC_UPLOADS_PATH` : stockage absolu **hors** `public_html` sur le compte ttrd.fr / Hostinger.
2. Helper `public_uploads_path()` / `public_file_path()` + classe `App\Support\PublicUploads`.
3. Front controller + `.htaccess` : servir `/uploads/…` depuis ce chemin persistant.
4. Workflow FTP : exclusions renforcées `public/uploads`, `storage/uploads`, documents, intel.

## Action prod

```bash
mkdir -p /home/u416380327/domains/athena.ttrd.fr/persistent-uploads
# migrer si besoin :
# cp -a …/public_html/public/uploads/. …/persistent-uploads/
```

Dans `.env` (hors Git) :
```bash
PUBLIC_UPLOADS_PATH=/home/u416380327/domains/athena.ttrd.fr/persistent-uploads
```

## Fichiers touchés

- `app/Support/PublicUploads.php`
- `app/Support/helpers.php`
- `bootstrap/app.php`
- `public/index.php`, `public/.htaccess`
- contrôleurs / services d’upload (chemins FS)
- `.github/workflows/deploy-hostinger-ftp.yml`
- `.env.example`, `DEPLOY.md`

## Vérification

- [ ] `PUBLIC_UPLOADS_PATH` renseigné en prod
- [ ] Upload avatar / logo → fichier apparaît dans `persistent-uploads`
- [ ] Push Git → image toujours accessible en `/uploads/…`

## Statut

corrigé (nécessite config `.env` prod)
