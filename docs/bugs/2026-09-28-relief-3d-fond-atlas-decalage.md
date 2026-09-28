# Overwatch Beta — Relief 3D : fond décalé (contacts en mer)

## Contexte

28 septembre 2026. Overwatch Beta, vue Relief 3D sur Altis. Fond photo / carte du jeu (Atlas).

## Symptôme

L’île (piste, baie) apparaît, mais les contacts et les volumes (bâtiments) flottent au nord-est dans la mer. Couture horizontale visible sur le fond.

## Cause

Le peintre `owtile` réutilisait le `tileWidth` Jetelain du plan (212) pour les tuiles Atlas (`tileSize` 381). Écart ≈ 13 km sur l’axe nord–sud : le fond se décale, les positions monde (contacts / scène) restent justes.

## Correctif

- `TheaterProjection.armaTilesForWorld` : si le fond fournit un `tileSize`, l’utiliser comme `tileWidth` ; grille Atlas en mètres monde (comme `atak-aerial`).
- `atak-aerial.overlaySpec` : exposer `tileWidth` aligné sur `tileSize`.

## Fichiers touchés

- `public/assets/js/overwatch-gl/TheaterProjection.js`
- `public/assets/js/atak-aerial.js`
- `tests/Unit/OverwatchGlAssetTest.php`

## Vérification

Overwatch Beta → fond Photo aérienne ou Carte du jeu → Relief 3D → Ctrl+F5. Le contact doit coller à la côte / au terrain, plus de champ de structures en mer.

## Statut

corrigé (à déployer)
