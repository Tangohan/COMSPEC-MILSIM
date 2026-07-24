# Guide d'intégration API - Nouvelles features ATAK

**Version** : 1.0  
**Date** : 24 juillet 2026  
**Phases implémentées** : Phase 1 & 2 (6 features)

---

## Vue d'ensemble

Ce guide documente les 31 nouveaux endpoints API REST pour les features ATAK Phases 1 & 2. Toutes les API utilisent l'authentification existante COMSPEC et le format JSON.

**Base URL** : `https://athena.comspec.fr/api/atak`

**Authentification** : Header `X-ATAK-Token` ou authentification session standard

---

## Phase 1 : Fondations coordination

### 1. Rapports tactiques (SPOTREP, SITREP, SALUTE, CONTACT)

#### Liste des rapports
```http
GET /api/atak/reports
```

**Query parameters** :
- `report_type` : SPOTREP | SITREP | SALUTE | CONTACT | OTHER
- `priority` : ROUTINE | PRIORITY | IMMEDIATE | FLASH
- `status` : DRAFT | SUBMITTED | ACKNOWLEDGED | ACTIONED | ARCHIVED
- `submitter_steam_id` : Steam ID émetteur
- `date_from` : Date début (YYYY-MM-DD HH:MM:SS)
- `date_to` : Date fin
- `limit` : Nombre max résultats (défaut: 100)
- `offset` : Pagination

**Réponse** :
```json
{
  "reports": [
    {
      "id": 123,
      "report_type": "SPOTREP",
      "report_number": "SPOTREP-20260724-001",
      "priority": "IMMEDIATE",
      "status": "SUBMITTED",
      "submitter_callsign": "ALPHA-1",
      "pos_x": 15234.56,
      "pos_y": 8765.43,
      "grid_reference": "123456",
      "summary": "Contact ennemi observé",
      "details": "Section ennemie 10 hommes direction nord",
      "structured_data": {
        "size": "SQUAD",
        "activity": "MOVING",
        "location": "Grid 123456",
        "unit": "Unknown",
        "time": "2026-07-24T11:00:00Z",
        "equipment": "Small arms, RPG"
      },
      "report_timestamp": "2026-07-24 11:00:00",
      "created_at": "2026-07-24 11:00:00"
    }
  ],
  "count": 1
}
```

#### Créer un rapport
```http
POST /api/atak/reports
```

**Body** :
```json
{
  "report_type": "SPOTREP",
  "priority": "IMMEDIATE",
  "submitter_callsign": "ALPHA-1",
  "pos_x": 15234.56,
  "pos_y": 8765.43,
  "grid_reference": "123456",
  "summary": "Contact ennemi",
  "details": "Section ennemie 10 hommes",
  "structured_data": {
    "size": "SQUAD",
    "activity": "MOVING",
    "location": "Grid 123456",
    "unit": "Unknown",
    "time": "2026-07-24T11:00:00Z",
    "equipment": "Small arms, RPG"
  },
  "visibility": "ALL"
}
```

**Réponse** : Rapport créé avec `id` et `report_number` généré

#### Détail d'un rapport
```http
GET /api/atak/reports/{id}
```

#### Acquitter un rapport
```http
POST /api/atak/reports/{id}/acknowledge
```

---

### 2. Points d'Intérêt (POI) tactiques

#### Liste des POI
```http
GET /api/atak/poi
```

**Query parameters** :
- `category` : OBJECTIVE | BUILDING | CACHE | ENEMY_POSITION | HVT | etc.
- `affiliation` : FRIENDLY | ENEMY | NEUTRAL | UNKNOWN
- `status` : ACTIVE | NEUTRALIZED | DESTROYED | ABANDONED | OCCUPIED
- `threat_level` : NONE | LOW | MEDIUM | HIGH | CRITICAL
- `is_visible` : true | false
- `limit` : Nombre max résultats (défaut: 200)
- `offset` : Pagination

**Réponse** :
```json
{
  "pois": [
    {
      "id": 456,
      "poi_name": "Cache d'armes suspecte",
      "poi_code": "CACHE-01",
      "category": "CACHE",
      "affiliation": "ENEMY",
      "certainty": "PROBABLE",
      "pos_x": 12345.67,
      "pos_y": 7890.12,
      "grid_reference": "098765",
      "description": "Bâtiment abandonné, activité suspecte observée",
      "threat_level": "MEDIUM",
      "status": "ACTIVE",
      "source_type": "VISUAL",
      "source_reliability": "USUALLY_RELIABLE",
      "reported_by_callsign": "RECON-2",
      "last_observed_at": "2026-07-24 10:30:00",
      "photo_count": 2,
      "observation_count": 3,
      "created_at": "2026-07-24 09:00:00"
    }
  ],
  "count": 1
}
```

#### Créer un POI
```http
POST /api/atak/poi
```

**Body** :
```json
{
  "poi_name": "Cache d'armes suspecte",
  "poi_code": "CACHE-01",
  "category": "CACHE",
  "affiliation": "ENEMY",
  "certainty": "PROBABLE",
  "pos_x": 12345.67,
  "pos_y": 7890.12,
  "grid_reference": "098765",
  "description": "Bâtiment abandonné",
  "threat_level": "MEDIUM",
  "source_type": "VISUAL",
  "source_reliability": "USUALLY_RELIABLE",
  "reported_by_callsign": "RECON-2",
  "visibility_level": "PUBLIC"
}
```

#### Mettre à jour un POI
```http
PUT /api/atak/poi/{id}
PATCH /api/atak/poi/{id}
```

**Body** : Champs à mettre à jour (ex: `status`, `certainty`, `threat_level`)

---

### 3. Zones tactiques (LZ, DZ, Objectives, Danger Zones)

#### Liste des zones
```http
GET /api/atak/zones
```

**Query parameters** :
- `zone_type` : LZ | DZ | OBJECTIVE | DANGER_ZONE | NO_GO_AREA | etc.
- `status` : PLANNED | ACTIVE | INACTIVE | COMPLETED | CANCELLED
- `is_visible` : true | false
- `only_active` : true (filtre zones actuellement actives selon temporalité)
- `limit` : Nombre max résultats (défaut: 200)
- `offset` : Pagination

**Réponse** :
```json
{
  "zones": [
    {
      "id": 789,
      "zone_name": "LZ Alpha",
      "zone_code": "LZ-ALPHA",
      "zone_type": "LZ",
      "geometry_type": "CIRCLE",
      "center_x": 10000.0,
      "center_y": 10000.0,
      "radius": 150.0,
      "status": "ACTIVE",
      "priority": "HIGH",
      "threat_level": "LOW",
      "active_from": "2026-07-24 12:00:00",
      "active_until": "2026-07-24 14:00:00",
      "alert_on_entry": true,
      "alert_message": "Vous entrez dans LZ Alpha",
      "alert_sound": "ATAK_ZONE_ENTRY",
      "fill_color": "#0088ff",
      "opacity": 0.30,
      "is_currently_active": true,
      "alert_count": 3,
      "unacknowledged_alert_count": 1
    }
  ],
  "count": 1
}
```

#### Créer une zone
```http
POST /api/atak/zones
```

**Body (exemple cercle)** :
```json
{
  "zone_name": "LZ Alpha",
  "zone_code": "LZ-ALPHA",
  "zone_type": "LZ",
  "geometry_type": "CIRCLE",
  "center_x": 10000.0,
  "center_y": 10000.0,
  "radius": 150.0,
  "status": "ACTIVE",
  "priority": "HIGH",
  "alert_on_entry": true,
  "alert_message": "Vous entrez dans LZ Alpha",
  "active_from": "2026-07-24 12:00:00",
  "active_until": "2026-07-24 14:00:00"
}
```

**Body (exemple polygone)** :
```json
{
  "zone_name": "Zone objective Bravo",
  "zone_type": "OBJECTIVE",
  "geometry_type": "POLYGON",
  "center_x": 12000.0,
  "center_y": 12000.0,
  "polygon_points": [
    [11900, 11900],
    [12100, 11900],
    [12100, 12100],
    [11900, 12100]
  ],
  "status": "ACTIVE",
  "alert_on_entry": true
}
```

#### Vérifier position dans zones
```http
POST /api/atak/zones/check-position
```

**Body** :
```json
{
  "pos_x": 10050.0,
  "pos_y": 10050.0,
  "callsign": "ALPHA-1",
  "steam_id": "76561198012345678"
}
```

**Réponse** :
```json
{
  "zones": [
    {
      "id": 789,
      "zone_name": "LZ Alpha",
      "zone_type": "LZ",
      "alert_on_entry": true
    }
  ],
  "alerts": [
    {
      "zone_id": 789,
      "zone_name": "LZ Alpha",
      "zone_type": "LZ",
      "alert_message": "Vous entrez dans LZ Alpha",
      "alert_sound": "ATAK_ZONE_ENTRY",
      "alert_id": 1234
    }
  ],
  "count": 1
}
```

#### Liste des alertes non acquittées
```http
GET /api/atak/zones/alerts
```

---

## Phase 2 : Capacités spécialisées

### 4. MEDEVAC 9-Line avec triage TCCC

#### Liste des MEDEVAC
```http
GET /api/atak/medevac
```

**Query parameters** :
- `status` : REQUESTED | ACKNOWLEDGED | ASSIGNED | INBOUND | ON_SITE | EVACUATING | COMPLETED | CANCELLED
- `priority` : URGENT | PRIORITY | ROUTINE | CONVENIENCE
- `golden_hour_critical` : true | false (filtre T1 avec golden hour expiré)
- `only_active` : true
- `limit`, `offset` : Pagination

**Réponse** :
```json
{
  "medevacs": [
    {
      "id": 101,
      "medevac_number": "MEDEVAC-20260724-001",
      "priority": "URGENT",
      "status": "ASSIGNED",
      "pickup_grid": "123456",
      "pickup_pos_x": 15000.0,
      "pickup_pos_y": 8000.0,
      "radio_frequency": "30.0",
      "radio_callsign": "ALPHA-1",
      "patients_t1_urgent": 1,
      "patients_t2_urgent": 0,
      "patients_t3_delayed": 2,
      "patients_t4_expectant": 0,
      "total_patients": 3,
      "patients_litter": 2,
      "patients_ambulatory": 1,
      "security_status": "POSSIBLE_ENEMY",
      "lz_marking": "SMOKE",
      "lz_marking_color": "GREEN",
      "requested_by_callsign": "MEDIC-1",
      "requested_at": "2026-07-24 11:15:00",
      "golden_hour_expires_at": "2026-07-24 12:15:00",
      "is_golden_hour_critical": false,
      "golden_hour_status": "WARNING",
      "golden_hour_minutes_remaining": 35,
      "assigned_asset_callsign": "DUSTOFF-1",
      "eta": "2026-07-24 11:40:00",
      "minutes_since_request": 13,
      "actual_patient_count": 3
    }
  ],
  "count": 1
}
```

#### Créer une MEDEVAC
```http
POST /api/atak/medevac
```

**Body (9-Line complet)** :
```json
{
  "priority": "URGENT",
  "pickup_grid": "123456",
  "pickup_pos_x": 15000.0,
  "pickup_pos_y": 8000.0,
  "pickup_elevation": 150,
  "radio_frequency": "30.0",
  "radio_callsign": "ALPHA-1",
  "patients_t1_urgent": 1,
  "patients_t2_urgent": 0,
  "patients_t3_delayed": 2,
  "patients_t4_expectant": 0,
  "equipment_needed": ["hoist", "ventilator"],
  "patients_litter": 2,
  "patients_ambulatory": 1,
  "security_status": "POSSIBLE_ENEMY",
  "enemy_description": "Tirs sporadiques 500m nord",
  "lz_marking": "SMOKE",
  "lz_marking_color": "GREEN",
  "patient_nationality": "FRIENDLY",
  "patient_status": "MILITARY",
  "nbc_contamination": "NONE",
  "terrain_description": "Champ ouvert, pente douce",
  "approach_direction": "Sud-Est recommandé",
  "remarks": "LZ sécurisée par section Bravo",
  "requested_by_callsign": "MEDIC-1"
}
```

#### Détail MEDEVAC avec patients
```http
GET /api/atak/medevac/{id}
```

**Réponse** : MEDEVAC + array `patients` avec détails médicaux

#### Mettre à jour statut
```http
PATCH /api/atak/medevac/{id}/status
```

**Body** :
```json
{
  "status": "INBOUND",
  "message": "Hélico décollé, ETA 10min"
}
```

#### Assigner un asset
```http
POST /api/atak/medevac/{id}/assign
```

**Body** :
```json
{
  "asset_callsign": "DUSTOFF-1"
}
```

#### Ajouter un patient
```http
POST /api/atak/medevac/{id}/patients
```

**Body** :
```json
{
  "patient_callsign": "ALPHA-3",
  "triage_category": "T1",
  "triaged_by_callsign": "MEDIC-1",
  "consciousness": "VERBAL",
  "breathing": "ABNORMAL",
  "circulation": "COMPROMISED",
  "primary_injury": "Blessure par éclat thorax",
  "injuries": [
    {"location": "thorax", "type": "shrapnel", "severity": "critical"}
  ],
  "treatments_given": [
    "tourniquet", "hemostatic_dressing", "chest_seal"
  ],
  "medications_given": [
    {"name": "morphine", "dose": "10mg", "time": "11:20"}
  ],
  "is_stabilized": true,
  "requires_litter": true,
  "can_walk": false
}
```

---

### 5. QRF (Quick Reaction Force)

#### Liste des QRF
```http
GET /api/atak/qrf
```

**Query parameters** :
- `status` : REQUESTED | ACKNOWLEDGED | QRF_ASSIGNED | QRF_ENROUTE | QRF_ENGAGED | SITUATION_STABILIZED | COMPLETED | CANCELLED
- `priority` : ROUTINE | PRIORITY | IMMEDIATE | FLASH
- `only_active` : true
- `limit`, `offset` : Pagination

**Réponse** :
```json
{
  "qrfs": [
    {
      "id": 202,
      "qrf_number": "QRF-20260724-001",
      "priority": "IMMEDIATE",
      "status": "QRF_ENROUTE",
      "contact_pos_x": 18000.0,
      "contact_pos_y": 9000.0,
      "grid_reference": "890123",
      "threat_type": "AMBUSH",
      "threat_description": "Embuscade sur convoi, tirs nourris",
      "enemy_strength": "SQUAD",
      "requesting_unit": "Convoi Charlie",
      "requesting_callsign": "CHARLIE-6",
      "friendly_strength": 12,
      "friendly_casualties": 2,
      "friendly_status": "PINNED",
      "support_requested": ["infantry", "cas"],
      "assigned_qrf_unit": "Réserve Bravo",
      "assigned_qrf_callsign": "BRAVO-QRF",
      "qrf_current_pos_x": 16500.0,
      "qrf_current_pos_y": 8500.0,
      "qrf_eta": "2026-07-24 11:35:00",
      "distance_to_contact": 1802.78,
      "minutes_since_request": 8,
      "urgency_status": "OK",
      "sitrep_update_count": 3,
      "waypoints_reached": 2,
      "waypoints_total": 5
    }
  ],
  "count": 1
}
```

#### Créer une demande QRF
```http
POST /api/atak/qrf
```

**Body** :
```json
{
  "priority": "IMMEDIATE",
  "contact_pos_x": 18000.0,
  "contact_pos_y": 9000.0,
  "grid_reference": "890123",
  "threat_type": "AMBUSH",
  "threat_description": "Embuscade convoi, tirs nourris depuis bâtiments",
  "enemy_strength": "SQUAD",
  "enemy_disposition": "Positions fortifiées, HMG visible",
  "requesting_unit": "Convoi Charlie",
  "requesting_callsign": "CHARLIE-6",
  "friendly_strength": 12,
  "friendly_casualties": 2,
  "friendly_status": "PINNED",
  "support_requested": ["infantry", "cas", "medevac"],
  "enemy_weapons": ["AK", "PKM", "RPG"],
  "terrain_description": "Zone urbaine, rues étroites",
  "best_approach": "Flanc ouest par champs",
  "hazards": "IED possibles sur route principale"
}
```

#### Assigner une QRF
```http
POST /api/atak/qrf/{id}/assign
```

**Body** :
```json
{
  "qrf_unit": "Réserve Bravo",
  "qrf_callsign": "BRAVO-QRF"
}
```

#### Mettre à jour position QRF
```http
POST /api/atak/qrf/{id}/position
```

**Body** :
```json
{
  "pos_x": 16500.0,
  "pos_y": 8500.0,
  "eta": "2026-07-24 11:35:00"
}
```

**À appeler périodiquement** (ex: toutes les 30s) pendant déplacement QRF

#### Ajouter un SITREP
```http
POST /api/atak/qrf/{id}/sitrep
```

**Body** :
```json
{
  "update_type": "SITUATION_UPDATE",
  "update_message": "QRF à 1km de la zone, ennemi commence à se replier",
  "pos_x": 17000.0,
  "pos_y": 8700.0,
  "is_from_qrf": true
}
```

Types : `STATUS_CHANGE`, `POSITION_UPDATE`, `SITUATION_UPDATE`, `CONTACT_REPORT`

---

### 6. Véhicules et assets lourds

#### Liste des véhicules
```http
GET /api/atak/vehicles
```

**Query parameters** :
- `vehicle_class` : LIGHT_VEHICLE | TRUCK | APC | IFV | TANK | ARTILLERY | HELICOPTER | FIXED_WING | UAV | BOAT
- `side` : BLUFOR | OPFOR | INDEPENDENT | CIVILIAN
- `status` : OPERATIONAL | DAMAGED | IMMOBILIZED | DESTROYED | ABANDONED
- `fuel_critical` : true (filtre véhicules < 20% fuel)
- `damaged` : true (filtre véhicules endommagés)
- `limit`, `offset` : Pagination

**Réponse** :
```json
{
  "vehicles": [
    {
      "id": 303,
      "vehicle_callsign": "ARMORED-1",
      "vehicle_name": "Bradley IFV Alpha",
      "vehicle_class": "IFV",
      "vehicle_type": "M2A3 Bradley",
      "side": "BLUFOR",
      "unit_assigned": "Alpha Company",
      "crew_commander_callsign": "ALPHA-6",
      "crew_count": 3,
      "crew_max": 3,
      "passenger_count": 6,
      "passenger_max": 7,
      "pos_x": 14000.0,
      "pos_y": 11000.0,
      "heading": 270.0,
      "speed": 45.5,
      "status": "OPERATIONAL",
      "fuel_percent": 65.0,
      "ammo_percent": 80.0,
      "engine_health": 95.0,
      "hull_health": 100.0,
      "tracks_wheels_health": 100.0,
      "turret_health": 100.0,
      "is_fuel_critical": false,
      "is_ammo_critical": false,
      "is_damaged": false,
      "fuel_status_label": "MEDIUM",
      "ammo_status_label": "OK",
      "mission_type": "COMBAT",
      "destination_pos_x": 15000.0,
      "destination_pos_y": 12000.0,
      "distance_to_destination": 1414.21,
      "pending_service_requests": 0,
      "last_seen_at": "2026-07-24 11:28:00",
      "seconds_since_last_update": 15
    }
  ],
  "count": 1
}
```

#### Upsert véhicule (créer ou mettre à jour)
```http
POST /api/atak/vehicles
```

**Body (complet)** :
```json
{
  "vehicle_callsign": "ARMORED-1",
  "vehicle_name": "Bradley IFV Alpha",
  "vehicle_class": "IFV",
  "vehicle_type": "M2A3 Bradley",
  "side": "BLUFOR",
  "crew_commander_callsign": "ALPHA-6",
  "crew_count": 3,
  "passenger_count": 6,
  "pos_x": 14000.0,
  "pos_y": 11000.0,
  "heading": 270.0,
  "speed": 45.5,
  "fuel_percent": 65.0,
  "ammo_percent": 80.0,
  "engine_health": 95.0,
  "hull_health": 100.0,
  "destination_pos_x": 15000.0,
  "destination_pos_y": 12000.0,
  "mission_type": "COMBAT"
}
```

**Body (update position seule)** :
```json
{
  "vehicle_callsign": "ARMORED-1",
  "pos_x": 14050.0,
  "pos_y": 11020.0,
  "heading": 272.0,
  "speed": 48.0
}
```

**Note** : Upsert par `vehicle_callsign` - crée si n'existe pas, met à jour sinon

#### Demander un service
```http
POST /api/atak/vehicles/{id}/service
```

**Body** :
```json
{
  "request_type": "REFUEL",
  "priority": "HIGH",
  "request_details": "Carburant critique, besoin ravitaillement urgent",
  "service_pos_x": 14000.0,
  "service_pos_y": 11000.0,
  "requested_by_callsign": "ARMORED-1"
}
```

Types : `REFUEL`, `REARM`, `REPAIR`, `MAINTENANCE`, `RECOVERY`

#### Liste demandes service en attente
```http
GET /api/atak/vehicles/service-requests
```

**Réponse** :
```json
{
  "service_requests": [
    {
      "id": 404,
      "vehicle_tracking_id": 303,
      "vehicle_callsign": "ARMORED-1",
      "vehicle_class": "IFV",
      "request_type": "REFUEL",
      "priority": "HIGH",
      "request_details": "Carburant critique",
      "status": "REQUESTED",
      "pos_x": 14000.0,
      "pos_y": 11000.0,
      "requested_at": "2026-07-24 11:25:00"
    }
  ],
  "count": 1
}
```

---

## Intégration mod Arma (SQF)

### Exemples d'appels depuis Arma

#### Soumettre un SPOTREP
```sqf
private _reportData = createHashMap;
_reportData set ["report_type", "SPOTREP"];
_reportData set ["priority", "IMMEDIATE"];
_reportData set ["submitter_callsign", groupId (group player)];
_reportData set ["pos_x", getPosWorld player select 0];
_reportData set ["pos_y", getPosWorld player select 1];
_reportData set ["grid_reference", mapGridPosition player];
_reportData set ["summary", "Contact ennemi observé"];
_reportData set ["details", "Section ennemie 10 hommes direction nord"];

private _structuredData = createHashMap;
_structuredData set ["size", "SQUAD"];
_structuredData set ["activity", "MOVING"];
_structuredData set ["equipment", "Small arms, RPG"];
_reportData set ["structured_data", _structuredData];

// Appel extension C#
private _jsonString = [_reportData] call comspec_overwatch_connect_fnc_hashMapToJson;
private _result = "COMSPECExtension" callExtension ["SubmitReport", [_jsonString]];
```

#### Créer un POI
```sqf
private _poiData = createHashMap;
_poiData set ["poi_name", "Cache d'armes suspecte"];
_poiData set ["category", "CACHE"];
_poiData set ["affiliation", "ENEMY"];
_poiData set ["certainty", "PROBABLE"];
_poiData set ["pos_x", getPosWorld cursorTarget select 0];
_poiData set ["pos_y", getPosWorld cursorTarget select 1];
_poiData set ["description", "Bâtiment abandonné, activité suspecte"];
_poiData set ["threat_level", "MEDIUM"];
_poiData set ["source_type", "VISUAL"];
_poiData set ["reported_by_callsign", name player];

private _jsonString = [_poiData] call comspec_overwatch_connect_fnc_hashMapToJson;
private _result = "COMSPECExtension" callExtension ["SubmitPOI", [_jsonString]];
```

#### Demander MEDEVAC
```sqf
private _medevacData = createHashMap;
_medevacData set ["priority", "URGENT"];
_medevacData set ["pickup_grid", mapGridPosition player];
_medevacData set ["pickup_pos_x", getPosWorld player select 0];
_medevacData set ["pickup_pos_y", getPosWorld player select 1];
_medevacData set ["radio_frequency", "30.0"];
_medevacData set ["radio_callsign", groupId (group player)];
_medevacData set ["patients_t1_urgent", 1];
_medevacData set ["patients_t2_urgent", 0];
_medevacData set ["patients_t3_delayed", 2];
_medevacData set ["security_status", "POSSIBLE_ENEMY"];
_medevacData set ["lz_marking", "SMOKE"];
_medevacData set ["lz_marking_color", "GREEN"];

private _jsonString = [_medevacData] call comspec_overwatch_connect_fnc_hashMapToJson;
private _result = "COMSPECExtension" callExtension ["RequestMEDEVAC", [_jsonString]];
```

#### Update position véhicule
```sqf
// À exécuter périodiquement (ex: toutes les 5s) pour véhicules occupés
if (vehicle player != player) then {
    private _vehicle = vehicle player;
    private _vehicleData = createHashMap;
    _vehicleData set ["vehicle_callsign", getText (configOf _vehicle >> "displayName")];
    _vehicleData set ["pos_x", getPosWorld _vehicle select 0];
    _vehicleData set ["pos_y", getPosWorld _vehicle select 1];
    _vehicleData set ["heading", getDir _vehicle];
    _vehicleData set ["speed", speed _vehicle];
    _vehicleData set ["fuel_percent", fuel _vehicle * 100];
    _vehicleData set ["status", if (canMove _vehicle) then {"OPERATIONAL"} else {"IMMOBILIZED"}];
    
    private _jsonString = [_vehicleData] call comspec_overwatch_connect_fnc_hashMapToJson;
    private _result = "COMSPECExtension" callExtension ["UpdateVehicle", [_jsonString]];
};
```

---

## Intégration interface web (JavaScript)

### Exemples d'appels depuis navigateur

#### Récupérer rapports actifs
```javascript
async function fetchActiveReports() {
    const response = await fetch('/api/atak/reports?status=SUBMITTED&priority=IMMEDIATE', {
        headers: {
            'X-ATAK-Token': atakToken
        }
    });
    const data = await response.json();
    return data.reports;
}
```

#### Acquitter un rapport
```javascript
async function acknowledgeReport(reportId) {
    const response = await fetch(`/api/atak/reports/${reportId}/acknowledge`, {
        method: 'POST',
        headers: {
            'X-ATAK-Token': atakToken,
            'Content-Type': 'application/json'
        }
    });
    return response.json();
}
```

#### Afficher POI sur carte Leaflet
```javascript
async function loadPOIsOnMap(map) {
    const response = await fetch('/api/atak/poi?is_visible=true&status=ACTIVE', {
        headers: { 'X-ATAK-Token': atakToken }
    });
    const data = await response.json();
    
    data.pois.forEach(poi => {
        const iconColor = {
            'FRIENDLY': 'blue',
            'ENEMY': 'red',
            'NEUTRAL': 'gray',
            'UNKNOWN': 'yellow'
        }[poi.affiliation];
        
        L.marker([poi.pos_y, poi.pos_x], {
            icon: createPOIIcon(poi.category, iconColor)
        })
        .bindPopup(`
            <strong>${poi.poi_name}</strong><br>
            ${poi.category} - ${poi.affiliation}<br>
            Certitude: ${poi.certainty}<br>
            Menace: ${poi.threat_level}<br>
            ${poi.description}
        `)
        .addTo(map);
    });
}
```

#### Vérifier position dans zones
```javascript
async function checkPositionInZones(posX, posY, callsign) {
    const response = await fetch('/api/atak/zones/check-position', {
        method: 'POST',
        headers: {
            'X-ATAK-Token': atakToken,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            pos_x: posX,
            pos_y: posY,
            callsign: callsign
        })
    });
    const data = await response.json();
    
    // Déclencher alertes
    data.alerts.forEach(alert => {
        showAlert(alert.alert_message, alert.alert_sound);
    });
    
    return data.zones;
}
```

#### Tracker véhicules sur carte
```javascript
const vehicleMarkers = {};

async function updateVehiclesOnMap(map) {
    const response = await fetch('/api/atak/vehicles?side=BLUFOR', {
        headers: { 'X-ATAK-Token': atakToken }
    });
    const data = await response.json();
    
    data.vehicles.forEach(vehicle => {
        const pos = [vehicle.pos_y, vehicle.pos_x];
        
        if (vehicleMarkers[vehicle.vehicle_callsign]) {
            // Mettre à jour position existante
            vehicleMarkers[vehicle.vehicle_callsign].setLatLng(pos);
            vehicleMarkers[vehicle.vehicle_callsign].setRotationAngle(vehicle.heading);
        } else {
            // Créer nouveau marqueur
            const marker = L.marker(pos, {
                icon: createVehicleIcon(vehicle.vehicle_class),
                rotationAngle: vehicle.heading
            }).bindPopup(`
                <strong>${vehicle.vehicle_callsign}</strong><br>
                ${vehicle.vehicle_type}<br>
                Fuel: ${vehicle.fuel_percent}% 
                ${vehicle.is_fuel_critical ? '⚠️ CRITIQUE' : ''}<br>
                Munitions: ${vehicle.ammo_percent}%<br>
                Vitesse: ${vehicle.speed} km/h
            `);
            marker.addTo(map);
            vehicleMarkers[vehicle.vehicle_callsign] = marker;
        }
    });
}

// Refresh toutes les 5 secondes
setInterval(() => updateVehiclesOnMap(map), 5000);
```

---

## Codes d'erreur courants

- `400 Bad Request` : Paramètres invalides ou manquants
- `401 Unauthorized` : Authentification requise ou token invalide
- `404 Not Found` : Ressource inexistante
- `500 Internal Server Error` : Erreur serveur

**Format erreur** :
```json
{
  "error": "Description de l'erreur"
}
```

---

## Notes techniques

### Performance
- Les endpoints liste supportent pagination via `limit` et `offset`
- Limites par défaut : 100 (rapports), 200 (POI, zones, véhicules)
- Utiliser les filtres pour réduire la charge réseau

### Temps réel
- Polling recommandé : 5-10 secondes pour véhicules, 30s pour autres endpoints
- WebSocket planifié pour Phase 3 (push temps réel)

### Persistance
- Toutes les données sont multi-tenant (isolation par `tenant_id`)
- Context opérationnel (`context_id`) permet plusieurs missions simultanées
- Soft delete sur la plupart des ressources (champ `deleted_at`)

### Historique
- Positions véhicules sauvegardées automatiquement pour replay
- Événements véhicules loggés automatiquement
- Changements statut MEDEVAC/QRF tracés via tables updates

---

## Support et documentation

**Roadmap** : `docs/NOUVELLES-FEATURES-ATAK-MOD.md`  
**Migrations SQL** : `migrations/2026_07_24_00X_*.sql`  
**Repositories** : `app/Repositories/Atak*.php`

Pour questions techniques : Voir code source des repositories pour méthodes avancées et options disponibles.
