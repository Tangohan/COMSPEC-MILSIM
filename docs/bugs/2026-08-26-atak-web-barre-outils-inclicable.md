# Barre d’outils ATAK web — rien ne se clique

## Contexte

Poste ATAK web. La bande Position / Annoter / Tracer / Analyse / Vue s’affichait, avec Position entouré en vert, mais aucun clic n’ouvrait d’action.

## Symptôme

On ne pouvait ni ouvrir un groupe, ni lancer Grille, Mesurer, Trait, etc. L’impression : « on ne peut rien faire, même pas cliquer ».

## Cause

Deux feuilles se contredisaient. Le chrome du poste traitait les intitulés comme du décor (clics ignorés) et coupait la ligne en hauteur. L’autre feuille plaçait les vrais boutons dans un menu sous la barre. Résultat : on ne voyait que les intitulés, et ils ne répondaient pas.

## Correctif

Les commandes restent sous chaque intitulé, cliquables. Les libellés-boutons reçoivent à nouveau les clics. Le script ne masque plus les groupes.

## Fichiers touchés

- `public/assets/css/atak-c2-shell.css`
- `public/assets/css/atak.css`
- `public/assets/js/atak-map-tools.js`
- `views/atak.php`
- `tests/Unit/AtakMapToolbarAssetTest.php`
- `app/Support/DevDispatchCatalog.php` (UPDATE 229)

## Vérification

Tests unitaires sur les feuilles, le script et la vue. Recette : recharger la carte, cliquer Grille / Mesurer / Trait / Affichage.

## Statut

corrigé
