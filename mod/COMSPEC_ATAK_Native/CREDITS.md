# Crédits — COMSPEC ATAK Native

## Inspirations

Better CAS Environment (BCE, Aaren, https://github.com/Aaren882/Better-CAS-Environment-) a inspiré certaines idées
(registre d'applications, messages directs, liste de groupe, visionneur de tâches). Aucun code ni contenu de BCE
n'est présent dans ce mod : ces fonctions ont été entièrement réécrites par COMSPEC.

## Licence de ce mod

`@COMSPEC_ATAK_Native` est distribué sous la licence COMSPEC (voir `LICENSE`, tous droits réservés) :
usage personnel depuis la page officielle du Steam Workshop ; copie, modification, ré-empaquetage et
redistribution interdits sans autorisation écrite de COMSPEC. Les éléments de tiers listés ci-dessous
gardent leur propre licence.

La coque du téléphone (S7 en coque olive, `tools/src/android_s7_ca.png`) est fournie par COMSPEC ; les icônes d'apps et d'outils sont dessinées par COMSPEC (`tools/gen_assets.py`).
Les outils carte (bâtiments, hauteur, terrain plat, ligne de vue) sont réécrits d'après le comportement de Simple Map Tools (POLPOX), sans reprise de code.
Les symboles de carte et certaines icônes restent celles d'Arma 3, référencées et non redistribuées.

## Traductions communautaires

Packs de langue publiés séparément (mods de traduction, `localisation/README.md`), crédités ici et dans l'app Crédits :

- Chinois simplifié (`zh_cn`, mod `comspec_atak_zh`) : RickLeo et MOYA, traduction et création complète du pack. Leur traduction (version 1.1) est intégrée au mod depuis la 2.2.1 (`i18n/zh_cn.txt`, polices de secours Noto Sans CJK du jeu) ; le pack reste compatible.

## Polices embarquées (SIL Open Font License 1.1)

Converties au format police d'Arma par `tools/gen_fonts.py` (dossier `Sources/addons/main/fonts/`, licence jointe dans chaque dossier) :

- Barlow Condensed — Jeremy Tribby (police par défaut du téléphone) ;
- B612 — Airbus, Intactile Design, PolarSys ;
- Rajdhani — Indian Type Foundry ;
- Share Tech — Carrois Apostrophe.

Images des caractères des bandeaux à LED des écrans TOC (`Sources/addons/main/data/toc/led/`, une image par caractère,
faites par `tools/gen_toc_led_glyphs.py`) : dessinées avec Cascadia Mono Bold — Microsoft, licence SIL Open Font License 1.1.

## ATAK Enhancements — Iceman

Aucun code Iceman n'est repris dans ce mod. Sa licence n'est pas documentée dans ce dépôt ; ses fonctionnalités
(alertes, itinéraire, relief, BDA…) seront réécrites à partir de leur comportement tant qu'elle n'est pas connue.
