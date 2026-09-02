# Audit — features ATAK annoncées dans `NOUVELLES-FEATURES-ATAK-MOD.md`

Vérification, feature par feature, de ce qui existe réellement dans le dépôt.

`NOUVELLES-FEATURES-ATAK-MOD.md` est une **proposition de roadmap**, pas un relevé de
livraison : rien n'y indique ce qui a été fait. Cet audit comble ce manque.

**Méthode** : pour chaque feature, recherche des routes déclarées dans `routes/web.php`, des
repositories dans `app/Repositories/`, et des tables dans `migrations/`. Une feature n'est
comptée « livrée » que si les trois se rejoignent.

**Portée** : cet audit ne couvre que le **côté portail** (API, base, back-office). Le code
SQF du mod et l'extension native ne sont pas vérifiables depuis ici — quand une feature en
dépend, c'est dit.

---

## Synthèse

| État | Nombre |
|---|---|
| Livrées côté portail | 10 |
| Partielles | 3 |
| Absentes | 2 |

La feature 1 était absente au début de l'audit ; elle a été implémentée dans le même lot
(détail ci-dessous). Le décompte reflète l'état après ce lot.

---

## Détail

### 1. Waypoints partagés et routes de patrouille — **livré depuis cet audit**

Était **absent** : ni table `atak_waypoints`, ni endpoint `/api/atak/waypoints`. Les
marqueurs (`/api/atak/markers`) existaient mais sans numéro de séquence, sans notion
d'itinéraire, sans horodatage d'atteinte — donc sans rien de ce que la feature demande.

Livré dans le même lot que cet audit :

- `migrations/2026_07_27_001_atak_waypoints.sql` — tables `atak_waypoint_routes` et
  `atak_waypoints`, enregistrées dans `bootstrap/atak_modules_schema_migration.php`.
  Le fichier est écrit portable (ni `COMMENT`, ni `FOREIGN KEY`, ni colonne `GENERATED`),
  ce qui lui évite les échecs MariaDB documentés pour le lot `2026_07_24_*`.
- `App\Support\AtakWaypointsSchema` — filet de sécurité à chaud. `AtakModulesSchema::ensure()`
  s'arrête dès que `atak_poi` existe : sur une base déjà installée, elle n'aurait jamais posé
  ces tables. Ce garde teste `atak_waypoints` et exécute son propre fichier.
- `App\Repositories\AtakWaypointRepository` — itinéraires et points, rang dans l'itinéraire,
  distance par segment et cumulée, marquage « atteint » horodaté. Le statut de l'itinéraire
  suit la progression tout seul : `PLANNED` → `ACTIVE` au premier point atteint,
  → `COMPLETED` quand tous le sont.
- `App\Controllers\Api\AtakWaypointApiController` et les routes :
  `/api/atak/waypoint-routes` (GET, POST), `/api/atak/waypoint-routes/{id}` (GET, PUT,
  PATCH, DELETE), `/api/atak/waypoints` (GET, POST), `/api/atak/waypoints/{id}` (PUT,
  PATCH, DELETE) et `/api/atak/waypoints/{id}/reached` (POST).

Deux points d'attention volontaires :

- La création d'itinéraire accepte les points dans le même appel (`waypoints: [...]`), pour
  éviter au mod un aller-retour par point.
- `GET /api/atak/waypoint-routes/{id}` renvoie `next_waypoint`, le prochain point non
  atteint — c'est ce dont le mod a besoin pour guider, et cela évite de lui faire trier la
  liste lui-même.

Reste à faire côté mod, hors de ce dépôt : le sondage périodique SQF et la création des
marqueurs numérotés en jeu. `POST /api/atak/waypoints/{id}/reached` est le point d'entrée
prévu pour cela, et accepte `reached: false` pour annuler une détection trop hâtive.

### 2. Gestion de zones (LZ, DZ, objectifs, zones dangereuses) — **livré**

`/api/atak/zones`, `/api/atak/zones/alerts`, `/api/atak/zones/check-position`.
`AtakTacticalZoneRepository`, `AtakZoneThreatRepository`, `DangerZoneRepository`.
Table par `2026_07_24_003_atak_tactical_zones.sql`.

### 3. Rapports structurés (SPOTREP, SITREP, SALUTE) — **livré**

`/api/atak/reports`, `/api/atak/reports/{id}`, `/api/atak/reports/{id}/acknowledge`,
`/api/atak/salute`. `AtakTacticalReportRepository`, `AtakReportRoutingRepository`.
Table par `2026_07_24_001_atak_tactical_reports.sql`.

À noter : le référentiel doctrinal (`/back-office/doctrine/referentiel`) décrit désormais
les trames imposées de ces formats, US et FR.

### 4. Suivi des véhicules et assets lourds — **livré**

`/api/atak/vehicles`, `/api/atak/vehicles/service-requests`,
`/api/atak/vehicles/{id}/service`. `AtakVehicleTrackingRepository`.
Table par `2026_07_24_006_atak_vehicle_tracking.sql`.

### 5. Brevets et certifications tactiques — **livré**

`/api/atak/certificates` remplit le rôle décrit. Le document annonçait
`/api/player/certifications` : l'adresse diffère, la capacité est là.

### 6. QRF et demande d'appui — **livré**

`/api/atak/qrf`, `/api/atak/qrf/{id}/assign`, `/api/atak/qrf/{id}/position`,
`/api/atak/qrf/{id}/sitrep`. `AtakQrfRepository`.
Table par `2026_07_24_005_atak_qrf_system.sql`.

### 7. Mode observateur avec contrôle caméra — **absent**

Aucune capture d'écran, aucun téléversement d'image de jeu, aucun contrôle de caméra.

**Non implémentable depuis ce dépôt** : le cœur de la feature est une capture périodique en
SQF et un téléversement par l'extension native. Le portail ne pourrait fournir qu'un point
d'entrée de dépôt, inutile sans le producteur en face.

### 8. Chronologie et timeline mission — **partiel**

Le socle existe : `ReplayRepository::getTimeline()`, `getIntelEvents()`,
`getOperationalEvents()`, exposés par `/api/replay/mission/{missionId}` et
`/api/replay/events/{missionId}`.

Manque : l'endpoint `/api/atak/timeline` annoncé, et le composant de frise temporelle côté
web avec zoom. Les données sont là, la restitution n'y est pas.

### 9. Météo et environnement — **livré**

`/api/atak/weather`.

### 10. Contrôle d'artillerie et mortiers — **absent**

Aucune demande de tir, aucun workflow de validation, aucune zone d'impact.
`/api/atak/designator` et `/api/atak/laser-codes` couvrent le **guidage laser**, ce qui est
une autre capacité : désigner une cible n'est pas demander un tir.

Implémentable côté portail (demande, validation, journal), mais l'effet en jeu dépend du mod.

### 11. Blessés et évacuation médicale — **livré**

`/api/atak/medevac` et ses quatre sous-routes, `/api/atak/medical-alerts`,
`/api/atak/medical-alerts/{id}/triage`. `AtakMedevacRepository`,
`AtakMedicalTriageRepository`, `AtakMedevacIntelligenceRepository`, `CasNineLineRepository`.
Table par `2026_07_24_004_atak_medevac_extended.sql`.

### 12. IFF avancé — **livré**

`/api/iff/challenge`, `/api/iff/respond`, `/api/iff/current`, `/api/iff/assets`,
`/api/iff/assets/sync`, via `IffController`. Vue `views/overwatch/iff-panel.php`.

### 13. Replay mission complète — **partiel**

L'API est complète : `/api/replay/mission/{missionId}`, `/api/replay/events/{missionId}`,
`/api/replay/aar/{missionId}` et son export PDF.

**Anomalie relevée** : la vue `views/overwatch/replay.php` existe mais **aucune route ni
aucun contrôleur ne la rend**. C'est un fichier orphelin — soit il reste à brancher, soit il
est à supprimer. À trancher avant d'ajouter quoi que ce soit.

### 14. Reconnaissance et UAV — **partiel**

`/api/atak/air-assets`, `/api/atak/air-assets/{callsign}/pilot-status`,
`/api/atak/video-feeds`, `/api/atak/flight-manifest` couvrent le suivi des moyens aériens et
les flux vidéo.

Manque le contrôle d'itinéraire d'un drone. Les itinéraires livrés en feature 1 en
fournissent désormais le support de données ; le pilotage reste côté mod.

### 15. Points d'intérêt et intelligence — **livré**

`/api/atak/poi`, `/api/atak/poi/{id}`, `/api/atak/intel`, `/api/atak/sigint`,
`/api/atak/sigint/zones`. `AtakPoiRepository`, `AtakIntelRepository`,
`AtakAdvancedIntelligenceRepository`.
Tables par `2026_07_24_002_atak_poi_intelligence.sql` et `..._007_...sql`.

---

## Ce qui reste, par ordre d'utilité

1. **Frise temporelle (feature 8)** — les données existent déjà : c'est du travail de
   restitution web, sans dépendance au mod. Le meilleur rapport valeur/effort.
2. **Vue de replay orpheline (feature 13)** — trancher : brancher ou supprimer. Un fichier
   non routé finit par dériver du reste du code.
3. **Demandes de tir (feature 10)** — la partie portail est faisable seule (demande,
   validation, journal) ; l'effet en jeu suivra côté mod.
4. **Mode observateur (feature 7)** et **pilotage UAV (feature 14)** — à traiter côté mod
   d'abord. Ouvrir un point d'entrée côté portail avant d'avoir le producteur en face ne
   servirait à rien.
