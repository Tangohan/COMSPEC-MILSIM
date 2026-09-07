# Carte noire et menus inactifs sur le téléphone ATAK

- Date : 2026-09-04
- Statut : corrigé (pack 1.8.16)

## Contexte

Terrain montrait le bandeau et la télémétrie, mais la zone centrale restait noire. Les boutons du bandeau n’ouvraient pas leurs panneaux.

## Symptôme

Écran Overwatch : fond noir à la place de la carte. Toucher Couches, Outils, Suivis ne changeait rien.

## Cause

L’écran du jeu charge la page seule. Les fichiers de la carte (moteur et fond satellite) n’étaient pas embarqués dans cette page, donc la carte ne s’initialisait pas. Le décor de terrain était masqué. Les boutons du bandeau n’avaient pas d’action directe assez fiable dans l’écran du jeu.

## Correctif

La carte est embarquée dans la page au moment du pack. Un fond terrain reste visible même sans photo satellite. Le bandeau ouvre réellement chaque panneau. Pack **1.8.16**.

## Fichiers touchés

- `web/phone.html`, `web/live-map.js`
- `inline_atak_web.ps1`, `build_atak.bat`
- `config.cpp`

## Vérification

- Preview `phone.html?preview=map` : carte + bandeau Couches / Outils.
- En jeu : pack 1.8.16, Terrain, carte au centre, Couches ouvre le panneau.

## Statut

Corrigé dans les sources 1.8.16 — reconstruire le pack et relancer Arma complètement.
