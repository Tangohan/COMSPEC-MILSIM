# Prompt — Mettre en place dans le mod toutes les updates Athena nécessaires

> **Copier-coller** ce prompt pour un agent / développeur mod.  
> Objectif : aligner le pack **COMSPEC Overwatch** (`mod/UptoDate`) sur tout ce que le **portail Athena** a déjà livré et que le jeu doit consommer, afficher ou déclencher.  
> Prompt précédent (geo / GPS partiel) : [`atak-mod-align-prompt.md`](atak-mod-align-prompt.md).

---

## Mission

Le portail Athena est en avance sur plusieurs contrats API ATAK. Une partie est déjà branchée dans les sources du mod (`connect` + `COMSPECExtension`), une partie est incomplète (surtout le **guidage GPS visible**), une partie utilise encore d’**anciens endpoints**, et de nombreux correctifs sont dans les sources (**connect 1.4.94**) sans être encore publiés côté changelog pack Workshop (dernier journal public ~**1.4.86**).

Tu travailles **uniquement dans** `mod/UptoDate/` (sources SQF/CPP + extension C# + docs pack + changelog). Tu ne modifies le portail PHP/JS que si un contrat API est vraiment cassé ou ambigu — et dans ce cas tu le documentes, tu ne « inventes » pas un second contrat.

Références obligatoires avant de coder :

| Doc | Rôle |
|-----|------|
| `docs/AUDIT-FEATURES-ATAK-MOD.md` | Ce qui est livré côté portail vs ce qui reste côté mod |
| `docs/NOUVELLES-FEATURES-ATAK-MOD.md` | Intention produit (waypoints, zones, QRF, MEDEVAC…) |
| `CHANGELOG-ATAK.md` | Historique joint portail / Overwatch / extension |
| `docs/technique/atak-mod-align-prompt.md` | Geo network + GPS (P0/P1 déjà posés) |
| `mod/UptoDate/@COMSPECOverwatch/CHANGELOG.md` | Journal opérateur du pack |
| `docs/bugs/2026-08-*.md` (statut « à reconstruire ») | Correctifs sources non encore rebuild |

---

## État des lieux (ne pas refaire ce qui marche)

### Déjà en place dans les sources (à vérifier, pas à réécrire)

- **Geo network** : `fn_sampleGeoNetwork.sqf`, `Geo.Ingest`, modules `geo_places` / `geo_roads`, `POST /api/atak/geo/ingest`.
- **GPS poll + reached** : `GetWaypoints`, `MarkWaypointReached`, `fn_pollGpsNavigation.sqf` (boucle dans `fn_startSyncLoops.sqf`), enrichissement `COMSPEC_GpsNav*` pour `POST /api/atak/position`.
- **Rapports / MEDEVAC / QRF / véhicules / IFF / météo / charges TOC** : fonctions SQF + méthodes extension correspondantes.
- **Wardrobes ACE Arsenal** : push / pull / overlay (`fn_arsenal*`, menus ACE).
- **Correctifs récents en sources** (ex. PANIC EAGLE_DOWN inconscient/KIA, liaison différée, ACE actions, relevé théâtre, occupants véhicule, IA ennemies masquées, etc.) — version `connect` **1.4.94**.

### Écarts confirmés à traiter

1. **Guidage GPS incomplet** — le poll marque `reached` et calcule ETA, mais **ne crée pas** les marqueurs locaux numérotés ni la ligne d’itinéraire (annoncé dans `CHANGELOG-ATAK.md` 1.2.2 et l’audit : « marqueurs numérotés en jeu »). L’extension renvoie déjà `sequence` en 8ᵉ colonne ; le SQF l’ignore aujourd’hui (il n’exige que 7 colonnes).
2. **Zones tactiques portail** — l’API moderne est `/api/atak/zones` (+ `alerts`, `check-position`). L’extension synchronise encore `DangerZones.Sync` via l’ancien `/api/danger-zones`. Il faut consommer le contrat ATAK zones (types LZ/DZ/objectif/danger/no-go…) sans casser les zones roleplay déjà synchronisées (`fn_syncRoleplayZonesFromPortal`).
3. **Qualité C2 / 3D** (reste du prompt geo) — `platform` / `affiliation` stables pour le pont C2 ; relevé théâtre cohérent pour le relief 3D (pas seulement AO 4 km quand le module théâtre est demandé).
4. **Rebuild + journal pack** — sources à 1.4.94 vs changelog pack arrêté plus tôt : rebuild PBO + DLL, bump versions affichées, entrées changelog opérateur pour tout ce qui est déjà corrigé en sources et pas encore publié.
5. **Hors vague immédiate (documenter, ne pas démarrer sauf demande)** — mode observateur caméra, demandes de tir d’artillerie (API portail absente), pilotage UAV d’itinéraire (données waypoints ok, pilotage jeu non). Voir audit features 7 / 10 / 14.

---

## Priorités d’exécution

### P0 — Rebuild et publication cohérente

1. Vérifier versions dans `Sources/.../connect/config.cpp` (et addons liés) vs `mod.cpp` / hub.
2. Rebuild : `mod/UptoDate/build_mod.bat` puis extension `COMSPECExtension` ; packager avec `workshop-pack.ps1` si le flux habituel le demande.
3. Mettre à jour `mod/UptoDate/@COMSPECOverwatch/CHANGELOG.md` (et miroir docs pack) pour couvrir **1.4.87 → version cible** en langage opérateur (pas de jargon de fichiers).
4. Critère : un joueur qui charge le nouveau pack voit la version attendue ; les bugs « pack à reconstruire » listés dans `docs/bugs/` passent en « corrigé (pack publié) » ou équivalent.

### P1 — Guidage GPS visible (marqueurs numérotés + itinéraire)

**Contrat API (déjà livré portail)**  
- `GET /api/atak/waypoints?mapId=&reached=0`  
- `POST /api/atak/waypoints/{id}/reached` `{ "reached": true|false, "reached_by_callsign"?: … }`  
- `GET /api/atak/waypoint-routes/{id}` expose `next_waypoint` (utile si tu enrichis le poll)

**Extension** (déjà là, à respecter)  
Lignes `GetWaypoints` :  
`id \t route_id \t label \t pos_x \t pos_y \t radius_m \t reached \t sequence`

**À faire côté SQF**

1. Étendre `fn_pollGpsNavigation.sqf` (ou fonction dédiée appelée par la boucle) pour :
   - créer / mettre à jour des **marqueurs locaux** numérotés (`sequence` ou rang) pour chaque waypoint non atteint de l’itinéraire actif ;
   - tracer une **polyline** (ou marqueurs POLYLINE) entre les points dans l’ordre ;
   - supprimer / griser les marqueurs des points `reached` ;
   - garder le comportement actuel : `MarkWaypointReached` si distance ≤ `radius_m`, annonce « Point GPS atteint », variables `COMSPEC_GpsNav*`.
2. Ne spam pas `createMarker` : upsert par nom stable (`COMSPEC_GPS_WP_<id>`).
3. Respecter `canTransmit`, module `gps_navigation`, et la dégradation liaison existante.
4. Optionnel UX : hint direction / distance vers le prochain point (déjà partiellement via ETA).

**Critères d’acceptation**

- Itinéraire créé sur ATAK web → en jeu, marqueurs 1…N visibles après liaison + module GPS ON.  
- Approche du point → `reached` en base + marqueur mis à jour + annonce.  
- `POST /api/atak/position` continue d’embarquer `eta_seconds`, `distance_to_destination_m`, `active_route_id`, `active_waypoint_id` quand un WP est actif.  
- Aucune erreur SQF si la 8ᵉ colonne `sequence` est absente (défaut = ordre de réception).

### P2 — Zones tactiques Athena (`/api/atak/zones`)

**Contrat**

- `GET /api/atak/zones`  
- `GET /api/atak/zones/alerts`  
- `POST /api/atak/zones/check-position`  
- Types attendus côté produit : LZ, DZ, Objective, Danger, No-Go, Extract, Rally (voir `NOUVELLES-FEATURES-ATAK-MOD.md` §2)

**À faire**

1. Faire évoluer `DangerZones.Sync` (ou ajouter `TacticalZones.Sync`) pour lire **`/api/atak/zones`**, avec simplification tabulaire SQF-friendly (comme les autres polls).
2. Brancher réception → stockage mission (`COMSPEC_DangerZones` ou structure élargie) → `fn_checkPlayerInDangerZone` / alertes sonores / marqueurs locaux.
3. Conserver `/api/danger-zones` en secours **uniquement** si le nouvel endpoint échoue (dégradation), ou migrer proprement et documenter la bascule.
4. Ne pas confondre avec les **zones roleplay** (brouillage / couverture) déjà synchronisées depuis le portail.

**Critères**

- Zone danger posée sur le web → visible / détectée en jeu après sync.  
- Entrée dans la zone → alerte locale (comportement déjà prévu par `fn_warnDangerZoneEntry`).  
- Pas de double application avec une zone roleplay du même polygone.

### P3 — Qualité C2 / relief (suite align geo)

1. `platform` / `affiliation` stables pour `atak-c2-bridge.js` :  
   `FIXED_WING` → AIR ; APC/IFV/TRUCK → VEHICLE ; affiliation normalisée `friend|hostile|neutral|unknown`.
2. Quand le relevé théâtre est demandé (Zeus / module), envoyer des heights utiles au 3D sans spam 401 (gates auth + backoff déjà présents — ne pas les casser).
3. Vérifier `GET /api/atak/geo/coverage?mapId=` → `geo_ready` après ingest lieux/routes.

### P4 — Backlog produit (ne pas implémenter dans cette vague sauf ordre explicite)

| Item | Pourquoi attendre |
|------|-------------------|
| Mode observateur / capture caméra commandée | Cœur SQF + upload ; portail sans producteur inutile |
| Demandes de tir artillerie / mortiers | API portail absente (audit feature 10) |
| Pilotage UAV d’itinéraire | Waypoints fournissent les données ; pilotage reste jeu |
| Frise timeline web | Travail portail, pas mod |

---

## Fichiers mod (points d’entrée)

```
mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_pollGpsNavigation.sqf
mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_startSyncLoops.sqf
mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_updatePosition.sqf
mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_receiveDangerZone.sqf
mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_checkPlayerInDangerZone.sqf
mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_sampleGeoNetwork.sqf
mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_sampleTheater.sqf
mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_bftPlatform.sqf
mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp
mod/UptoDate/COMSPECExtension/Extension.cs
mod/UptoDate/@COMSPECOverwatch/CHANGELOG.md
mod/UptoDate/build_mod.bat
mod/UptoDate/workshop-pack.ps1
```

## Fichiers site (référence lecture seule)

```
app/Controllers/Api/AtakWaypointApiController.php
app/Controllers/Api/AtakApiController.php          # zones, vehicles, qrf, medevac…
app/Controllers/Api/AtakGeoNetworkApiController.php
routes/web.php                                    # /api/atak/waypoints*, /api/atak/zones*
docs/AUDIT-FEATURES-ATAK-MOD.md
CHANGELOG-ATAK.md
```

---

## Contraintes techniques

- **SQF** : pas de JSON lourd côté jeu ; s’appuyer sur les réponses tabulées / simplifiées de l’extension.
- **Liaison** : toujours passer par `canTransmit` / profils réseau ; ne pas marteler en 401/503 (backoff existant).
- **Modules CBA** : respecter les interrupteurs `gps_navigation`, geo, etc.
- **Compat** : ne pas casser ACE, IceMan/ATAK Enhanced, SSE, wardrobes Arsenal.
- **Versions** : bump cohérent `version` / `versionStr` / changelog / hub.
- **Langue opérateur** dans le changelog pack (français clair, comme les entrées 1.4.7x–1.4.8x).

---

## Plan de vérification

1. **Sans jeu** : build PBO + compile extension sans erreur ; grep que `GetWaypoints` documente bien 8 colonnes et que le SQF lit `sequence`.
2. **Session liée** : module GPS ON → marqueurs d’un itinéraire web ; franchissement → reached ; ETA dans le journal / payload position.
3. **Zones** : une danger zone web déclenche l’alerte entrée ; une LZ n’applique pas les PP roleplay.
4. **Régression** : position, photos, PANIC médical, charges TOC, wardrobes, geo ingest, relief autour de l’équipe.
5. **Docs** : mettre à jour ce prompt (cases ✅) et, si besoin, une ligne dans `CHANGELOG-ATAK.md` « pack Overwatch x.y.z — guidage GPS + zones ATAK ».

---

## Ops

- Migrations VPS déjà posées pour waypoints / zones / geo : `php run-migrations.php` si une base de test est vide.
- Après merge : republier le pack Workshop + DLL ; demander une **relance complète d’Arma** (pas seulement la mission).
- Ne pas toucher enrôlement / register / RH portail.

## Hors scope

- Refonte UI IceMan / cTab hors pont existant.
- Features audit 7 / 10 / 14 (observateur, artillerie, UAV).
- Travail purement web (frise timeline, ombrage carte) sans impact jeu.
