# Carte entièrement verte sur le téléphone ATAK

- Date : 2026-09-04
- Statut : corrigé (pack 1.8.18) ; récidive images cassées traitée en 1.8.20 (`2026-09-04-atak-tuiles-images-cassees.md`)

## Contexte

Après le passage à la scène cartographique, Terrain montrait bien la position de l’opérateur, mais le fond restait entièrement vert. Le pack 1.8.17 était bien chargé.

## Symptôme

L’écran Terrain est vert uni. La position bouge. Aucune photo aérienne du terrain n’apparaît.

## Cause

Le résolveur d’extraits de carte attendait le magasin local avant d’afficher une image. Dans l’écran du jeu, ce magasin peut rester silencieux : les extraits n’étaient jamais demandés. Un décor vert de secours restait alors visible.

## Correctif

Les extraits de carte sont demandés tout de suite. Le magasin local ne bloque plus l’affichage. Le décor vert de secours est masqué dès que la carte est prête. Pack **1.8.18**.

## Fichiers touchés

- `web/map-tiles.js`, `web/map-store.js`, `web/live-map.js`
- `web/phone.html`
- `config.cpp`

## Vérification

- Preview `phone.html?preview=map` : photo aérienne visible autour de la position.
- En jeu : pack 1.8.18, Terrain, le terrain n’est plus un écran vert uni.

## Statut

Corrigé dans les sources 1.8.18 — reconstruire le pack et relancer Arma complètement.
