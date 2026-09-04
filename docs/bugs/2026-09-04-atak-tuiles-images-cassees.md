# Carte : cases d’image cassée à la place du terrain

- Date : 2026-09-04
- Statut : corrigé (pack 1.8.21) — récidive après 1.8.20

## Contexte

Suite au résolveur d’extraits 1.8.17–1.8.20. L’opérateur ouvre Terrain, parfois sans poste (indisponible). Zoom 3.

## Symptôme

Grille de cases avec l’icône Windows / Chromium « fichier introuvable ». Fond vert sombre, pas de photo aérienne.

## Cause

Le premier affichage posait bien la source distante autorisée. Le cache pouvait ensuite remplacer l’image par une adresse interne du type extraits locaux, ou un chemin de fichier. Dans le téléphone chargé depuis l’appareil, cela devient une recherche de fichier local : icône cassée. Si les deux sources distantes échouaient, l’image restait sur l’adresse ratée : même icône.

## Correctif

Chaque case charge d’abord la photo aérienne distante déjà autorisée. Le cache ne remplace l’image que s’il s’agit d’une copie déjà en mémoire. Si tout échoue, la case devient transparente : le fond vert reste, sans icône de fichier manquant. L’adresse interne des extraits sert au suivi, jamais à l’affichage.

## Fichiers touchés

- `web/map-tiles.js`
- `config.cpp` (adresses distantes déjà autorisées)

## Vérification

`paintTile` pose d’abord la source distante. Le cache n’écrit un `src` que s’il est une copie mémoire. Échec total → image transparente.

## Statut

Corrigé dans les sources 1.8.21 — reconstruire le pack et relancer Arma complètement.
