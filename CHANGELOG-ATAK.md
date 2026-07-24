# CHANGELOG - Features ATAK

Toutes les modifications notables des features ATAK sont documentées ici.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/),
et ce projet adhère au [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.0.0] - 2026-07-24

### Ajouté - Phase 1 : Fondations coordination

#### Système de rapports tactiques structurés
- **Tables** : `atak_tactical_reports`, `atak_report_attachments`, `atak_report_templates`
- **Vue** : `v_atak_tactical_reports`
- **Repository** : `AtakTacticalReportRepository` avec 9 méthodes publiques
- **API** : 4 endpoints REST
  - `GET /api/atak/reports` : Liste rapports avec filtres (type, priorité, statut, émetteur, dates)
  - `POST /api/atak/reports` : Créer rapport (SPOTREP, SITREP, SALUTE, CONTACT)
  - `GET /api/atak/reports/{id}` : Détail rapport avec attachements
  - `POST /api/atak/reports/{id}/acknowledge` : Acquitter rapport
- **Features** :
  - Support 4 types : SPOTREP, SITREP, SALUTE, CONTACT
  - Génération automatique numéro rapport (`SPOTREP-20260724-001`)
  - Données structurées JSON (SALUTE : Size, Activity, Location, Unit, Time, Equipment)
  - Classification : UNCLASSIFIED, RESTRICTED, CONFIDENTIAL, SECRET
  - Priorités : ROUTINE, PRIORITY, IMMEDIATE, FLASH
  - Statuts : DRAFT, SUBMITTED, ACKNOWLEDGED, ACTIONED, ARCHIVED
  - Système visibilité : ALL, COMMAND, RESTRICTED, PRIVATE
  - Géolocalisation (pos_x, pos_y, grid_reference)
  - Multi-tenant et context-aware

#### Système POI (Points d'Intérêt) tactiques
- **Tables** : `atak_poi`, `atak_poi_observations`, `atak_poi_photos`
- **Vue** : `v_atak_poi` avec compteurs observations/photos
- **Repository** : `AtakPoiRepository` avec 10 méthodes publiques
- **API** : 3 endpoints REST
  - `GET /api/atak/poi` : Liste POI avec filtres (catégorie, affiliation, statut, menace)
  - `POST /api/atak/poi` : Créer POI
  - `PUT /api/atak/poi/{id}` : Mettre à jour POI
- **Features** :
  - 13 catégories : OBJECTIVE, BUILDING, CACHE, ENEMY_POSITION, HVT, PATROL_BASE, CHECKPOINT, STRUCTURE, INFRASTRUCTURE, ROUTE, TERRAIN, HAZARD, OTHER
  - Affiliation : FRIENDLY, ENEMY, NEUTRAL, UNKNOWN
  - Certitude : CONFIRMED, PROBABLE, POSSIBLE, DOUBTFUL
  - Niveau menace : NONE, LOW, MEDIUM, HIGH, CRITICAL
  - Statuts : ACTIVE, NEUTRALIZED, DESTROYED, ABANDONED, OCCUPIED, UNDER_SURVEILLANCE
  - Recherche proximité géographique (`findNearPosition`)
  - Historique observations multiples
  - Photos géolocalisées
  - Source fiabilité (échelle A-F NATO)
  - Génération automatique code POI

#### Zones tactiques enrichies
- **Tables** : `atak_tactical_zones`, `atak_zone_alerts`
- **Vue** : `v_atak_active_zones` avec calcul `is_currently_active`
- **Repository** : `AtakTacticalZoneRepository` avec 14 méthodes publiques
- **API** : 4 endpoints REST
  - `GET /api/atak/zones` : Liste zones avec filtres
  - `POST /api/atak/zones` : Créer zone
  - `POST /api/atak/zones/check-position` : Vérifier position dans zones
  - `GET /api/atak/zones/alerts` : Liste alertes non acquittées
- **Features** :
  - 9 types zones : LZ, DZ, OBJECTIVE, DANGER_ZONE, NO_GO_AREA, PATROL_AREA, SECTOR, BOUNDARY, OTHER
  - 3 géométries : CIRCLE, RECTANGLE, POLYGON
  - Algorithmes géométriques :
    - `isInCircle()` : Calcul distance euclidienne
    - `isInRectangle()` : Test rotation + bounds
    - `isInPolygon()` : Ray casting algorithm
  - Système alertes entrée/sortie configurable
  - Sons alertes personnalisables
  - Temporalité (`active_from`, `active_until`)
  - Priorités et niveaux menace
  - Style visuel (couleurs, opacité, contours)
  - Log détaillé alertes avec position exacte

### Ajouté - Phase 2 : Capacités spécialisées

#### Extension système MEDEVAC 9-Line avec triage TCCC
- **Tables** : `atak_medevac_requests`, `atak_medevac_patients`, `atak_medevac_status_updates`
- **Vue** : `v_atak_active_medevac` avec golden hour et patients
- **Triggers** :
  - `trg_medevac_golden_hour` : Calcul automatique golden hour pour patients T1
  - `trg_medevac_status_log` : Logging changements statut
- **Repository** : `AtakMedevacRepository` avec 12 méthodes publiques
- **API** : 6 endpoints REST
  - `GET /api/atak/medevac` : Liste MEDEVAC
  - `POST /api/atak/medevac` : Créer demande MEDEVAC 9-Line
  - `GET /api/atak/medevac/{id}` : Détail avec patients
  - `PATCH /api/atak/medevac/{id}/status` : Mettre à jour statut
  - `POST /api/atak/medevac/{id}/assign` : Assigner asset
  - `POST /api/atak/medevac/{id}/patients` : Ajouter patient
- **Features** :
  - Format 9-Line NATO complet
  - Triage TCCC : T1 (urgent), T2 (urgent surgical), T3 (delayed), T4 (expectant)
  - Golden hour tracking automatique (T1)
    - Calcul expiration : request_time + 60min
    - Statut : `OK` (> 30min), `WARNING` (15-30min), `CRITICAL` (< 15min), `EXPIRED` (> 60min)
    - Minutes restantes calculées
  - Catégories patients : Litter vs Ambulatory
  - Équipement spécialisé : hoist, ventilator, blood, etc.
  - Statut sécurité LZ : NO_ENEMY, POSSIBLE_ENEMY, ENEMY_IN_AREA, ENEMY_SUPPRESSED
  - Marquage LZ : NONE, SMOKE, PANEL, STROBE, FLARE, VS17, MIRROR
  - Contamination NBC tracking
  - Workflow complet : REQUESTED → ACKNOWLEDGED → ASSIGNED → INBOUND → ON_SITE → EVACUATING → COMPLETED
  - Historique changements statut avec timestamps
  - Données médicales détaillées par patient :
    - Conscience : ALERT, VERBAL, PAIN, UNRESPONSIVE
    - Respiration : NORMAL, ABNORMAL, ABSENT
    - Circulation : NORMAL, COMPROMISED, ABSENT
    - Blessures structurées (location, type, severity)
    - Traitements appliqués
    - Médicaments administrés (nom, dose, heure)

#### Système QRF (Quick Reaction Force)
- **Tables** : `atak_qrf_requests`, `atak_qrf_sitrep_updates`, `atak_qrf_waypoints`
- **Vue** : `v_atak_active_qrf` avec distance et urgence
- **Trigger** : `trg_qrf_urgency_deadline` : Calcul deadline urgence
- **Repository** : `AtakQrfRepository` avec 13 méthodes publiques
- **API** : 5 endpoints REST
  - `GET /api/atak/qrf` : Liste QRF
  - `POST /api/atak/qrf` : Créer demande QRF
  - `POST /api/atak/qrf/{id}/assign` : Assigner QRF
  - `POST /api/atak/qrf/{id}/position` : Mettre à jour position QRF
  - `POST /api/atak/qrf/{id}/sitrep` : Ajouter SITREP
- **Features** :
  - Types menace : AMBUSH, ATTACK, TROOPS_IN_CONTACT, CASEVAC_URGENT, IED_STRIKE, OTHER
  - Taille ennemi : FIRE_TEAM, SQUAD, PLATOON, COMPANY, UNKNOWN
  - Statut unité amie : SECURE, ENGAGED, PINNED, OVERRUN, RETREATING
  - Workflow : REQUESTED → ACKNOWLEDGED → QRF_ASSIGNED → QRF_ENROUTE → QRF_ENGAGED → SITUATION_STABILIZED → COMPLETED
  - Tracking position QRF temps réel
  - Calcul distance vers zone contact (formule euclidienne)
  - ETA dynamique
  - SITREP multi-source (demandeur + QRF)
  - Types SITREP : STATUS_CHANGE, POSITION_UPDATE, SITUATION_UPDATE, CONTACT_REPORT
  - Waypoints route QRF
  - Deadline urgence (FLASH : 5min, IMMEDIATE : 15min, PRIORITY : 30min)
  - Support demandé multiples : infantry, armor, aviation, cas, medevac, eod, engineers

#### Suivi véhicules et assets lourds enrichi
- **Tables** : `atak_vehicle_tracking`, `atak_vehicle_position_history`, `atak_vehicle_events`, `atak_vehicle_service_requests`
- **Vue** : `v_atak_active_vehicles` avec statut fuel/ammo
- **Triggers** :
  - `trg_vehicle_deployed` : Logging déploiement
  - `trg_vehicle_destroyed` : Logging destruction
- **Repository** : `AtakVehicleTrackingRepository` avec 16 méthodes publiques
- **API** : 4 endpoints REST
  - `GET /api/atek/vehicles` : Liste véhicules
  - `POST /api/atak/vehicles` : Upsert véhicule (create or update intelligent)
  - `POST /api/atak/vehicles/{id}/service` : Demander service
  - `GET /api/atak/vehicles/service-requests` : Liste demandes service
- **Features** :
  - 10 classes véhicules : LIGHT_VEHICLE, TRUCK, APC, IFV, TANK, ARTILLERY, HELICOPTER, FIXED_WING, UAV, BOAT
  - Côté : BLUFOR, OPFOR, INDEPENDENT, CIVILIAN
  - Statuts : OPERATIONAL, DAMAGED, IMMOBILIZED, DESTROYED, ABANDONED
  - Types mission : TRANSPORT, COMBAT, RECON, SUPPLY, MEDEVAC, CAS, CAP, PATROL, LOGISTICS
  - Tracking complet :
    - Position GPS (pos_x, pos_y)
    - Cap et vitesse
    - Fuel % (alerte < 20%)
    - Munitions % (alerte < 30%)
    - Santé composants : moteur, coque, chenilles/roues, tourelle
  - Upsert intelligent par callsign :
    - Si véhicule existe : mise à jour sélective (seulement champs fournis)
    - Si nouveau : création complète
    - Update automatique `last_seen_at`
  - Historique positions (table séparée pour replay)
  - Événements automatiques :
    - DEPLOYED, DESTROYED, DAMAGED, REPAIRED, ABANDONED, RECOVERED, REFUELED, REARMED
  - Demandes service :
    - Types : REFUEL, REARM, REPAIR, MAINTENANCE, RECOVERY
    - Priorités : LOW, MEDIUM, HIGH, CRITICAL
    - Statuts : REQUESTED, ACKNOWLEDGED, IN_PROGRESS, COMPLETED, CANCELLED
  - Calcul distance vers destination
  - Statut "véhicule actif" (vu < 30min)
  - Labels fuel/ammo : CRITICAL, LOW, MEDIUM, OK, FULL
  - Équipage et passagers tracking

### Documentation

#### Guides utilisateur et intégration
- **`docs/GUIDE-INTEGRATION-API-ATAK.md`** : Guide complet 31 endpoints
  - Exemples SQF pour mod Arma
  - Exemples JavaScript pour interface web
  - Formats requêtes/réponses détaillés
  - Codes erreurs
  - Notes performance et sécurité

#### Documentation technique
- **`docs/SYNTHESE-TECHNIQUE-ATAK-PHASES-1-2.md`** : Synthèse technique complète
  - Architecture système
  - Détails base de données (15 tables, 5 vues, 4 triggers)
  - Pattern repositories
  - Sécurité et performance
  - Tests recommandés
  - Roadmap Phase 3-5

#### Proposition features
- **`docs/NOUVELLES-FEATURES-ATAK-MOD.md`** : Proposition 15 features sur 5 phases
  - Features détaillées avec cas d'usage
  - Notes implémentation
  - Priorités P0/P1/P2

#### Documentation produit
- **`docs/COMPARAISON-PRODUIT-COMSPEC-CTAB-SIT.md`** : Comparaison produits
- **`docs/ATAK-WEB-DOCUMENTATION-PRODUIT.md`** : Doc ATAK Web
- **`docs/ATHENA-MYTHOLOGIE.md`** : Lien mythologique
- **Variantes forum** : `*-VERSION-FORUM.md` (sans URLs/tableaux)

### Migration

#### Scripts SQL
- **`migrations/2026_07_24_001_atak_tactical_reports.sql`** : Phase 1.1
- **`migrations/2026_07_24_002_atak_poi_intelligence.sql`** : Phase 1.2
- **`migrations/2026_07_24_003_atak_tactical_zones.sql`** : Phase 1.3
- **`migrations/2026_07_24_004_atak_medevac_extended.sql`** : Phase 2.1
- **`migrations/2026_07_24_005_atak_qrf_system.sql`** : Phase 2.2
- **`migrations/2026_07_24_006_atak_vehicle_tracking.sql`** : Phase 2.3

Toutes les migrations :
- Sont idempotentes (`IF NOT EXISTS`)
- Commentées en détail
- Incluent contraintes FK
- Définissent index stratégiques
- Multi-tenant natives

### Sécurité

#### Multi-tenant
- Isolation complète par `tenant_id` + `context_id`
- Contraintes FK vers `tenants` et `contextes`
- Filtrage systématique dans repositories

#### Soft delete
- Implémenté sur : reports, POI, zones
- Permet restauration et audit
- Vues filtrent automatiquement

#### Protection SQL
- Requêtes préparées PDO
- Pas de concaténation SQL
- Paramètres bindés

### Performance

#### Optimisations base de données
- Index composites sur (tenant_id, context_id)
- Index géographiques sur (pos_x, pos_y)
- Colonnes calculées STORED
- Vues enrichies pour éviter N+1 queries

#### Optimisations API
- Pagination par défaut (limit: 100-200)
- Filtres côté SQL
- Sélection colonnes via vues

### Dépendances

#### Backend
- PHP >= 8.0
- MySQL >= 8.0
- Extension PDO MySQL
- Authentification COMSPEC existante

#### Frontend (à implémenter)
- Leaflet.js
- JavaScript ES6+

#### Mod Arma (à implémenter)
- CBA A3
- Extension C# .NET
- SQF functions

---

## [À venir] - Phase 3 : Coordination avancée

### Planifié

#### Waypoints et routes partagées
- Table `atak_shared_waypoints`
- Synchronisation bidirectionnelle web ↔ jeu
- Calcul distance et temps estimé
- Routes partagées entre unités
- Visualisation temps réel sur carte

#### Timeline mission interactive
- Table `atak_mission_timeline`
- Agrégation tous événements (rapports, contacts, MEDEVAC, QRF, etc.)
- Filtres par type, unité, criticité
- Navigation temporelle
- Export PDF/Excel pour AAR

#### Contrôle artillerie et mortiers
- Table `atak_fire_missions`
- Calcul balistique (élévation, azimut, charge)
- Visualisation zone impact
- Workflow mission feu NATO
- Corrections tir (shot, splash, impact)

---

## [À venir] - Phase 4 : Capacités avancées

### Planifié

#### Système UAV et reconnaissance
- Table `atak_uav_tracking`
- Flux vidéo (captures périodiques)
- Détection automatique contacts
- Zones surveillance
- Handoff entre opérateurs

#### IFF avancé
- Extension système IFF existant
- Interrogation active
- Code du jour dynamique
- Alertes véhicule inconnu
- Intégration avec véhicules

#### Intégration météo opérationnelle
- Table `atak_weather_log`
- Impact visibilité/portée
- Alertes conditions critiques
- Prévisions mission
- Historique pour AAR

---

## [À venir] - Phase 5 : Immersion totale

### Planifié

#### Mode replay complet
- Reconstruction mission 3D
- Contrôles vidéo (play, pause, vitesse)
- Changement point de vue
- Export MP4
- Analyse post-mission

#### Système certifications LMS
- Intégration avec LMS existant
- Déblocage capacités selon certification
- Badges visibles in-game
- Progression utilisateur
- Rapports performance

#### Contrôle caméra et observation
- Stream images caméras terrain
- Demande vues spécifiques
- Contrôle PTZ (pan, tilt, zoom)
- Archive pour AAR
- Multi-flux simultanés

---

## Versionning

**Format** : [MAJOR.MINOR.PATCH]

- **MAJOR** : Changements incompatibles API
- **MINOR** : Ajout features rétrocompatibles
- **PATCH** : Corrections bugs rétrocompatibles

**Version actuelle** : 1.0.0 (Phase 1 & 2 complètes)

---

*Document maintenu par l'équipe développement COMSPEC*
