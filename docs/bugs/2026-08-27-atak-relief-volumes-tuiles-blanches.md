# Vue inclinée : relief, bâtiments et tuiles blanches

## Contexte

Carte ATAK web, 27/08/2026, `https://athena.ttrd.fr/public/atak`, théâtre Altis, vue Inclinée, exagération Z 4.0×. Inventaire : 8 265 bâtiments, 20 743 forêts, couverture relief 99 %.

## Symptôme

- Les volumes du jeu sont cochés et comptés, mais aucun bâtiment ni forêt n’apparaît sur la carte.
- Le curseur d’exagération Z ne change rien : l’île reste un plan incliné.
- Un grand rectangle blanc (souvent en mer, haut-gauche) à la place d’une tuile.

Bandeau rouge possible : « Le poste n’atteint pas ses données… » (autre file d’attente API, pas la cause du relief).

## Cause

1. Le maillage WebGL et le canevas des volumes sont des **frères** du `.leaflet-map-pane` (z-index **400**). Avec des tuiles opaques, Leaflet recouvre entièrement le sol déformé et les volumes.
2. Un correctif précédent forçait `opacity: 1` sur les tuiles dès que le maillage était « prêt », donc on ne voyait que le fond plat.
3. Les tuiles CDN absentes (mer / zoom) reçoivent un GIF 1×1. Ce pixel, drapé sur tout le carré, devient un rectangle blanc.

## Correctif

- En vue inclinée, une fois le maillage prêt : tuiles Leaflet transparentes. Pastilles et tracés restent au-dessus.
- Ignorer les images 1×1 / GIF data-URI pour le drapage.
- Masquer l’image Leaflet en cas d’échec de tuile (fond sombre du poste, plus de plaque blanche).
- Volumes trop petits en vue d’ensemble : empreinte minimale à l’écran.

## Fichiers touchés

- `public/assets/css/atak.css`
- `public/assets/js/atak-terrain-3d.js`
- `public/assets/js/atak-scene-3d.js`
- `public/assets/js/atak-map.js`
- `tests/Unit/AtakTerrain3dAssetTest.php`
- `tests/Unit/AtakScene3dAssetTest.php`

## Vérification

Recharger la carte (vider le cache). Vue Inclinée, exagération 4.0× : les collines se soulèvent. Case bâtiments/forêts : volumes visibles, surtout en se rapprochant. Plus de plaque blanche en mer.

## Statut

corrigé (sources)
