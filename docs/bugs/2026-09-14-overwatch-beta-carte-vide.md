# Overwatch Beta — carte vide

## Contexte

La page Overwatch Beta (`/-ATAK-OVERWATCH-Beta`) affiche un poste dédié, distinct
de la carte ATAK stable. Les contacts arrivaient, le fond restait vert uni.

## Symptôme

Carte tactique sans tuiles. Coordonnées au survol, contacts à droite, aucun
plan ni photo aérienne.

## Cause

Le Leaflet Beta utilisait un repère simplifié, sans le calage des tuiles du
théâtre. Un filtre visuel assombrissait encore le fond.

## Correctif

Le poste Beta reste une interface neuve (pas la page ATAK stable). La carte
reprend le calage des tuiles du théâtre, le tchat et les réglages ont leurs
propres colonnes, trois lectures Altis sont mémorisées localement.

## Fichiers touchés

- `views/atak-overwatch-beta.php`
- `public/assets/js/atak-overwatch-beta.js`
- `public/assets/css/atak-overwatch-beta.css`
- `public/assets/js/atak-map-crs.js` (calage des tuiles, sans l’écran ATAK)

## Vérification

1. Ouvrir Overwatch Beta, accepter l’avertissement.
2. La carte du théâtre s’affiche.
3. Passer Classique / Aerial / Noir et blanc : les contacts restent en place.
4. Envoyer un message dans le tchat de droite.

## Statut

Corrigé côté sources (à valider sur le poste authentifié).
