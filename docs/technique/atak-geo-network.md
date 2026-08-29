# Réseau géographique ATAK (villes, routes, planification)

## Objectif

Remonter depuis Arma les **localités** et le **réseau routier** du théâtre, les stocker côté Athena, puis alimenter les modules JS de **planification d’itinéraire**, **prédiction** et **navigation GPS** sur TACMAP.

## Modules pont (back-office → mod)

| ID | Rôle |
|----|------|
| `geo_places` | Relevé des villes / villages / repères (`nearestLocations`) |
| `geo_roads` | Échantillonnage des routes (`nearRoads`, `roadsConnectedTo`) |
| `route_planning` | Autorise la planification road-aware via `/api/atak/route/plan` |
| `gps_navigation` | Réservé aux enrichissements position / ETA (évolution mod) |

Catalogue : `App\Services\Tactical\AtakBridgeModulesService`.

## Base de données

Migration : `migrations/2026_08_28_001_atak_geo_network.sql`

- `atak_geo_places` — lieux nommés (type, nom, position)
- `atak_geo_road_segments` — segments routiers (extrémités A/B, longueur, sens unique)

Filet à chaud : `App\Support\AtakGeoNetworkSchema`.

## API

| Méthode | Route | Description |
|---------|-------|-------------|
| GET | `/api/atak/geo/places?bbox=…&mapId=1` | Lieux dans une bbox |
| GET | `/api/atak/geo/places?q=Kavala` | Recherche par nom |
| GET | `/api/atak/geo/roads?bbox=…&mapId=1` | Segments dans une bbox |
| GET | `/api/atak/geo/coverage?mapId=1` | Comptages + `geo_ready` |
| POST | `/api/atak/geo/ingest` | Ingest mod (clé API ou CSRF) |
| POST | `/api/atak/route/plan` | Plan A* sur graphe routier (+ repli direct) |

Corps planification :

```json
{
  "mapId": 1,
  "start": { "x": 12000, "y": 15000 },
  "end": { "x": 18000, "y": 9000 },
  "via": [{ "x": 14000, "y": 12000 }],
  "mode": "foot",
  "snap_m": 150
}
```

## Mod Arma

- Relevé : `comspec_overwatch_connect_fnc_sampleGeoNetwork` (`fn_sampleGeoNetwork.sqf`)
- Extension : `COMSPECExtension` → `Geo.Ingest` → POST `/api/atak/geo/ingest`

Lancer en jeu (Zeus / debug) :

```sqf
[] call comspec_overwatch_connect_fnc_sampleGeoNetwork;
```

## Front TACMAP

Scripts :

- `public/assets/js/atak-geo-network.js` — calques villes / routes
- `public/assets/js/atak-route-planner.js` — appel planification
- `tacmap-route-tools.js` — double-clic → plan routier si données disponibles
- `public/assets/js/atak-geo-live.js` — pont live sur `/public/atak` (cases Villes/Routes + `ATAKGeoLive.planRoadRoute`)

Calques carte : **Villes**, **Routes** (barre d’outils TACMAP / préférences ATAK).

Sur ATAK, l’outil **Itinéraire** (`atak-terrain-tools.js`) appelle `ATAKGeoLive.planRoadRoute` au double-clic lorsque le graphe est prêt.

## Activation

```bash
php run-migrations.php
```

Puis activer les modules souhaités dans le back-office ATAK (modules pont).
