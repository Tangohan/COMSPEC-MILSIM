# ATAK — 403 nginx Ubuntu sur `/public/atak`

## Symptôme

`https://athena.ttrd.fr/public/atak` (et `/atak`) → 301 vers `/atak/` puis page
**403 Forbidden** `nginx/1.24.0 (Ubuntu)`. PHP n’est jamais atteint.

## Cause

Un **dossier filesystem** `public/atak/` existe sur le VPS (hors Git). DocumentRoot
nginx = `…/public`, donc :

1. `/public/atak` est réécrit en `/atak`
2. nginx voit le dossier `atak/` → 301 `/atak/`
3. `try_files … $uri/` + autoindex off → **403**

La route applicative `/atak` est masquée par ce dossier.

## Correctif

- Déployer : supprimer `public/atak` s’il n’y a pas d’`index.php` dedans
  (`.github/workflows/deploy-vps.yml`).
- Nginx : `try_files $uri /index.php` (sans `$uri/`) + `location = /atak` forcé
  vers le front controller (`docs/nginx.example.conf`).
- `.htaccess` racine (Hostinger) : redirection 302 vers `/public/atak`.

Manuel immédiat sur le VPS :

```bash
rm -rf /var/www/athena.ttrd.fr/public/atak
nginx -t && systemctl reload nginx   # après alignement du vhost sur l’exemple
```

## Vérification

`curl -sI https://athena.ttrd.fr/public/atak` → 200/302 applicatif (Set-Cookie /
Location login), **pas** 403 nginx.
