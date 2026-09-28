# Relief 3D — erreur fill-extrusion-opacity et outline TextLayer

## Contexte

Overwatch Beta, Relief 3D (MapLibre + deck.gl), après déploiement UPDATE #00723–725.

## Symptôme

Console :

- `layers.ow-buildings-fill.paint.fill-extrusion-opacity: data expressions not supported`
- `ow-gl-unit-labels*: fontSettings.sdf is required to render outline`

(Les 404 `recon_*.jpg` sont des photos absentes du stockage, hors de ce correctif.)

## Cause

- MapLibre n’accepte qu’une **constante** pour `fill-extrusion-opacity`, pas `['get', 'opacity']`.
- deck.gl TextLayer exige SDF pour `outlineWidth` ; la police système n’en fournit pas.

## Correctif

- Opacité volumes = `0.72` (constante) ; source/couche séparées si la couche manquait.
- Libellés : `background` + `getBackgroundColor` à la place de outline ; suppression de la couche `-labels-bg`.

## Fichiers touchés

- `public/assets/js/overwatch-gl/OverwatchGlLayers.js`
- tests / UPDATE #00726

## Vérification

Ctrl+F5, Relief 3D : plus d’erreur MapLibre ni d’avertissement SDF sur les indicatifs.

## Statut

Corrigé.
