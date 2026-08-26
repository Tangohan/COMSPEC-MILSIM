# Bug — marqueurs collés au sol en vue inclinée

## Contexte

Carte ATAK web, vue inclinée (Position → Affichage : Vue de la carte Inclinée, ou bouton Vue → 3D). Le relief est drapé, la carte est tournée avec `rotateX` / `rotateZ`.

## Symptôme

Les icônes (unités, aériens, annotations) restaient à plat sur le sol et suivaient l’inclinaison : illisibles dès que la carte n’est plus vue du dessus. Les indicatifs n’étaient pas affichés sur le symbole.

## Cause

Toute la carte Leaflet, y compris le volet des marqueurs, reçoit la transformation 3D. Les icônes n’avaient pas de contre-rotation pour rester face à l’écran.

## Correctif

Les glyphes (`.atak-marker-billboard`) contre-rotent pitch et cap pour rester face à l’écran, ancrés à la position carte. En vue inclinée, l’indicatif est affiché sous le symbole.

## Fichiers touchés

- `public/assets/css/atak.css`
- `public/assets/js/atak-marker-sizes.js`
- `public/assets/js/atak-map.js`
- `public/assets/js/nato-sidc-icons.js`
- `tests/Unit/AtakTerrain3dAssetTest.php`

## Vérification

Passer en vue inclinée : les icônes jaunes (aérien) et les unités restent droites à l’écran, avec l’indicatif. Revenir à plat : affichage inchangé.

## Statut

corrigé
