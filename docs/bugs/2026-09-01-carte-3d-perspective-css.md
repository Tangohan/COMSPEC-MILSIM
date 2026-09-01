# Bug — vue 3D qui incline le plan 2D (trapèze, tuiles en bandes)

## Contexte

Carte ATAK, 1er septembre 2026. La vue relief était encore obtenue en inclinant le conteneur de la carte à plat (perspective CSS + rotation). Le moteur WebGL existait déjà, mais la classe d’inclinaison restait posée sur le plan.

## Symptôme

La carte forme un trapèze qui converge vers le haut. Les tuiles s’étirent en bandes horizontales. Les pastilles (unités, libellés) ne sont pas dans le même espace que le sol. Aucune colline réelle : tout le plan est simplement penché.

## Cause

Une transformation 3D était appliquée à tout le plan de carte (perspective du cadre, rotation du plan). Cela ne soulève pas le terrain : cela déforme l’image. La vue relief WebGL, elle, peut encore montrer un plan plat si le relevé d’altitude n’est pas drapé, mais le défaut visible de la capture venait de l’inclinaison du plan 2D.

## Correctif

- Le plan 2D n’est plus jamais incliné : plus de perspective, ni de rotation, ni de déformation du cadre carte.
- La vue relief est un moteur séparé : le sol est un maillage dont chaque sommet a une altitude, la carte est une texture, la caméra regarde le terrain.
- La grille tactique est dessinée légèrement au-dessus du sol ; les unités sont posées à l’altitude du point + 3 m.
- Le cadrage (centre et zoom) de la carte à plat est repris à l’ouverture de la vue relief, et restitué au retour.

## Fichiers touchés

- `public/assets/css/atak.css`
- `public/assets/css/atak-terrain3d-premium.css`
- `public/assets/js/atak-terrain3d-premium.js`
- `public/assets/js/atak-terrain-3d.js`
- `public/assets/js/terrain3d/Terrain3DRenderer.js`
- `public/assets/js/terrain3d/TerrainGeometryBuilder.js`
- `public/assets/js/terrain3d/TerrainCameraControls.js`
- `public/assets/js/terrain3d/TerrainOverlayManager.js`
- `public/assets/js/terrain3d/TerrainMaterial.js`
- `views/atak.php`
- `tests/Unit/AtakTerrain3dAssetTest.php`
- `tests/Unit/AtakTerrain3dPremiumAssetTest.php`
- `tests/Unit/DevDispatchCatalogTest.php`
- `app/Support/DevDispatchCatalog.php`

## Vérification

Tests unitaires des assets et du catalogue. Recette : carte du poste, **À plat** — plan sans trapèze ; **Relief 3D** — collines si le relevé existe, grille collée au sol, unités au-dessus du relief ; retour à plat reprend le cadrage.

## Statut

corrigé (sources)
