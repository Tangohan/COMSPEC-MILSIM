# Overwatch Beta — Relief 3D : « already running » au redimensionnement

## Contexte

Vue Relief 3D Overwatch Beta (MapLibre 4.7.1). Console après bascule 3D, comparatif 2D/3D ou redimensionnement de la fenêtre.

## Symptôme

```
Uncaught Error: Attempting to run(), but is already running.
    at Cs.run (maplibre-gl.js)
    at t.Map._render
    at t.Map.redraw
    at ResizeObserver
```

La carte peut se figer ou rester incomplète. L’erreur se répète (souvent 3 fois).

## Cause

MapLibre suit la taille du conteneur (`trackResize`, ResizeObserver interne) et enchaîne `resize()` puis `redraw()` pendant qu’un rendu est déjà lancé. Overwatch appelait aussi `glMap.resize()` tout de suite après avoir affiché le canvas (et sur `window.resize`), ce qui doublait le cycle.

## Correctif

- `trackResize: false` à la création de la carte.
- Un seul `scheduleResize()` regroupé (32 ms), avec nouvel essai si « already running ».
- Un ResizeObserver Overwatch sur le hôte, débranché à la destruction.

## Fichiers touchés

- `public/assets/js/overwatch-gl/OverwatchGlMap.js`
- `tests/Unit/OverwatchGlAssetTest.php`

## Vérification

Contrôles automatiques : `trackResize: false`, `scheduleResize`, `already running`. En poste : Overwatch Beta, Relief 3D, recharger, agrandir la fenêtre, ouvrir 2D / 3D — plus d’erreur `already running`, le relief reste affiché.

## Statut

corrigé
