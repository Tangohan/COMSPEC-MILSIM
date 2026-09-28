# Overwatch Beta — cache local des tuiles (charge VPS)

## Contexte

28 septembre 2026. En Relied 3D / fond photo, le poste martèle `/api/atak/tiles` et le relief RGB. Sous charge, le fond reste gris (seul le DEM s’affiche).

## Symptôme

Carte sans texture, rafales réseau vers Athena, ressenti « la carte ne charge plus ».

## Cause

1. Le service worker contournait toute l’API — aucun cache navigateur des tuiles.
2. À l’activation, le SW **effaçait** aussi le cache Leaflet `athena-overwatch-tiles-*`.
3. Le peintre Relief 3D (`owtile`) retéléchargeait les tuiles Atlas à chaque case Mercator.

## Correctif

- `OverwatchTileCache.js` : Cache API + mémoire (tuiles brutes + cases peintes).
- SW `v10` : cache-first pour `/api/atak/tiles` et `/api/atak/terrain/rgb/`, conservation des caches tuiles.
- `OverwatchGlMap` utilise le cache pour `loadImage` et les cases composées.

## Fichiers touchés

- `public/assets/js/overwatch-gl/OverwatchTileCache.js`
- `public/assets/js/overwatch-gl/OverwatchGlMap.js`
- `public/assets/js/atak-overwatch-beta.js`
- `public/sw.js`
- `views/atak-overwatch-beta.php`
- tests SW / Overwatch GL

## Vérification

1. Ouvrir Overwatch Beta, pan/zoom une zone, Relief 3D.
2. Couper temporairement le réseau ou saturer le VPS : la zone déjà vue doit rester texturée.
3. Libellé « Fonds en cache local ». Recharger avec Ctrl+F5 pour forcer le réseau si besoin.

## Statut

corrigé (à déployer)
