# Barre de liaison — double fond et deux lignes

## Contexte

Bandeau bas de la carte ATAK (Athena 1.0.108).

## Symptôme

- Double zone opaque (bandeau large + pavé plus sombre).
- Infos sur deux lignes ; versions OW/ATAK/liaison plus grandes que l’identité.

## Cause

HTML avec `<br/>` et tailles `0.55` / `0.70` dans un contrôle déjà opaque ; hauteur calibrée pour deux lignes.

## Correctif

Une seule ligne, taille unique `0.48`, fond unique, hauteur réduite.

## Fichiers

- `fn_athena_updateLinkStrip.sqf`
- `atak_athena/config.cpp` (1.0.109)

## Statut

corrigé
