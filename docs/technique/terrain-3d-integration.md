# Terrain 3D — intégration Athena / ATAK

Module **Terrain3DRenderer** : remplace la surface plate de la carte par un relief Three.js géométrique, tout en conservant l’interface tactique (panneaux, journal, overlays).

## Fichiers

| Fichier | Rôle |
|---------|------|
| `public/assets/js/terrain3d/initTerrain3D.js` | Point d’entrée `initTerrain3D()` |
| `public/assets/js/terrain3d/Terrain3DRenderer.js` | Orchestrateur principal |
| `public/assets/js/terrain3d/HeightmapLoader.js` | Image grayscale ou tableau 2D → grille Z |
| `public/assets/js/terrain3d/TextureLoader.js` | Texture diffuse (carte PNG/JPG) |
| `public/assets/js/terrain3d/TerrainGeometryBuilder.js` | `PlaneGeometry` + déplacement sommets + normales |
| `public/assets/js/terrain3d/TerrainMaterial.js` | Matériau + éclairage + brouillard |
| `public/assets/js/terrain3d/TerrainCameraControls.js` | Vue inclinée, pan, zoom, limites rotation |
| `public/assets/js/terrain3d/TerrainOverlayManager.js` | Marqueurs CSS2D au-dessus du relief |
| `public/assets/css/terrain3d-demo.css` | Styles démo (réutilisables pour le HUD) |
| `demo/demo-terrain3d.html` | Démo autonome |

## Démo locale

Ouvrir avec un serveur HTTP statique (modules ES) :

```bash
cd /chemin/vers/athena
python3 -m http.server 8080
# Puis : http://localhost:8080/demo/demo-terrain3d.html
```

## API

```javascript
import { initTerrain3D } from '/public/assets/js/terrain3d/initTerrain3D.js';

const terrain = await initTerrain3D(document.getElementById('atak-map-host'), {
  textureUrl: '/ressources/maps/altis/overview.png',
  heightmapUrl: '/api/atak/terrain/hillshade?mapId=1', // ou image grayscale
  // heightData: [[0,12,...], [...]],  // alternative : injection tableau 2D
  width: 30720,          // largeur monde (ex. Altis)
  height: 30720,
  heightScale: 1.8,      // exagération verticale
  minAltitude: 0,
  maxAltitude: 900,
  segments: 128,           // subdivisions du mesh (128 → 128×128 quads)
  markers: [
    { id: 'u1', x: 15000, y: 15000, label: 'ALPHA-1', type: 'unit', color: '#35d6a1' },
  ],
  fog: true,
});
```

### Méthodes

| Méthode | Description |
|---------|-------------|
| `setTexture(url)` | Remplace la texture diffuse |
| `setHeightmap(url)` | Recharge une heightmap image et reconstruit le mesh |
| `setHeightData(array)` | Injecte un tableau 2D d’altitudes |
| `setHeightScale(n)` | Exagération verticale sans rechargement |
| `toggle2D3D(mode?)` | Bascule 2D ortho ↔ 3D incliné (`'2d'` / `'3d'`) |
| `focusOnGrid(x, y)` | Centre la caméra sur une coordonnée grille |
| `updateMarkers(markers)` | Met à jour les overlays tactiques |
| `dispose()` | Libère WebGL / listeners |

## Intégration dans `/public/atak`

**Statut : activé** via `window.ATAK_TERRAIN3D_PREMIUM = true` dans `views/atak.php`.

| Fichier | Rôle |
|---------|------|
| `public/assets/js/atak-terrain3d-premium.js` | Pont live : bouton `#atak-view-3d`, API heights, marqueurs BFT |
| `public/assets/css/atak-terrain3d-premium.css` | Hôte canvas + marqueurs CSS2D |
| `public/assets/js/atak-terrain-3d.js` | Early-exit si premium (pas de maillage CSS-pitch) |

1. **Conteneur** dans `.atak-map-stage` :

```html
<div class="atak-map-stage">
  <div id="terrain3d-container" class="terrain3d-host" hidden></div>
  <div id="atak-map"></div>
</div>
```

2. **Chargement** (déjà dans `views/atak.php`) :

```html
<script>window.ATAK_TERRAIN3D_PREMIUM = true;</script>
<script src="…/atak-terrain-3d.js"></script>
<script type="module" src="…/atak-terrain3d-premium.js"></script>
```

Le pont décode `GET /api/atak/terrain?include=heights` (`int16le_b64`), appelle `setHeightData`, bascule Leaflet ↔ Three.js sur `#atak-view-3d`, et synchronise les unités via `atak:units-updated`.

3. **Données Arma** — conversion côté JS (implémentée dans le pont) :

```javascript
function decodeHeights(apiResponse) {
  const raw = atob(apiResponse.heights);
  const arr = new Int16Array(raw.length / 2);
  for (let i = 0; i < arr.length; i++) {
    let v = raw.charCodeAt(i * 2) | (raw.charCodeAt(i * 2 + 1) << 8);
    arr[i] = v > 32767 ? v - 65536 : v;
  }
  return { cols: apiResponse.cols, rows: apiResponse.rows, data: arr };
}
// Puis : terrain.setHeightData(flatRows) ou étendre HeightmapLoader.fromApi()
```

4. **Marqueurs BFT** — brancher le flux WebSocket existant :

```javascript
window.addEventListener('atak:units-updated', (ev) => {
  const markers = ev.detail.units.map(u => ({
    id: u.id,
    x: u.pos_x,
    y: u.pos_y,
    label: u.call_sign,
    type: u.side === 'EAST' ? 'hostile' : 'unit',
  }));
  window.ATAKTerrainThree.updateMarkers(markers);
});
```

5. **Bascule 2D/3D** — réutiliser le bouton `#atak-view-3d` :

```javascript
document.getElementById('atak-view-3d').addEventListener('click', () => {
  const mode = window.ATAKTerrainThree.toggle2D3D();
  document.getElementById('atak-map').hidden = mode === '3d';
});
```

## Fallback sans altitude

Si `heightmapUrl` et `heightData` sont absents ou en erreur, le module génère une **grille plate** (`HeightmapLoader.fallback`) : la texture reste visible, le relief est neutralisé jusqu’à réception d’un relevé ACE « Relever le relief ».

## Performance

- `segments: 128` → ~32k triangles : fluide desktop.
- Pour de grandes cartes (Altis 30 km), limiter la zone affichée ou decimer la grille côté serveur.
- `renderer.setPixelRatio(Math.min(devicePixelRatio, 2))` limite le sur-échantillonnage Retina.

## Différence avec `atak-terrain-3d.js`

Le module existant (`public/assets/js/atak-terrain-3d.js`) déforme les tuiles Leaflet en **WebGL 2D** (CSS pitch). **Terrain3DRenderer** est une vraie scène **Three.js** avec mesh, normales et caméra 3D — base recommandée pour une vue topo drapée premium.

## Dépendances

- **Three.js r160** — vendored sous `public/assets/vendor/three/` (CSP `script-src 'self'`)
- Import map `three` dans `views/atak.php` pour OrbitControls / CSS2DRenderer
- Fallback optionnel CDN jsDelivr si `ATAK_THREE_BASE` / `threeBase` pointe ailleurs
- Navigateur desktop moderne (WebGL2)
