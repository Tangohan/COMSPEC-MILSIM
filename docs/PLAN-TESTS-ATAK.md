# 🧪 Plan de tests - Features ATAK Phases 1 & 2

**Version** : 1.0  
**Date** : 24 juillet 2026  
**Objectif** : Valider le bon fonctionnement backend avant intégration frontend

---

## 📋 Tests préliminaires (Déploiement)

### TP-01 : Vérification base de données

**Objectif** : S'assurer que toutes les tables sont créées  
**Prérequis** : Accès MySQL

```sql
-- Vérifier existence tables Phase 1
SHOW TABLES LIKE 'atak_tactical_reports';
SHOW TABLES LIKE 'atak_poi';
SHOW TABLES LIKE 'atak_tactical_zones';

-- Vérifier existence tables Phase 2
SHOW TABLES LIKE 'atak_medevac_requests';
SHOW TABLES LIKE 'atak_qrf_requests';
SHOW TABLES LIKE 'atak_vehicle_tracking';

-- Vérifier vues
SHOW FULL TABLES WHERE Table_type = 'VIEW' AND Tables_in_comspec_milsim LIKE 'v_atak%';

-- Vérifier triggers
SHOW TRIGGERS LIKE 'atak_%';
```

**Résultat attendu** : 15 tables, 5 vues, 4 triggers

**Statut** : [ ] PASS [ ] FAIL

---

### TP-02 : Vérification indexes

**Objectif** : S'assurer que les index stratégiques sont créés

```sql
-- Exemple pour table reports
SHOW INDEX FROM atak_tactical_reports;

-- Vérifier index composites
-- Doit contenir au minimum:
-- - idx_tenant_context (tenant_id, context_id)
-- - idx_status (status)
-- - idx_priority (priority)
-- - idx_submitter (submitter_steam_id)
```

**Résultat attendu** : Index présents pour toutes tables principales

**Statut** : [ ] PASS [ ] FAIL

---

## 🔌 Tests API REST

### Configuration tests
```bash
# Variables à adapter
API_URL="https://athena.comspec.fr/api/atak"
TOKEN="your_token_here"
TENANT_ID=1
CONTEXT_ID=1
```

---

### T1-01 : Liste rapports (vide)

**Endpoint** : `GET /api/atak/reports`

```bash
curl -X GET "$API_URL/reports" \
  -H "X-ATAK-Token: $TOKEN"
```

**Résultat attendu** :
```json
{
  "reports": [],
  "count": 0
}
```

**Statut** : [ ] PASS [ ] FAIL

---

### T1-02 : Créer rapport SPOTREP

**Endpoint** : `POST /api/atak/reports`

```bash
curl -X POST "$API_URL/reports" \
  -H "X-ATAK-Token: $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "report_type": "SPOTREP",
    "priority": "IMMEDIATE",
    "submitter_callsign": "ALPHA-1-TEST",
    "pos_x": 15234.56,
    "pos_y": 8765.43,
    "grid_reference": "152087",
    "summary": "Contact ennemi observé - TEST",
    "details": "Section ennemie 10 hommes direction nord",
    "structured_data": {
      "size": "SQUAD",
      "activity": "MOVING",
      "location": "Grid 152087",
      "unit": "Unknown",
      "equipment": "Small arms, RPG"
    }
  }'
```

**Résultat attendu** :
- Code 201 Created
- JSON avec `id` et `report_number` généré (ex: `SPOTREP-20260724-001`)

**Vérification BDD** :
```sql
SELECT * FROM atak_tactical_reports WHERE report_number LIKE 'SPOTREP-2026%' ORDER BY id DESC LIMIT 1;
```

**Statut** : [ ] PASS [ ] FAIL

---

### T1-03 : Lire rapport créé

**Endpoint** : `GET /api/atak/reports/{id}`

```bash
# Remplacer {ID} par id du rapport créé en T1-02
curl -X GET "$API_URL/reports/{ID}" \
  -H "X-ATAK-Token: $TOKEN"
```

**Résultat attendu** :
- Code 200 OK
- JSON complet du rapport avec tous les champs

**Statut** : [ ] PASS [ ] FAIL

---

### T1-04 : Acquitter rapport

**Endpoint** : `POST /api/atak/reports/{id}/acknowledge`

```bash
curl -X POST "$API_URL/reports/{ID}/acknowledge" \
  -H "X-ATAK-Token: $TOKEN"
```

**Résultat attendu** :
- Code 200 OK
- Champ `status` passé à `ACKNOWLEDGED`

**Vérification BDD** :
```sql
SELECT status FROM atak_tactical_reports WHERE id = {ID};
-- Doit retourner: ACKNOWLEDGED
```

**Statut** : [ ] PASS [ ] FAIL

---

### T2-01 : Créer POI

**Endpoint** : `POST /api/atak/poi`

```bash
curl -X POST "$API_URL/poi" \
  -H "X-ATAK-Token: $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "poi_name": "Cache armes TEST",
    "poi_code": "CACHE-TEST-01",
    "category": "CACHE",
    "affiliation": "ENEMY",
    "certainty": "PROBABLE",
    "pos_x": 12345.67,
    "pos_y": 7890.12,
    "grid_reference": "123078",
    "description": "Bâtiment abandonné, activité suspecte",
    "threat_level": "MEDIUM",
    "source_type": "VISUAL",
    "reported_by_callsign": "RECON-2-TEST"
  }'
```

**Résultat attendu** :
- Code 201 Created
- JSON avec `id` généré

**Statut** : [ ] PASS [ ] FAIL

---

### T2-02 : Liste POI avec filtres

**Endpoint** : `GET /api/atak/poi?category=CACHE&affiliation=ENEMY`

```bash
curl -X GET "$API_URL/poi?category=CACHE&affiliation=ENEMY" \
  -H "X-ATAK-Token: $TOKEN"
```

**Résultat attendu** :
- Code 200 OK
- Array contenant au moins le POI créé en T2-01

**Statut** : [ ] PASS [ ] FAIL

---

### T3-01 : Créer zone cercle

**Endpoint** : `POST /api/atak/zones`

```bash
curl -X POST "$API_URL/zones" \
  -H "X-ATAK-Token: $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "zone_name": "LZ Alpha TEST",
    "zone_code": "LZ-ALPHA-TEST",
    "zone_type": "LZ",
    "geometry_type": "CIRCLE",
    "center_x": 10000.0,
    "center_y": 10000.0,
    "radius": 150.0,
    "status": "ACTIVE",
    "priority": "HIGH",
    "alert_on_entry": true,
    "alert_message": "Entrée LZ Alpha",
    "alert_sound": "ATAK_ZONE_ENTRY"
  }'
```

**Résultat attendu** :
- Code 201 Created
- JSON avec `id` généré

**Statut** : [ ] PASS [ ] FAIL

---

### T3-02 : Check position DANS zone

**Endpoint** : `POST /api/atak/zones/check-position`

```bash
curl -X POST "$API_URL/zones/check-position" \
  -H "X-ATAK-Token: $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "pos_x": 10050.0,
    "pos_y": 10050.0,
    "callsign": "TEST-UNIT",
    "steam_id": "76561198000000000"
  }'
```

**Résultat attendu** :
- Code 200 OK
- `zones` array contenant LZ-ALPHA-TEST
- `alerts` array contenant une alerte générée

**Vérification BDD** :
```sql
SELECT * FROM atak_zone_alerts WHERE zone_id = {ZONE_ID} ORDER BY id DESC LIMIT 1;
```

**Statut** : [ ] PASS [ ] FAIL

---

### T3-03 : Check position HORS zone

**Endpoint** : `POST /api/atak/zones/check-position`

```bash
curl -X POST "$API_URL/zones/check-position" \
  -H "X-ATAK-Token: $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "pos_x": 20000.0,
    "pos_y": 20000.0,
    "callsign": "TEST-UNIT-2"
  }'
```

**Résultat attendu** :
- Code 200 OK
- `zones` array vide
- `alerts` array vide

**Statut** : [ ] PASS [ ] FAIL

---

### T4-01 : Créer MEDEVAC

**Endpoint** : `POST /api/atak/medevac`

```bash
curl -X POST "$API_URL/medevac" \
  -H "X-ATAK-Token: $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "priority": "URGENT",
    "pickup_grid": "150080",
    "pickup_pos_x": 15000.0,
    "pickup_pos_y": 8000.0,
    "radio_frequency": "30.0",
    "radio_callsign": "ALPHA-1-TEST",
    "patients_t1_urgent": 1,
    "patients_t2_urgent": 0,
    "patients_t3_delayed": 2,
    "patients_t4_expectant": 0,
    "patients_litter": 2,
    "patients_ambulatory": 1,
    "security_status": "POSSIBLE_ENEMY",
    "lz_marking": "SMOKE",
    "lz_marking_color": "GREEN",
    "requested_by_callsign": "MEDIC-1-TEST"
  }'
```

**Résultat attendu** :
- Code 201 Created
- JSON avec `medevac_number` généré (ex: `MEDEVAC-20260724-001`)
- `golden_hour_expires_at` calculé automatiquement (+ 60min)

**Vérification BDD** :
```sql
SELECT 
  medevac_number,
  patients_t1_urgent,
  golden_hour_expires_at,
  TIMESTAMPDIFF(MINUTE, requested_at, golden_hour_expires_at) as minutes_diff
FROM atak_medevac_requests 
WHERE medevac_number LIKE 'MEDEVAC-2026%' 
ORDER BY id DESC LIMIT 1;

-- minutes_diff doit être 60
```

**Statut** : [ ] PASS [ ] FAIL

---

### T4-02 : Assigner asset MEDEVAC

**Endpoint** : `POST /api/atak/medevac/{id}/assign`

```bash
curl -X POST "$API_URL/medevac/{ID}/assign" \
  -H "X-ATAK-Token: $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "asset_callsign": "DUSTOFF-1-TEST"
  }'
```

**Résultat attendu** :
- Code 200 OK
- `status` passé à `ASSIGNED`
- `assigned_asset_callsign` = "DUSTOFF-1-TEST"

**Statut** : [ ] PASS [ ] FAIL

---

### T4-03 : Ajouter patient

**Endpoint** : `POST /api/atak/medevac/{id}/patients`

```bash
curl -X POST "$API_URL/medevac/{ID}/patients" \
  -H "X-ATAK-Token: $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "patient_callsign": "ALPHA-3-TEST",
    "triage_category": "T1",
    "triaged_by_callsign": "MEDIC-1-TEST",
    "consciousness": "VERBAL",
    "breathing": "ABNORMAL",
    "circulation": "COMPROMISED",
    "primary_injury": "Blessure par éclat thorax",
    "injuries": [
      {
        "location": "thorax",
        "type": "shrapnel",
        "severity": "critical"
      }
    ],
    "treatments_given": ["tourniquet", "chest_seal"],
    "is_stabilized": true,
    "requires_litter": true
  }'
```

**Résultat attendu** :
- Code 201 Created
- Patient visible dans `GET /api/atak/medevac/{id}`

**Statut** : [ ] PASS [ ] FAIL

---

### T5-01 : Créer demande QRF

**Endpoint** : `POST /api/atak/qrf`

```bash
curl -X POST "$API_URL/qrf" \
  -H "X-ATAK-Token: $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "priority": "IMMEDIATE",
    "contact_pos_x": 18000.0,
    "contact_pos_y": 9000.0,
    "grid_reference": "180090",
    "threat_type": "AMBUSH",
    "threat_description": "Embuscade sur convoi TEST",
    "enemy_strength": "SQUAD",
    "requesting_unit": "Convoi Charlie TEST",
    "requesting_callsign": "CHARLIE-6-TEST",
    "friendly_strength": 12,
    "friendly_casualties": 2,
    "friendly_status": "PINNED",
    "support_requested": ["infantry", "cas"]
  }'
```

**Résultat attendu** :
- Code 201 Created
- `qrf_number` généré (ex: `QRF-20260724-001`)
- `urgency_deadline` calculé automatiquement (IMMEDIATE = +15min)

**Vérification BDD** :
```sql
SELECT 
  qrf_number,
  priority,
  urgency_deadline,
  TIMESTAMPDIFF(MINUTE, requested_at, urgency_deadline) as minutes_diff
FROM atak_qrf_requests 
WHERE qrf_number LIKE 'QRF-2026%' 
ORDER BY id DESC LIMIT 1;

-- minutes_diff doit être 15 pour IMMEDIATE
```

**Statut** : [ ] PASS [ ] FAIL

---

### T5-02 : Assigner QRF

**Endpoint** : `POST /api/atak/qrf/{id}/assign`

```bash
curl -X POST "$API_URL/qrf/{ID}/assign" \
  -H "X-ATAK-Token: $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "qrf_unit": "Réserve Bravo TEST",
    "qrf_callsign": "BRAVO-QRF-TEST"
  }'
```

**Résultat attendu** :
- Code 200 OK
- `status` passé à `QRF_ASSIGNED`

**Statut** : [ ] PASS [ ] FAIL

---

### T5-03 : Update position QRF

**Endpoint** : `POST /api/atak/qrf/{id}/position`

```bash
curl -X POST "$API_URL/qrf/{ID}/position" \
  -H "X-ATAK-Token: $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "pos_x": 16500.0,
    "pos_y": 8500.0,
    "eta": "2026-07-24 12:30:00"
  }'
```

**Résultat attendu** :
- Code 200 OK
- `qrf_current_pos_x/y` mis à jour
- `distance_to_contact` calculé automatiquement dans vue

**Vérification BDD** :
```sql
SELECT 
  qrf_current_pos_x,
  qrf_current_pos_y,
  distance_to_contact
FROM v_atak_active_qrf
WHERE id = {ID};

-- distance_to_contact doit être ~1803 (formule euclidienne)
```

**Statut** : [ ] PASS [ ] FAIL

---

### T6-01 : Upsert véhicule (création)

**Endpoint** : `POST /api/atak/vehicles`

```bash
curl -X POST "$API_URL/vehicles" \
  -H "X-ATAK-Token: $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "vehicle_callsign": "ARMORED-1-TEST",
    "vehicle_name": "Bradley IFV TEST",
    "vehicle_class": "IFV",
    "vehicle_type": "M2A3 Bradley",
    "side": "BLUFOR",
    "crew_commander_callsign": "ALPHA-6-TEST",
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
    "status": "OPERATIONAL"
  }'
```

**Résultat attendu** :
- Code 201 Created
- Véhicule créé avec tous les champs

**Vérification BDD** :
```sql
SELECT * FROM atak_vehicle_tracking WHERE vehicle_callsign = 'ARMORED-1-TEST';

-- Vérifier trigger deployed
SELECT * FROM atak_vehicle_events WHERE vehicle_tracking_id = {VEHICLE_ID} AND event_type = 'DEPLOYED';
```

**Statut** : [ ] PASS [ ] FAIL

---

### T6-02 : Upsert véhicule (mise à jour position)

**Endpoint** : `POST /api/atak/vehicles`

```bash
curl -X POST "$API_URL/vehicles" \
  -H "X-ATAK-Token: $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "vehicle_callsign": "ARMORED-1-TEST",
    "pos_x": 14050.0,
    "pos_y": 11020.0,
    "heading": 272.0,
    "speed": 48.0,
    "fuel_percent": 64.5
  }'
```

**Résultat attendu** :
- Code 200 OK
- Position mise à jour
- `last_seen_at` actualisé

**Vérification BDD** :
```sql
-- Vérifier position mise à jour
SELECT pos_x, pos_y, fuel_percent FROM atak_vehicle_tracking WHERE vehicle_callsign = 'ARMORED-1-TEST';

-- Vérifier historique positions
SELECT COUNT(*) FROM atak_vehicle_position_history WHERE vehicle_tracking_id = {VEHICLE_ID};
-- Doit être >= 2 (création + update)
```

**Statut** : [ ] PASS [ ] FAIL

---

### T6-03 : Demander service refuel

**Endpoint** : `POST /api/atak/vehicles/{id}/service`

```bash
curl -X POST "$API_URL/vehicles/{ID}/service" \
  -H "X-ATAK-Token: $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "request_type": "REFUEL",
    "priority": "HIGH",
    "request_details": "Carburant critique TEST",
    "service_pos_x": 14050.0,
    "service_pos_y": 11020.0,
    "requested_by_callsign": "ARMORED-1-TEST"
  }'
```

**Résultat attendu** :
- Code 201 Created
- Service request créée

**Vérification BDD** :
```sql
SELECT * FROM atak_vehicle_service_requests WHERE vehicle_tracking_id = {VEHICLE_ID} AND status = 'REQUESTED';
```

**Statut** : [ ] PASS [ ] FAIL

---

## 🔍 Tests triggers et calculs automatiques

### TT-01 : Golden hour MEDEVAC

**Objectif** : Vérifier calcul automatique golden hour

**Procédure** :
1. Créer MEDEVAC avec `patients_t1_urgent > 0`
2. Vérifier `golden_hour_expires_at = requested_at + 60min`

```sql
SELECT 
  medevac_number,
  patients_t1_urgent,
  requested_at,
  golden_hour_expires_at,
  TIMESTAMPDIFF(MINUTE, requested_at, golden_hour_expires_at) as diff_minutes
FROM atak_medevac_requests
WHERE patients_t1_urgent > 0
ORDER BY id DESC LIMIT 1;
```

**Résultat attendu** : `diff_minutes` = 60

**Statut** : [ ] PASS [ ] FAIL

---

### TT-02 : Urgency deadline QRF

**Objectif** : Vérifier calcul automatique deadline selon priorité

**Procédure** : Créer QRF avec différentes priorités

```sql
-- FLASH = +5min
-- IMMEDIATE = +15min
-- PRIORITY = +30min
-- ROUTINE = +60min

SELECT 
  qrf_number,
  priority,
  requested_at,
  urgency_deadline,
  TIMESTAMPDIFF(MINUTE, requested_at, urgency_deadline) as diff_minutes
FROM atak_qrf_requests
ORDER BY id DESC LIMIT 5;
```

**Résultat attendu** : Délais corrects selon priorité

**Statut** : [ ] PASS [ ] FAIL

---

### TT-03 : Colonnes calculées véhicules

**Objectif** : Vérifier colonnes calculées vue véhicules

```sql
SELECT 
  vehicle_callsign,
  fuel_percent,
  is_fuel_critical,
  fuel_status_label,
  ammo_percent,
  is_ammo_critical,
  is_damaged
FROM v_atak_active_vehicles
WHERE vehicle_callsign = 'ARMORED-1-TEST';
```

**Vérifications** :
- `is_fuel_critical` = TRUE si `fuel_percent < 20`
- `fuel_status_label` = "CRITICAL" si < 20%, "LOW" si < 40%, etc.
- `is_ammo_critical` = TRUE si `ammo_percent < 30`
- `is_damaged` = TRUE si un composant < 80%

**Statut** : [ ] PASS [ ] FAIL

---

## 🧹 Nettoyage tests

### Supprimer données de test

```sql
-- Rapports
DELETE FROM atak_tactical_reports WHERE submitter_callsign LIKE '%-TEST';

-- POI
DELETE FROM atak_poi WHERE poi_code LIKE '%-TEST%';

-- Zones
DELETE FROM atak_tactical_zones WHERE zone_code LIKE '%-TEST%';

-- MEDEVAC
DELETE FROM atak_medevac_requests WHERE requested_by_callsign LIKE '%-TEST';

-- QRF
DELETE FROM atak_qrf_requests WHERE requesting_callsign LIKE '%-TEST';

-- Véhicules
DELETE FROM atak_vehicle_tracking WHERE vehicle_callsign LIKE '%-TEST';

-- Vérifier nettoyage
SELECT 
  (SELECT COUNT(*) FROM atak_tactical_reports WHERE submitter_callsign LIKE '%-TEST') as reports,
  (SELECT COUNT(*) FROM atak_poi WHERE poi_code LIKE '%-TEST%') as pois,
  (SELECT COUNT(*) FROM atak_tactical_zones WHERE zone_code LIKE '%-TEST%') as zones,
  (SELECT COUNT(*) FROM atak_medevac_requests WHERE requested_by_callsign LIKE '%-TEST') as medevacs,
  (SELECT COUNT(*) FROM atak_qrf_requests WHERE requesting_callsign LIKE '%-TEST') as qrfs,
  (SELECT COUNT(*) FROM atak_vehicle_tracking WHERE vehicle_callsign LIKE '%-TEST') as vehicles;

-- Tous les compteurs doivent être 0
```

**Statut** : [ ] PASS [ ] FAIL

---

## 📊 Résumé tests

### Statistiques

```
Tests préliminaires (TP) : __/2 (___%)
Tests Phase 1 (T1)       : __/4 (___%)
Tests Phase 1 (T2)       : __/2 (___%)
Tests Phase 1 (T3)       : __/3 (___%)
Tests Phase 2 (T4)       : __/3 (___%)
Tests Phase 2 (T5)       : __/3 (___%)
Tests Phase 2 (T6)       : __/3 (___%)
Tests triggers (TT)      : __/3 (___%)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
TOTAL                    : __/23 (___%)
```

### Blockers identifiés

| Test | Description | Criticité | Action |
|------|-------------|-----------|--------|
|      |             |           |        |

### Recommandations

- [ ] Tous les tests PASS → **Déploiement production OK**
- [ ] Quelques FAIL non critiques → **Corriger puis redéployer**
- [ ] Blockers critiques → **Ne pas déployer, investigation requise**

---

## 📝 Notes

**Testeur** : _____________  
**Date tests** : _____________  
**Environnement** : [ ] DEV [ ] STAGING [ ] PROD  
**Version MySQL** : _____________  
**Version PHP** : _____________

**Commentaires** :
```
[Espace pour notes libres]
```

---

*Plan de tests créé : 24 juillet 2026 - Cloud Agent*
