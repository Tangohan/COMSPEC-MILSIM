# Barre d’outils carte ATAK — disparaît à chaque visite

## Contexte

Poste ATAK web (`/atak`). Barre classique horizontale Position / Annoter / Tracer / Analyse / Vue, avec Affichage, Personnaliser et Masquer.

## Symptôme

La barre s’affichait un instant puis disparaissait à chaque rechargement. Aucun bouton « Outils » pour la faire revenir.

## Cause

Le chrome C2 v2 (rail gauche) masquait systématiquement cette barre au démarrage de la carte, sans tenir compte du choix de l’opérateur. Le bouton pour la réafficher était aussi caché. Le repli volontaire (Masquer) n’était donc plus le seul cas où la barre part.

## Correctif

Le rail C2 et la barre classique restent tous les deux visibles. Seul **Masquer** replie la barre ; ce choix est mémorisé. Le bouton **Outils** la fait réapparaître.

## Fichiers touchés

- `public/assets/js/map/atak-c2-bridge.js`
- `public/assets/css/atak-map-c2-live.css`
- `public/assets/js/atak-map-tools.js` (repli déjà mémorisé, inchangé)
- `tests/Unit/AtakMapToolbarPersistAssetTest.php`
- `docs/technique/atak-map-c2-refonte.md`

## Vérification

Tests unitaires `AtakMapToolbarPersistAssetTest`. Recette : ouvrir `/atak`, la barre Position / Annoter / Tracer reste. Recharger : elle est toujours là. Masquer puis recharger : elle reste repliée jusqu’à **Outils**.

## Statut

corrigé
