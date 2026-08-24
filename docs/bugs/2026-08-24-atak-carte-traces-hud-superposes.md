# Carte ATAK — légende Traces superposée au bandeau grille / réseau

**Date :** 2026-08-24  
**Statut :** corrigé

## Contexte

Sur la carte ATAK web (théâtre Altis), le coin bas-droit concentrait plusieurs cartouches d’information.

## Symptôme

La légende **Traces** recouvrait le bandeau **Grille / Échelle / Contacts / Réseau**. Les deux fenêtres n’étaient plus lisibles. Signalement opérateur : trop de fenêtres superposées dans ce coin.

## Cause

La légende et le bandeau d’état étaient tous les deux collés en `bottom: 10–12px` / `right: 10px`, dans des contextes d’empilement différents (carte Leaflet vs scène carte). La légende, plus haute et plus large, dépassait sous et autour du bandeau. Le copyright Leaflet occupait le même coin.

## Correctif

- Pile unique en bas à droite : Traces au-dessus, bandeau d’état en dessous, interstice de 10 px.
- Le bandeau Grille / Échelle / Contacts / Réseau est dans la pile HTML (plus un calque Leaflet indépendant collé au même coin).
- Copyright Leaflet retiré de ce coin (déjà recouvert).
- Journal d’analyse légèrement relevé pour laisser l’échelle métrique lisible, et largeur limitée pour ne pas rejoindre la pile de droite.

## Fichiers touchés

- `views/atak.php`
- `public/assets/css/atak.css`
- `public/assets/css/atak-motion.css`
- `public/assets/css/atak-cop.css`
- `public/assets/js/atak-map.js`
- `public/assets/js/atak-terrain.js`

## Vérification

Carte ATAK, traces visibles : légende au-dessus du bandeau grille/réseau, sans recouvrement. Journal d’analyse toujours lisible en bas à gauche, au-dessus de l’échelle. Barre d’outils, relief et radio inchangés.

## Statut

corrigé
