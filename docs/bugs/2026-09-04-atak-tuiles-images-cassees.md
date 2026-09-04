# Carte : cases d’image cassée à la place du terrain

- Date : 2026-09-04
- Statut : corrigé (pack 1.8.20)

## Contexte

Suite au résolveur d’extraits 1.8.17–1.8.19. L’opérateur ouvre Terrain, liaison établie, zoom 3.

## Symptôme

Grille de cases vides avec l’icône d’image cassée. Fond vert sombre, pas de photo aérienne.

## Cause

Si le premier `src` n’est pas une adresse distante autorisée (extrait local, adresse du poste inconnue, ou attente du cache), Chromium du jeu refuse l’image. Leçon 1.8.18 : ne jamais attendre le cache avant d’afficher.

## Correctif

Chaque case charge d’abord la source distante déjà autorisée. Le cache et l’enregistrement sur disque ne remplacent l’image que s’ils sont prêts. L’adresse des extraits locaux est autorisée pour plus tard, jamais en premier affichage.

## Fichiers touchés

- `web/map-tiles.js`
- `config.cpp` (`allowedHTMLLoadURIs`)

## Vérification

`paintTile` pose `img.src` sur la source distante avant tout appel au cache.

## Statut

Corrigé dans les sources 1.8.20 — reconstruire le pack et relancer Arma complètement.
