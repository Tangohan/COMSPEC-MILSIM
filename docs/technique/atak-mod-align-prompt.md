# Prompt — Aligner le mod Overwatch sur les payloads ATAK du site

> Copier-coller ce prompt pour un agent / développeur mod.  
> État Athena (site) en avance : geo live, route A*, 3D premium, C2 v2, waypoints.

## Mission

Aligner le pack Arma **comspec-overwatch-addons/connect** sur les endpoints déjà ingérés par Athena (`/public/atak`).

## Priorités

### P0 — Geo network en mission
- Déclencher `comspec_overwatch_connect_fnc_sampleGeoNetwork` (ACE + relevé théâtre Zeus).
- Modules pont `geo_places` / `geo_roads`.
- Payload `POST /api/atak/geo/ingest` :
  - places : `id`, `type` (CITY|TOWN|VILLAGE|LANDMARK|OTHER), `name`, `x`, `y`, `z?`, `radius_m?`
  - roads : `id`, `ax`, `ay`, `bx`, `by`, `class` (HIGHWAY|PRIMARY|SECONDARY|TRACK|OTHER), `one_way?`
- Vérifier `GET /api/atak/geo/coverage?mapId=` → `geo_ready`.

### P1 — GPS / waypoints
- Poll `GET /api/atak/assignments` / routes waypoints.
- `POST /api/atak/waypoints/{id}/reached`.
- Enrichir `POST /api/atak/position` `extra` : `eta_seconds`, `distance_to_destination_m`, `active_route_id` si utile.
- Gate module `gps_navigation`.

### P2 — Qualité C2 / 3D
- Relevé théâtre (pas seulement AO 4 km) pour heights 3D.
- `platform` / `affiliation` stables pour `atak-c2-bridge.js`.

## Fichiers mod
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_sampleGeoNetwork.sqf`
- `fn_initACE.sqf`, `fn_sampleTheater.sqf`, `fn_theaterSurvey*.sqf`
- `config.cpp` (CfgFunctions)
- Extension : `mod/UptoDate/COMSPECExtension/Extension.cs` (`Geo.Ingest` déjà présent)

## Fichiers site (référence)
- `app/Controllers/Api/AtakGeoNetworkApiController.php`
- `AtakTerrainApiController.php`, `AtakSceneApiController.php`, `AtakWaypointApiController.php`
- `public/assets/js/atak-geo-live.js`, `atak-route-planner.js`, `atak-terrain3d-premium.js`, `map/atak-c2-bridge.js`

## Critères d’acceptation
1. Après liaison + modules lieux/routes ON → `geo_ready:true`.
2. ATAK web : Villes/Routes peuplés ; itinéraire `plan_mode:"ROAD"`.
3. Waypoint atteint → `reached` en base.
4. Relief 3D sans spam 401.

## Hors scope
Enrôlement / register / migrations VPS (ops : `php run-migrations.php`).
