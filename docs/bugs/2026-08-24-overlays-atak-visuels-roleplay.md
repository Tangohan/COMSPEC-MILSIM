# Overlays ATAK — visuels liaison / cassé / brouillage

**Date :** 2026-08-24  
**Statut :** corrigé

## Contexte

Les écrans « liaison perdue », brouillage et écran cassé du terminal étaient des fonds génériques + gros texte rouge, peu crédibles.

## Symptôme

Panneau moche, titre en double, pas le rendu tactique (bande hazard, HUD glitch, verre brisé).

## Cause

Textures placeholder et légendes structurées plein écran par-dessus l’image.

## Correctif

- Liaison perdue : visuel bande hazard / tours radio ; seule la reconnexion (secondes) est écrite par-dessus.
- Hors couverture, gel, appareil compromis : HUD glitch (coins viseur, parasites).
- Écran endommagé : verre brisé + fuites RGB, légende en bas.
- Carte web : mêmes images (déconnexion, interférences, fissures).

## Fichiers touchés

- `connect/img/overlays/*.paa` et `atak-fx/broken-screen.paa`
- `connect/functions/fn_updateDeviceOverlay.sqf`
- `connect/functions/fn_updateAtakEnhancedRoleplay.sqf`
- `public/assets/img/atak-fx/liaison-perdue.png`, `glitch-hud.png`, `broken-screen.png`
- `public/assets/css/atak-roleplay-ctab.css`, `atak.css`, `atak-roleplay-ctab.js`

## Vérification

Relancer Arma (connect 1.4.61). Ouvrir l’ATAK hors liaison : visuel hazard, compte à rebours en haut. Zeus « écran cassé » : fissures. Zone sans couverture : HUD glitch.

## Statut

corrigé
