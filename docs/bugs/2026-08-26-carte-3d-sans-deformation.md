# Bug — vue inclinée / 3D actif sans déformation du relief

## Contexte

Carte ATAK web, 26/08/2026. L’opérateur active **3D actif**, Vue **Inclinée**, exagération Z **4.0×**, inclinaison **65°**. L’ombrage affiche une couverture de 99 %. La case **Relief et profondeur** est décochée.

## Symptôme

La carte s’incline (perspective) mais l’île reste un plan : aucun soulèvement des collines, aucune déformation du sol. Le bandeau Affichage peut dire que le théâtre n’est pas relevé alors que l’inventaire montre déjà l’ombrage.

## Cause

1. Le maillage altimétrique ne démarrait pas à la restauration de la vue 3D, ni si la carte n’était pas encore prête ; seule l’inclinaison de la vue s’appliquait.
2. Les tuiles du fond viennent d’un autre domaine : le drapage du sol échouait en silence, le canevas restait vide, on ne voyait que le plan incliné.
3. Les altitudes n’étaient envoyées que si un drapeau « prêt » distinct était vrai, alors que l’ombrage utilisait déjà la même couverture.
4. Le bandeau « non encore relevé » ignorait l’inventaire déjà rempli.

La case **Relief et profondeur** n’était pas un verrou du maillage (elle ne concerne que les pastilles) ; il ne faut pas qu’elle le devienne.

## Correctif

- Vue inclinée / 3D actif démarre toujours le maillage du sol, dès que la carte est prête, sans regarder Relief et profondeur.
- Les altitudes déjà relevées (même couverture que l’ombrage) soulèvent les sommets ; si le fond ne peut pas être drapé, un ombrage déformé reste visible.
- Le bandeau Affichage reprend la couverture déjà connue au lieu de dire que rien n’est arrivé.

## Fichiers touchés

- `public/assets/js/atak-terrain-3d.js`
- `public/assets/js/atak-terrain.js`
- `public/assets/js/atak-map.js`
- `public/assets/js/atak-scene-3d.js`
- `public/assets/css/atak.css`
- `app/Controllers/Api/AtakTerrainApiController.php`
- `views/atak.php`
- `tests/Unit/AtakTerrain3dAssetTest.php`
- `tests/Unit/AtakTerrainCoverageStatusAssetTest.php`
- `tests/Unit/AtakScene3dAssetTest.php`
- `tests/Unit/DevDispatchCatalogTest.php`
- `app/Support/DevDispatchCatalog.php` (UPDATE 239)

## Vérification

Tests unitaires des assets et du catalogue. Recette : recharger la carte, Vue inclinée, exagération 4.0×, inclinaison 65°, Relief et profondeur décoché : les collines se voient. Le bandeau ne contredit plus l’inventaire à 99 %.

## Statut

corrigé (sources)
