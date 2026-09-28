# Relief 3D — symboles flottants, taille instable, position ambiguë

## Contexte

Overwatch Beta, mode Relief 3D (MapLibre + deck.gl), symbologie OTAN via milsymbol.

## Symptôme

Les contacts grossissaient ou rétrécissaient avec la distance, flottaient sans ancrage au sol, perdaient leur lisibilité derrière un bâtiment ou sur photo claire, et montraient tous les indicatifs en même temps. La sélection au clic était imprécise.

## Cause

- Symbole placé à une altitude fixe sans tige ni pied au sol.
- Pas de distinction taille écran / distance monde pour le declutter.
- Texture milsymbol générée à 1× DPR, halo insuffisant.
- Raycasting limité à l’icône billboard (petite surface).

## Correctif

- Billboard en pixels (`sizeUnits: 'pixels'`), texture 2–3× + contour sombre.
- Pied au sol + tige verticale jusqu’au symbole ; aéronefs à l’altitude réelle.
- Opacité ~60 % si le contact est dans un volume ; colonne médicale pulsante.
- Declutter selon distance projetée en pixels ; indicatif complet seulement sélection / survol.
- Couche de sélection invisible élargie + anneau au survol.

## Fichiers touchés

- `public/assets/js/overwatch-gl/OverwatchGlLayers.js`
- `public/assets/js/overwatch-gl/OverwatchGlSymbols.js`
- `tests/Unit/OverwatchGlAssetTest.php`
- `app/Support/DevDispatchCatalog.php` (UPDATE #00723)
- `tests/Unit/DevDispatchCatalogTest.php`

## Vérification

- Tests asset / catalogue.
- En session : Ctrl+F5, Relief 3D — tige jusqu’au sol, taille stable, survol avec indicatif, colonne sur alerte médicale.

## Statut

Corrigé (client).
