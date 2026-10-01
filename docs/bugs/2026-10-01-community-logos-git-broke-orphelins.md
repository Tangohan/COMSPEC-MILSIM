# Logos communauté — arbre git-broke vs site live (fichiers orphelins)

## Contexte

Même incident que les photos terrain (`2026-09-28-photos-recon-git-broke-orphelins.md`) :

| Dossier | Rôle |
| --- | --- |
| `athena.ttrd.fr` | Site live (Nginx) |
| `athena.ttrd.fr.git-broke` | Ancien arbre mis de côté (~25/09/2026) |

Les logos / covers / favicons communauté sont écrits sous `public/assets/img/communities/{slug}-logo.png` (et variantes) par le back-office. **Ils ne sont pas dans Git.**

## Symptôme

- URL type `https://athena.ttrd.fr/public/assets/img/communities/athena-sys-logo.png?v=…` → 404.
- UI SSE / vitrine : « Image introuvable (fichier absent ou adresse incorrecte). »
- `tenants.logo_url` / `tenant_branding.logo_url` pointent encore vers le fichier manquant.
- Dossier live `public/assets/img/communities/` ne contient que `.gitkeep`.

## Cause

1. Recréation du dépôt live : les médias sont restés dans `athena.ttrd.fr.git-broke`.
2. Le code et la BDD référencent toujours le chemin ; le fichier n’est plus sur le document root.

## Correctif (VPS, une fois)

```bash
LIVE=/var/www/athena.ttrd.fr
OLD=/var/www/athena.ttrd.fr.git-broke

rsync -av --ignore-existing \
  "$OLD/public/assets/img/communities/" \
  "$LIVE/public/assets/img/communities/"

chown -R www-data:www-data "$LIVE/public/assets/img/communities"
find "$LIVE/public/assets/img/communities" -type d -exec chmod 775 {} \;
find "$LIVE/public/assets/img/communities" -type f -exec chmod 644 {} \;

curl -sI "https://athena.ttrd.fr/public/assets/img/communities/athena-sys-logo.png" | head -n 5
```

Optionnel : rafraîchir le `?v=` en BDD avec `filemtime` du fichier restauré pour invalider le cache navigateur.

## Prévention

- `.gitignore` : `/public/assets/img/communities/*` (sauf `.gitkeep`).
- Deploy VPS : `mkdir` + `chown www-data` sur ce dossier (comme `public/uploads`).
- Ne pas supprimer `git-broke` tant que la récupération n’est pas validée.

## Statut

Corrigé en prod (restauration depuis git-broke, 2026-10-01). Prévention versionnée dans ce dépôt.
