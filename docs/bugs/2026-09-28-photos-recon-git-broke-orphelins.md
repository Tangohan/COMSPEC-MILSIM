# Photos terrain — arbre git-broke vs site live (fichiers orphelins)

## Contexte

Sur le VPS `/var/www/` coexistent :

| Dossier | Propriétaire | Rôle |
| --- | --- | --- |
| `athena.ttrd.fr` | `root:root` | Site servi par Nginx (document root `…/public`) |
| `athena.ttrd.fr.git-broke` | `www-data` | Ancien arbre mis de côté (~25/09/2026) |
| `la-popotte` | `www-data` | Autre site |

Les photos terrain et autres uploads **ne sont pas dans Git**. Elles étaient dans l’ancien arbre.

## Symptôme

- URLs `/uploads/recon/recon_….jpg` correctes → 404.
- Dossier `public/uploads/recon` absent (ou vide) sous le site live.
- Les fichiers sont encore sous `athena.ttrd.fr.git-broke/public/uploads/…`.

## Cause

1. Lors d’un incident Git (~25/09), l’ancien dépôt a été renommé en `athena.ttrd.fr.git-broke`, puis un arbre neuf a été recrée en `athena.ttrd.fr`.
2. Le code suit Git ; **les uploads restent sur le disque de l’ancien dossier**.
3. Le site live est propriétaire `root:root` : PHP-FPM (`www-data`) ne peut pas créer `public/uploads/recon` si le parent n’est pas writable → nouvelles photos absentes ou indexées sans fichier.

## Correctif (à exécuter une fois sur le VPS)

```bash
LIVE=/var/www/athena.ttrd.fr
OLD=/var/www/athena.ttrd.fr.git-broke

# Rapatrier les médias (ne pas écraser un fichier plus récent sur le live)
rsync -av --ignore-existing "$OLD/public/uploads/" "$LIVE/public/uploads/"

# Droits d’écriture pour PHP
mkdir -p "$LIVE/public/uploads/recon" "$LIVE/public/uploads/intel" "$LIVE/storage/uploads/recon"
chown -R www-data:www-data "$LIVE/public/uploads" "$LIVE/storage"
find "$LIVE/public/uploads" -type d -exec chmod 775 {} \;

# Contrôle
ls -la "$LIVE/public/uploads/recon" | head
curl -sI "https://athena.ttrd.fr/uploads/recon/recon_20260928114955_YA1.jpg" | head -n 5
```

Ensuite : ne **pas** supprimer `git-broke` tant que le contrôle n’est pas OK. Quand c’est bon, archiver ou supprimer l’ancien arbre pour éviter toute confusion.

Déploiement : `mkdir` + `chown www-data` sur `public/uploads` et `storage` (déjà dans `deploy-vps.yml`).

## Fichiers touchés (code associé)

Voir aussi `2026-09-28-photos-recon-fichier-absent-disque.md` (repli storage + filet index).

## Vérification

- Photo listée → HTTP 200.
- Nouvelle capture jeu → fichier créé sous `$LIVE/public/uploads/recon` propriétaire `www-data`.

## Statut

Identifié — récupération manuelle VPS (rsync + chown).
