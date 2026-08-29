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

**Activée par défaut** sur `views/atak.php` (`window.ATAK_MAP_C2_V2 = true`).

1. CSS : `atak-map-c2-v2.css` + `atak-map-c2-live.css` (overlay dans `.atak-map-wrap`)
2. Shell live : `#atak-c2-live-shell` — rail gauche (`.tac-c2-rail`), contrôles (`.tac-map-controls`), panneau contexte bas
3. Pont : `public/assets/js/map/atak-c2-bridge.js`
   - Réutilise la carte Leaflet `ATAKMap` (pas de second init)
   - Intercepte / écoute `setUnitsMarkers` + événement `atak:units-updated` (WebSocket / polling via `atak-units.js` → `atak-socket.js`)
   - Affiche les unités via `MarkerManager` (symbologie C2) ; le rendu legacy est désactivé
   - Relie le rail aux outils existants (`#atak-map-tools`, masqué mais actif)

Désactiver temporairement : `window.ATAK_MAP_C2_V2 = false` avant le chargement du bridge.

```javascript
// API exposée après atak:c2-ready
window.ATAKMapC2.setEntities(units);
window.ATAKMapC2.setTracks(trails);
```

## Relation avec modules existants

- **Remplace visuellement** les gros `divIcon` / pictogrammes web pour les unités BFT
- **Conserve** les outils mesure / zones / 3D / NVG (déclenchés depuis le rail)
- **Complète** `atak-terrain-3d.js` ; la bascule 2D/3D des contrôles C2 clique `#atak-view-3d`
