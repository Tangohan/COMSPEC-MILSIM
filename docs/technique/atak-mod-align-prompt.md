# Prompt — Aligner le mod Overwatch sur les payloads ATAK du site

> Copier-coller ce prompt pour un agent / développeur mod.  
> État Athena (site) en avance : geo live, route A*, 3D premium, C2 v2, waypoints GPS.  
> **Vague complète (GPS marqueurs, zones `/api/atak/zones`, rebuild 1.4.94, backlog)** : voir [`atak-mod-updates-necessaires-prompt.md`](atak-mod-updates-necessaires-prompt.md).

## Mission

Aligner le pack Arma **comspec-overwatch-addons/connect** (+ extension `COMSPECExtension`) sur les endpoints déjà ingérés par Athena (`/public/atak`).

## Priorités

### P0 — Geo network en mission ✅ (branch `cursor/atak-mod-geo-gps-align-fa98`)
- Déclencher `comspec_overwatch_connect_fnc_sampleGeoNetwork` (ACE + relevé théâtre Zeus).
- Modules pont `geo_places` / `geo_roads`.
- Payload `POST /api/atak/geo/ingest` :
  - places : `id`, `type` (CITY|TOWN|VILLAGE|LANDMARK|OTHER), `name`, `x`, `y`, `z?`, `radius_m?`
  - roads : `id`, `ax`, `ay`, `bx`, `by`, `class` (HIGHWAY|PRIMARY|SECONDARY|TRACK|OTHER), `one_way?`
- Vérifier `GET /api/atak/geo/coverage?mapId=` → `geo_ready`.

### P1 — GPS / waypoints ✅ (même branch)
- Extension `GetWaypoints` → `GET /api/atak/waypoints?reached=0`.
- Extension `MarkWaypointReached` → `POST /api/atak/waypoints/{id}/reached`.
- SQF `fn_pollGpsNavigation` (boucle sync, module `gps_navigation`).
- Enrichir `POST /api/atak/position` `extra` : `eta_seconds`, `distance_to_destination_m`, `active_route_id`, `active_waypoint_id`.

### P2 — Qualité C2 / 3D
- Relevé théâtre (pas seulement AO 4 km) pour heights 3D.
- `platform` / `affiliation` stables pour `atak-c2-bridge.js`
  (`FIXED_WING` → AIR, APC/IFV/TRUCK → VEHICLE, affiliation normalisée `friend|hostile|neutral|unknown`).

## Fichiers mod
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_sampleGeoNetwork.sqf`
- `fn_pollGpsNavigation.sqf`, `fn_updatePosition.sqf`, `fn_startSyncLoops.sqf`
- `fn_initACE.sqf`, `fn_sampleTheater.sqf`
- `config.cpp` (CfgFunctions)
- Extension : `mod/UptoDate/COMSPECExtension/Extension.cs` (`Geo.Ingest`, `GetWaypoints`, `MarkWaypointReached`)

## Fichiers site (référence)
- `app/Controllers/Api/AtakGeoNetworkApiController.php`
- `AtakTerrainApiController.php`, `AtakSceneApiController.php`, `AtakWaypointApiController.php`
- `public/assets/js/atak-geo-live.js`, `atak-route-planner.js`, `atak-terrain3d-premium.js`, `map/atak-c2-bridge.js`

## Critères d’acceptation
1. Après liaison + modules lieux/routes ON → `geo_ready:true`.
2. ATAK web : Villes/Routes peuplés ; itinéraire `plan_mode:"ROAD"`.
3. Waypoint atteint (distance ≤ radius) → `reached` en base + annonce jeu.
4. Position enrichie avec ETA quand un waypoint actif existe.
5. Relief 3D sans spam 401.

## Ops
- Migrations VPS : `php run-migrations.php` (geo network + phone pairing).
- Republier le pack Overwatch + recompiler `COMSPECExtension` après merge.

## Hors scope
Enrôlement / register.
