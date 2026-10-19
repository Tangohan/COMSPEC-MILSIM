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

La coque du téléphone (S7 en coque olive, `tools/src/android_s7_ca.png`) est fournie par COMSPEC ; les icônes d'apps et d'outils sont dessinées par COMSPEC (`tools/gen_assets.py`).
Les outils carte (bâtiments, hauteur, terrain plat, ligne de vue) sont réécrits d'après le comportement de Simple Map Tools (POLPOX), sans reprise de code.
Les symboles de carte et certaines icônes restent celles d'Arma 3, référencées et non redistribuées.

## Traductions communautaires

Packs de langue publiés séparément (mods de traduction, `localisation/README.md`), crédités ici et dans l'app Crédits :

- Chinois simplifié (`zh_cn`, mod `comspec_atak_zh`) : RickLeo et MOYA, traduction et création complète du pack.

## Polices embarquées (SIL Open Font License 1.1)

Converties au format police d'Arma par `tools/gen_fonts.py` (dossier `Sources/addons/main/fonts/`, licence jointe dans chaque dossier) :

- Barlow Condensed — Jeremy Tribby (police par défaut du téléphone) ;
- B612 — Airbus, Intactile Design, PolarSys ;
- Rajdhani — Indian Type Foundry ;
- Share Tech — Carrois Apostrophe.

## ATAK Enhancements — Iceman

Aucun code Iceman n'est repris dans ce mod. Sa licence n'est pas documentée dans ce dépôt ; ses fonctionnalités
(alertes, itinéraire, relief, BDA…) seront réécrites à partir de leur comportement tant qu'elle n'est pas connue.
