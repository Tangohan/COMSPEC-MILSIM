# Crédits — COMSPEC ATAK Native

## Better CAS Environment (BCE) — Aaren

- Dépôt : https://github.com/Aaren882/Better-CAS-Environment-
- Licence : Arma Public License Share Alike (APL-SA)

Fonctionnalités reprises de BCE et réécrites pour le terminal natif (sans cTab) :

- registre d'applications déclaré en config (principe des `app.hpp` de BCE) → `COMSPEC_ATAK_Apps` ;
- messages directs entre joueurs par événement CBA ciblé (app message de BCE) → `fn_p2pSend` / `fn_p2pReceive` ;
- liste de groupe (app group de BCE) → `fn_pageGroup` ;
- visionneur de tâches (app taskViewer de BCE) → `fn_pageTasks`.

Non repris : `UI_Anim` (ressort du téléphone), cause des fermetures de jeu documentées dans `docs/bugs`.

## Licence de ce mod

Conformément à l'APL-SA de BCE, `@COMSPEC_ATAK_Native` est distribué sous APL-SA (voir `LICENSE`) :
crédit aux auteurs, usage non commercial (le mod reste gratuit et n'est réservé à aucune offre payante),
même licence pour toute adaptation, Arma uniquement.

Les icônes utilisées sont celles d'Arma 3 (`\a3\ui_f\data\igui\cfg\simpletasks\types\`), référencées et non redistribuées.

## ATAK Enhancements — Iceman

Aucun code Iceman n'est repris dans ce mod. Sa licence n'est pas documentée dans ce dépôt ; ses fonctionnalités
(alertes, itinéraire, relief, BDA…) seront réécrites à partir de leur comportement tant qu'elle n'est pas connue.
