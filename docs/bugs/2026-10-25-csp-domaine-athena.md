# Pages sur le nouveau domaine, scripts encore sur l’ancien

## Contexte

Ouverture du portail via `athena-comspec.eu` pendant la migration, alors que `APP_URL` pointe encore vers `athena.ttrd.fr`.

## Symptôme

Le navigateur bloque les feuilles de style et les scripts (`app-version-check.js`, `cookie_consent.js`, `home-impact.js`). Le message indique que l’adresse `https://athena.ttrd.fr/public/assets/…` n’est pas autorisée par la règle du site, qui n’accepte les scripts que depuis le domaine de la page.

## Cause

Les adresses des fichiers sont construites à partir de `APP_URL`. La page est servie par le nouveau domaine, les fichiers sont demandés à l’ancien. Ce sont deux sites distincts pour le navigateur.

## Correctif

`APP_URL` passe à `https://athena-comspec.eu` (sans retirer un `/public` déjà présent). Les pages de `athena.ttrd.fr` redirigent vers le nouveau domaine. L’API, les photos et les fichiers restent servis sur l’ancien, pour les mods déjà installés.

## Fichiers touchés

- `.env` du VPS (`APP_URL`)
- `/etc/nginx/sites-available/athena`
- `docs/migration-domaine-athena-comspec.md`

## Vérification

Sur `https://athena-comspec.eu`, les scripts se chargent depuis ce même domaine. `https://athena.ttrd.fr/` renvoie vers `https://athena-comspec.eu/`. `https://athena.ttrd.fr/public/api/atak/ping` répond encore ici, sans redirection.

## Statut

Identifié. À appliquer sur le VPS.
