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
Complément : `bootstrap/atak_geo_road_operator_label_migration.php` (colonne `operator_label`)

- `atak_geo_places` — lieux nommés (type, nom, position)
- `atak_geo_road_segments` — segments routiers (extrémités A/B, longueur, sens unique, **libellé opérateur**)

Filet à chaud : `App\Support\AtakGeoNetworkSchema` (ajoute `operator_label` si absente).

Le libellé saisi au poste **n’est jamais écrasé** lors d’un nouvel ingest Arma (`ON DUPLICATE KEY UPDATE` sans toucher `operator_label`).

## API

| Méthode | Route | Description |
|---------|-------|-------------|
| GET | `/api/atak/geo/places?bbox=…&mapId=1` | Lieux dans une bbox |
| GET | `/api/atak/geo/places?q=Kavala` | Recherche par nom |
| GET | `/api/atak/geo/roads?bbox=…&mapId=1` | Segments dans une bbox (`db_id`, `label`, `class`…) |
| POST | `/api/atak/geo/roads/label` | Nommer / effacer le nom d’une route (CSRF navigateur) |
| GET | `/api/atak/geo/coverage?mapId=1` | Comptages + `geo_ready` |
| POST | `/api/atak/geo/ingest` | Ingest mod (clé API ou CSRF) |
| POST | `/api/atak/route/plan` | Plan A* sur graphe routier (+ repli direct) |

### Nommer une route (poste de commandement)

Corps JSON :

```json
{
  "mapId": 1,
  "source_id": "road-42",
  "label": "Axe nord Kavala"
}
```

- `label` vide ou `null` : efface le nom.
- Réponse : segment mis à jour (`road` avec `db_id`, `label`, etc.) et message « Nom de route enregistré. »

## Affichage carte (Lots 2 / 3)

Sur le calque **Routes** :

- Épaisseur et couleur selon la classe (`HIGHWAY` plus marqué, `TRACK` fin et pointillé, etc.).
- Info-bulle au survol (« Route sans nom » si aucun libellé).
- Clic → invite « Nom de cette route » → enregistrement immédiat au poste.
- Libellé permanent visible à partir d’un zoom moyen lorsque un nom existe.

Sur le calque **Villes** :

- Villes un peu plus grandes ; info-bulle avec le nom.
- Nom affiché en permanent (villes / bourgs) à partir du zoom 4 environ.

Les cases **Villes** / **Routes** de la barre d’outils restent le seul moyen d’afficher ou masquer ces calques (`setVisible`).

### Limitation restante (Lot 4 / plus tard)

- Empreintes 2D des bâtiments en vue plate : non livré ici (calque scène 3D réservé à la vue inclinée).
- Tuiles Grad / MEH supplémentaires : reportées (Lot 4).

## Corps planification

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

- `public/assets/js/atak-geo-network.js` — calques villes / routes + nommage
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
