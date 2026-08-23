# Overlay « LIAISON ATAK PERDUE » sans image

## Contexte

Perte de liaison simulée sur ATAK Enhanced (téléphone cTab). Le compte à rebours
texte s’affichait, pas le visuel CRT « LIAISON PERDUE ».

## Symptôme

Bandeau semi-transparent « LIAISON ATAK PERDUE / Reconnexion estimée dans 10 s »
par-dessus la carte (marqueur SSE encore visible). Aucune image.

## Cause

Les overlays étaient des PNG **1536×1024**. Arma n’affiche une texture
`RscPicture` que si les dimensions sont une **puissance de 2**. Le contrôle
image restait vide ; seul le texte (fond 45 % transparent) se voyait.

## Correctif

- Conversion `.paa` 2048×1024 (ImageToPAA).
- Overlay calé sur la carte + menu ATAK, fond texte allégé pour laisser
  l’image visible.

## Fichiers touchés

- `connect/img/overlays/comspec_overlay_no_signal_ca.paa`
- `connect/functions/fn_updateDeviceOverlay.sqf`
- `connect/functions/fn_updateAtakEnhancedRoleplay.sqf`
- `connect/display_device_macros.hpp`

## Vérification

Relancer Arma. Déclencher une perte de liaison : visuel CRT (tours, bande
orange, « LIAISON PERDUE ») + compte à rebours par-dessus.

## Statut

Corrigé (connect 1.4.42) — à relancer en jeu.
