# Barre d’outils compacte et inventaire Affichage écrasé

## Contexte

Poste ATAK web, branche `split/atak-web-carte`. La barre C2 (outils toujours visibles sous les intitulés) avait déjà été rétablie (#229). Un passage ultérieur a ramené le look compact : seuls les intitulés Position / Annoter / Tracer / Analyse / Vue restaient visibles.

## Symptôme

- Barre : intitulés avec chevron seulement. Position cerclé en vert. À droite, le mot « Barre » flottait au-dessus d’Affichage / Personnaliser / Masquer. Les commandes Grille, Mesurer, Trait, 3D n’étaient pas cliquables.
- Affichage : les lignes d’inventaire collaient (« Ombrage Pas encore sur le poste »). L’en-tête pouvait dire couverture 99 % · Altis avec Ombrage coché, pendant que l’inventaire disait encore absent.

## Cause

Deux feuilles se contredisaient. `atak.css` rangeait les groupes en ligne, traitait les intitulés comme des menus, et mettait le bloc chrome en colonne (titre « Barre » au-dessus). Le chrome C2 (`atak-c2-shell.css`) voulait les boutons en flux sous les intitulés, mais il était chargé *avant* `atak-cop.css` et des règles device plus précises. Les boutons disparaissaient (menu hors flux / ligne trop basse). Dans Affichage, les lignes d’état n’avaient pas assez d’air, et le bandeau 99 % venait du relief sans mettre à jour la ligne Ombrage.

## Correctif

- Le chrome C2 est chargé **après** `atak.css` et `atak-cop.css`.
- Les groupes d’outils sont en colonne, boutons toujours visibles (`position: static`, y compris `[hidden]`).
- Le titre « Barre » est masqué : Affichage / Personnaliser / Masquer tiennent sur une ligne.
- Inventaire : libellé à gauche, état à droite, interligne lisible.
- Dès que la couverture du relief est connue, Ombrage / relevé divers suivent ce bandeau.

## Fichiers touchés

- `views/atak.php`
- `public/assets/css/atak-c2-shell.css`
- `public/assets/css/atak.css`
- `public/assets/css/atak-cop.css`
- `public/assets/js/atak-terrain.js`
- `tests/Unit/AtakMapToolbarAssetTest.php`
- `tests/Unit/AtakTerrainCoverageStatusAssetTest.php`
- `app/Support/DevDispatchCatalog.php` (UPDATE 243)

## Vérification

Tests unitaires : outils en flux, intitulés-boutons cliquables, C2 après `atak.css` / `atak-cop.css`, inventaire en deux colonnes. Recette : recharger la carte, cliquer Grille / Mesurer / Trait / Affichage ; l’inventaire se lit ligne par ligne.

## Statut

corrigé
