# Extranet JNET — couleurs sombres sur tous les onglets (BO)

## Contexte

JNET embarqué dans le chrome back-office clair (`/jnet` → `jnet-embed` + `jnet_bo_embed.css`).

## Symptôme

Accueil partiellement corrigé, mais messagerie, opérations, cibles, unité, apps, sync, etc. restaient sur fonds / textes « néon » du thème sombre (`#14191f`, `#6ee7b7`, `#f87171`…).

## Cause

`jnet_portal.css` hardcodait des surfaces sombres hors variables CSS ; `jnet_bo_embed.css` ne surchargeait que l’accueil.

## Correctif

1. Tokens `--jnet-panel-fill`, `--jnet-head`, `--jnet-inset`, `--jnet-hover`, `--jnet-search`, `--jnet-ok-bright`, `--jnet-danger-bright` dans le portail + remap clair dans l’embed.
2. Remplacement des hardcodes critiques du portail par ces variables.
3. Surcharges explicites mail / ops / cibles / badges / théâtre dans `jnet_bo_embed.css`.

## Fichiers touchés

- `public/assets/css/jnet_portal.css`
- `public/assets/css/jnet_bo_embed.css`
- `public/assets/js/jnet-theatre.js`
- `views/jnet/_layout.php`
- `docs/bugs/2026-08-16-jnet-onglets-couleurs-bo.md`

## Vérification

Parcourir `/jnet` (accueil, messagerie, opérations, cibles, unité, apps, théâtre) : panneaux blancs, texte slate, accents verts lisibles.

## Statut

corrigé
