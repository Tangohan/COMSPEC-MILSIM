# Téléphone ATAK — menu ouvert tout seul et arrêt brutal

## Contexte

Athena 1.0.137 chargé (barre : ATAK 1.0.137). Capture 14:10,
RPT `Arma3_x64_2026-09-16_14-08-13.rpt` : AutoArray puis ACCESS_VIOLATION à 14:11:38.

## Symptôme

- Le menu d’applications **revient d’un coup** juste après l’ouverture
  (écran de chargement du téléphone qui se retire).
- Le chevron ne le referme pas.
- Le jeu se ferme encore une vingtaine de secondes après.

## Cause

Le menu IceMan n’est pas seulement le fond 4660. La grille d’icônes
(Message, Missions, Messagerie…) est un **second cadre**. On masquait le
fond, pas la grille. À l’ouverture, la grille était réaffichée, d’où le
« pop ». La carte continuait d’être coupée à 60 %, jusqu’à un cadre
invalide.

## Correctif

- Grille d’icônes masquée avec le fond, tant que le chevron de **cette**
  session n’a pas ouvert le menu.
- Le chevron est le seul ouvreur.
- La carte garde toute la largeur : le menu se pose par-dessus, à droite.
  Plus de rétrécissement en cascade.

Athena 1.0.138.

## Fichiers touchés

- `atak_athena/functions/fn_athena_enforceDrawer.sqf`
- `atak_athena/functions/fn_ATAK_Check_Layout.sqf`
- `atak_athena/functions/fn_athena_updateMapHud.sqf`
- `atak_athena/functions/fn_athena_installMapHud.sqf`
- `atak_athena/XEH_postInitClient.sqf`
- `atak_athena/config.cpp`

## Vérification

1. Quitter Arma complètement, recharger le pack (Athena 1.0.138).
2. Ouvrir le téléphone : carte pleine largeur, menu fermé, **sans pop**.
3. Chevron : le menu s’ouvre dans l’écran, puis se referme.
4. Fermer le téléphone, le rouvrir : le menu est encore fermé.
5. Attendre une minute : le jeu reste ouvert.

## Statut

Corrigé côté sources (à valider in-game après relance Arma).
