# Refonte cartographique C2 — Athena / ATAK v2

Interface cartographique tactique moderne : symbologie MIL-STD simplifiée, LOD, clustering, traces fines, shell C2 dense.

## Démo

```bash
python3 -m http.server 8765
# http://localhost:8765/demo/demo-atak-c2-map.html
```

Vue split 2D (haut) + 3D (bas) pour la démo. En production, une seule zone carte + bascule 2D/3D (`MapControls`).

## Architecture

```
public/assets/js/map/
├── TacticalSymbol.js       — symboles APP-6 simplifiés (SVG)
├── MarkerLOD.js            — taille / labels selon zoom
├── MarkerClusterManager.js — clustering [ N ]
├── MarkerManager.js        — marqueurs Leaflet 2D
├── MarkerManager3D.js      — billboards + tige d'ancrage
├── TrackRenderer.js        — traces 1–2 px, fade temporel
├── MapRenderer.js          — encapsulation Leaflet
├── MapControls.js          — nord, zoom, 2D/3D, suivi
├── MapUI.js                — top bar, tool rail
├── SelectedEntityPanel.js  — panneau contextuel bas
├── MapLayerManager.js      — visibilité calques
└── initMapC2.js            — assemble le tout
```

CSS : `public/assets/css/atak-map-c2-v2.css`

## Symbologie

Types : `INFANTRY`, `VEHICLE`, `AIR`, `UAV`, `COMMAND`, `MEDICAL`, `OBSERVATION`, `STATIC_POSITION`

Affiliations : `FRIENDLY` (rectangle), `HOSTILE` (losange), `NEUTRAL` (carré), `UNKNOWN` (quatre-feuilles)

Tailles LOD :
| Zoom | Taille | Labels |
|------|--------|--------|
| éloigné | 18 px | symbole seul |
| ops | 20 px | + indicatif |
| tac | 24 px | + rôle |
| rapproché | 28 px max | + statut |

Statuts : `ONLINE`, `DEGRADED` (opacité), `STALE` (contour pointillé), `LOST` (fantôme), `KIA` (barre)

## Intégration ATAK live

1. Inclure CSS + modules dans `views/atak.php`
2. Remplacer la toolbar `#atak-map-tools` par `.tac-c2-rail` + `.tac-map-controls`
3. Brancher le flux unités existant :

```javascript
import { initMapC2 } from '/public/assets/js/map/initMapC2.js';

const c2 = await initMapC2({
  map2dEl: document.getElementById('atak-map'),
  map3dEl: document.getElementById('terrain3d-container'),
  config: window.Arma3Map.Maps.altis,
  terrain: { /* heightmap API */ },
});

// Depuis atak-units.js / socket :
c2.setEntities(unitsFromBft);
c2.setTracks(trailsFromBft);
```

4. Feature flag recommandé : `window.ATAK_MAP_C2_V2 = true`

## Relation avec modules existants

- **Remplace visuellement** les gros `divIcon` / pictogrammes web
- **Conserve** milsymbol en option via extension future de `TacticalSymbol`
- **Complète** `atak-terrain-3d.js` (WebGL 2D Leaflet) par `terrain3d/` + `MarkerManager3D`
