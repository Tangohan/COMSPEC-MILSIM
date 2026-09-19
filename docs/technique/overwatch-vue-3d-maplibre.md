# Vue 3D Overwatch Beta (MapLibre + deck.gl)

Poste Overwatch Beta uniquement (`/atak-overwatch-beta`). La carte ATAK classique (`/atak`) conserve le maillage Three.js / canvas 2.5D.

À terme, MapLibre est le moteur cartographique unique (pitch 0 = à plat, pitch libre = 3D). Les tracés et la visée restent pour l’instant sur Leaflet ; les calques tactiques deck.gl sont les mêmes dans les deux lectures.

## Couches

| Couche | Contenu | Rythme |
| --- | --- | --- |
| **Terrain** | DEM, ombrage, courbes, routes | Précalculé |
| **Scène** | Bâtiments, forêts, obstacles linéaires | Précalculé (`scene-mesh.json`, schéma 3) |
| **Tactique** | BFT, unités, véhicules, drones, marqueurs, zones, TASK, SSE, BDA, photos | Synchronisation fréquente |

## Comportement

- **À plat (2D)** : Leaflet, CRS mètres, inchangé.
- **Relief 3D** : second canvas MapLibre. Curseurs Exagération Z et Inclinaison.
- **Tactique 3D** : même moteur, inclinaison plus forte, orbit (clic droit + glisser), double-clic pour viser un point.
- **Nord** : pitch 0, cap nord (lecture orthographique).
- **Unité** : la caméra suit le groupe sélectionné.
- **Sol** : caméra près du terrain (~2 m de lecture).
- Bâtiments : prismes (deck.gl), base calée sur le DEM, hauteur = AGL relevée.
- Obstacles : murs, clôtures, lignes électriques, ponts, rochers, pylônes — plus utiles que des milliers d’arbres.
- Forêts : polygones regroupés. Imposteurs seulement à très fort zoom.
- Contacts : pastilles au-dessus du sol. Replay : positions et trajectoires sur le relief.
- Outils de tracé / visée : restent en vue à plat, résultats affichés dans le tiroir.
- Masque de visibilité, horizon, coupe verticale A→B, mesure 3D.
- Comparaison 2D/3D (même centre et zoom), occlusion des symboles, piles d’icônes.
- Volumes `z_min`/`z_max`, étages RDC…toit, ancrages sur façade.
- Replay cinématique, traces fantômes, densité de passages, vues caméra.
- Préchargement dans le sens de la caméra, cache IndexedDB versionné.
- Inspection (case cartographie) et tuiles de relief manquantes.

## Fiche construction

Clic sur un volume (hors groupe lointain) → `GET /api/atak/scene/object` → tiroir : nom lisible, grille, orientation, niveaux, hauteur, entrées si relevées, photos proches. Actions : Marquer, Objectif, Entrée, Photo, TASK.

X-Ray : le volume choisi reste opaque, les autres s’estompent. Pas d’intérieur 3D.

## LOD géographique (serveur, bbox + zoom)

| Niveau | Usage | Contenu |
| --- | --- | --- |
| 0 | Stratégique | Agglomérations + forêts |
| 1 | Opérationnel | Empreintes simplifiées |
| 2 | Tactique | Volumes + orientation + obstacles |
| 3 | Proximité | Détail POI. Refusé si la bbox dépasse ~2,5 km² |

Le navigateur ne reçoit pas le LOD 3 quand il regarde tout Altis.

## Visée et profil

La visée croise le DEM et les volumes (bâtiments et obstacles). Le tiroir affiche distance, cause (bâtiment / mur / couvert…) et altitude de l’obstacle.

Le profil d’un tracé : distance, D+, D−, pente max, altitudes min/max, coloration du tracé selon la pente.

## Qualité du relevé

Case « Qualité du relevé (cartographie) », éteinte par défaut.

- Vert : données complètes
- Orange : dimensions approximées
- Gris : position seulement
- Rouge : géométrie suspecte

## Projection

Le théâtre n’est pas du WGS84. `TheaterProjection` (JS) et `AtakTheaterProjection` (PHP) convertissent `(x, y)` Arma avec les `offsetX` / `offsetY` / `worldSize` de `ATAK_MAP_CONFIG` vers une emprise locale (1 m ≈ 1 m près de l’équateur).

Les fonds (plan / carte du jeu / photo) sont redessinés via le protocole MapLibre `owtile://` à partir du même damier de tuiles.

## CSP

MapLibre et deck.gl créent des Web Workers en URL `blob:`. `SecurityHeadersMiddleware` impose `worker-src 'self' blob:` (y compris si `APP_CSP` est défini sans cette directive). Sans cela, Relief 3D reste un canvas vide : le navigateur retombe sur `script-src` et refuse le worker.

La carte est créée avec `trackResize: false`. Overwatch recale la taille via `scheduleResize` (un passage groupé) pour éviter l’erreur MapLibre `Attempting to run(), but is already running` au ResizeObserver.

## Relief

`GET /api/atak/terrain/rgb/{z}/{x}/{y}` sert des PNG Terrain-RGB (cache `storage/atak_terrain/shared/{mapId}/rgb/`). Source : grille DEM existante.

## Scène

`GET /api/atak/scene?bbox=&kind=&limit=` (plafond 8000). `kind` : building, forest, wall, fence, power, bridge, rock, pylon, obstacle.

`GET /api/atak/scene/mesh?bbox=&lod=0|1|2|3` sert la géométrie **précalculée par carte**. Unités, tracés et tags restent dynamiques.

`GET /api/atak/scene/object?id=` : fiche d’un volume (nom lisible, pas le classname en titre).

Le relevé jeu (`Scene.Ingest`) envoie aussi les obstacles linéaires et le nombre d’entrées des maisons.

## Lumière

`MapLibre setLight` suit l’heure de mission (`daytime`) et la météo déjà affichée.

## Fichiers

| Fichier | Rôle |
| --- | --- |
| `public/assets/js/overwatch-gl/TheaterProjection.js` | Arma ↔ lng/lat locale |
| `public/assets/js/overwatch-gl/OverwatchGlMap.js` | Hôte MapLibre, tuiles, terrain, caméra |
| `public/assets/js/overwatch-gl/OverwatchGlTactics.js` | Masque, horizon, coupe, split, vues, diagnostic |
| `public/assets/js/overwatch-gl/OverwatchGlLayers.js` | deck.gl bâtiments / obstacles / forêts / contacts |
| `app/Services/Tactical/AtakSceneKind.php` | Types, LOD, qualité, libellés |
| `app/Services/Tactical/AtakSceneMeshBake.php` | Précalcul footprints + LOD + obstacles |
| `app/Services/Tactical/AtakTerrainSight.php` | Visée volumes + profil pente |
| `public/assets/vendor/maplibre-gl/` | MapLibre 4.7.1 |
| `public/assets/vendor/deck.gl/` | deck.gl 9.1.12 |
