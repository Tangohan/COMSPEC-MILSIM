# Icônes jeu / cTab / Iceman absentes sur ATAK web

## Contexte

Les PNG convertis (Arma 3, cTab, MarkersPlus, Metis) sont bien dans `public/assets/markers/arma/` et servent la page documentation. Les repères remontés par le jeu n’affichaient pas ces icônes sur la carte ATAK.

## Symptôme

Sur la carte web, un point posé en jeu (carte Arma, cTab, ATAK Enhanced / Iceman) apparaît comme un glyphe générique ou un symbole OTAN simplifié, pas comme l’icône du jeu.

## Cause

Trois écarts se cumulaient :

1. La carte ATAK ne chargeait pas l’index des PNG (`arma-marker-library-index.js`), utilisé seulement par la documentation.
2. Le rendu ne consultait le PNG que si le jeu avait envoyé une texture *et* que l’adresse stockée était déjà bonne. Une adresse périmée (mauvais préfixe d’addon, hôte différent) provoquait un 404 silencieux, puis le repli glyphe/SVG.
3. Iceman / NLN cTab n’ont pas de dossier dédié : leurs textures doivent être rabatues sur `ctab/`. Les icônes militaires vanilla (blanc + transparence) n’étaient pas teintées, donc peu lisibles.

## Correctif

- Résoudre l’icône côté carte à partir de la texture jeu, puis de l’index, puis du type (`mil_warning`, `b_inf`, `mplus_…`).
- Recaler l’adresse sur le préfixe d’icônes de la page courante.
- Mapper les préfixes Iceman / NLN cTab vers `ctab/`.
- Charger l’index sur ATAK, Tacmap et Overwatch.
- Teinter les icônes militaires / dessinées vanilla avec la couleur du repère.

## Fichiers touchés

- `public/assets/js/arma-map-markers.js`
- `views/atak.php`, `views/tacmap.php`, `views/overwatch/index.php`
- `app/Support/helpers.php`
- `app/Controllers/Api/AtakApiController.php`
- `public/assets/css/atak-map-popups.css`
- `tests/Unit/AtakMarkerIconPathTest.php`

## Vérification

- Tests unitaires des chemins texture → PNG.
- Contrôle code : ATAK charge l’index avant le rendu des marqueurs.

Vérification visuelle en partie en jeu (poser un repère cTab + un `mil_warning` et confirmer l’icône sur la carte web) : à faire sur le portail déployé.

## Statut

Corrigé (à valider en mission)
