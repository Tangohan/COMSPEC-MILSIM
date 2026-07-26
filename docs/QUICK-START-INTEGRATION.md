# 🚀 Quick Start - Intégration features ATAK

**Audience** : Développeurs frontend (web) et mod (Arma)  
**Prérequis** : Backend Phase 1 & 2 déployé  
**Objectif** : Premiers appels API fonctionnels en < 30 minutes

---

## 📋 Checklist avant de commencer

### Backend déployé
- [ ] Migrations SQL exécutées (001 à 006)
- [ ] Tables visibles dans base de données
- [ ] API accessible (`/api/atak/reports` répond 200 ou 401)
- [ ] Token authentification disponible

### Environnement dev
- [ ] Accès repository Git
- [ ] Branch `cursor/documentation-atak-comparison-9357` mergée
- [ ] Serveur dev/staging fonctionnel
- [ ] Outils dev installés (voir ci-dessous)

---

## 🛠️ Configuration initiale

### 1. Variables d'environnement

Créer `.env.local` (ou équivalent selon environnement) :

```bash
# API Backend
ATAK_API_URL=https://athena.comspec.fr/api/atak
ATAK_TOKEN=your_atak_token_here

# Database (si accès direct nécessaire)
DB_HOST=localhost
DB_NAME=comspec_milsim
DB_USER=comspec
DB_PASSWORD=your_password_here

# Context (pour dev)
TENANT_ID=1
CONTEXT_ID=1
```

### 2. Tester la connexion API

#### Curl (Linux/Mac)
```bash
curl -H "X-ATAK-Token: YOUR_TOKEN" \
     https://athena.comspec.fr/api/atak/reports

# Réponse attendue : {"reports": [], "count": 0}
```

#### PowerShell (Windows)
```powershell
$headers = @{ "X-ATAK-Token" = "YOUR_TOKEN" }
Invoke-RestMethod -Uri "https://athena.comspec.fr/api/atak/reports" -Headers $headers
```

#### JavaScript (navigateur)
```javascript
fetch('https://athena.comspec.fr/api/atak/reports', {
    headers: { 'X-ATAK-Token': 'YOUR_TOKEN' }
})
.then(r => r.json())
.then(data => console.log('Reports:', data));
```

**✅ Si vous voyez `{"reports": [], "count": 0}`, l'API fonctionne !**

---

## 🌐 Intégration Web (JavaScript/Leaflet)

### Prérequis
```bash
npm install leaflet leaflet-rotatedmarker
# ou
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
```

### Étape 1 : Initialiser la carte (si pas déjà fait)

```javascript
// Initialiser Leaflet sur terrain Arma
const map = L.map('map', {
    crs: L.CRS.Simple,
    minZoom: -3,
    maxZoom: 2
});

// Bounds pour Altis (exemple, adapter selon terrain)
const bounds = [[0, 0], [30720, 30720]];
L.imageOverlay('path/to/altis_map.jpg', bounds).addTo(map);
map.fitBounds(bounds);
```

### Étape 2 : Charger et afficher les POI

```javascript
// Configuration
const ATAK_TOKEN = 'your_token_here';
const API_URL = 'https://athena.comspec.fr/api/atak';

// Fonction helper pour appels API
async function atakFetch(endpoint, options = {}) {
    const response = await fetch(`${API_URL}${endpoint}`, {
        ...options,
        headers: {
            'X-ATAK-Token': ATAK_TOKEN,
            'Content-Type': 'application/json',
            ...options.headers
        }
    });
    
    if (!response.ok) {
        const error = await response.json();
        throw new Error(error.error || 'API Error');
    }
    
    return response.json();
}

// Charger POI sur la carte
async function loadPOIs() {
    const data = await atakFetch('/poi?is_visible=true&status=ACTIVE');
    
    data.pois.forEach(poi => {
        // Couleur selon affiliation
        const colors = {
            'FRIENDLY': '#0088ff',
            'ENEMY': '#ff0000',
            'NEUTRAL': '#808080',
            'UNKNOWN': '#ffcc00'
        };
        
        // Créer marker
        const marker = L.circleMarker([poi.pos_y, poi.pos_x], {
            radius: 8,
            fillColor: colors[poi.affiliation],
            color: '#000',
            weight: 1,
            opacity: 1,
            fillOpacity: 0.8
        }).addTo(map);
        
        // Popup
        marker.bindPopup(`
            <div class="poi-popup">
                <h3>${poi.poi_name}</h3>
                <p><strong>Type:</strong> ${poi.category}</p>
                <p><strong>Affiliation:</strong> ${poi.affiliation}</p>
                <p><strong>Certitude:</strong> ${poi.certainty}</p>
                <p><strong>Menace:</strong> ${poi.threat_level}</p>
                <p>${poi.description}</p>
                <small>Signalé par ${poi.reported_by_callsign}</small>
            </div>
        `);
    });
    
    console.log(`✅ ${data.pois.length} POI chargés`);
}

// Lancer au chargement page
loadPOIs();

// Refresh toutes les 30 secondes
setInterval(loadPOIs, 30000);
```

### Étape 3 : Afficher les zones tactiques

```javascript
async function loadZones() {
    const data = await atakFetch('/zones?is_visible=true&only_active=true');
    
    data.zones.forEach(zone => {
        let shape;
        
        switch (zone.geometry_type) {
            case 'CIRCLE':
                shape = L.circle([zone.center_y, zone.center_x], {
                    radius: zone.radius,
                    color: zone.stroke_color || '#0088ff',
                    fillColor: zone.fill_color || '#0088ff',
                    fillOpacity: zone.opacity || 0.3,
                    weight: zone.stroke_width || 2
                });
                break;
                
            case 'RECTANGLE':
                const halfWidth = zone.width / 2;
                const halfHeight = zone.height / 2;
                const bounds = [
                    [zone.center_y - halfHeight, zone.center_x - halfWidth],
                    [zone.center_y + halfHeight, zone.center_x + halfWidth]
                ];
                shape = L.rectangle(bounds, {
                    color: zone.stroke_color || '#0088ff',
                    fillColor: zone.fill_color || '#0088ff',
                    fillOpacity: zone.opacity || 0.3,
                    weight: zone.stroke_width || 2
                });
                break;
                
            case 'POLYGON':
                const points = JSON.parse(zone.polygon_points || '[]').map(p => [p[1], p[0]]);
                shape = L.polygon(points, {
                    color: zone.stroke_color || '#0088ff',
                    fillColor: zone.fill_color || '#0088ff',
                    fillOpacity: zone.opacity || 0.3,
                    weight: zone.stroke_width || 2
                });
                break;
        }
        
        if (shape) {
            shape.addTo(map);
            shape.bindPopup(`
                <div class="zone-popup">
                    <h3>${zone.zone_name}</h3>
                    <p><strong>Type:</strong> ${zone.zone_type}</p>
                    <p><strong>Statut:</strong> ${zone.status}</p>
                    ${zone.description ? `<p>${zone.description}</p>` : ''}
                </div>
            `);
        }
    });
    
    console.log(`✅ ${data.zones.length} zones chargées`);
}

loadZones();
```

### Étape 4 : Tracker véhicules temps réel

```javascript
const vehicleMarkers = {};

async function updateVehicles() {
    const data = await atakFetch('/vehicles?side=BLUFOR');
    
    data.vehicles.forEach(vehicle => {
        const pos = [vehicle.pos_y, vehicle.pos_x];
        
        if (vehicleMarkers[vehicle.vehicle_callsign]) {
            // Mettre à jour position existante
            vehicleMarkers[vehicle.vehicle_callsign].setLatLng(pos);
            vehicleMarkers[vehicle.vehicle_callsign].setRotationAngle(vehicle.heading);
        } else {
            // Créer nouveau marker
            const icon = L.icon({
                iconUrl: `/assets/icons/${vehicle.vehicle_class.toLowerCase()}.png`,
                iconSize: [32, 32],
                iconAnchor: [16, 16]
            });
            
            const marker = L.marker(pos, {
                icon: icon,
                rotationAngle: vehicle.heading,
                rotationOrigin: 'center'
            });
            
            marker.bindPopup(`
                <div class="vehicle-popup">
                    <h3>${vehicle.vehicle_callsign}</h3>
                    <p><strong>Type:</strong> ${vehicle.vehicle_type}</p>
                    <p><strong>Carburant:</strong> ${vehicle.fuel_percent}% 
                       ${vehicle.is_fuel_critical ? '⚠️' : ''}</p>
                    <p><strong>Munitions:</strong> ${vehicle.ammo_percent}%</p>
                    <p><strong>Vitesse:</strong> ${vehicle.speed} km/h</p>
                    <p><strong>Équipage:</strong> ${vehicle.crew_count}/${vehicle.crew_max}</p>
                </div>
            `);
            
            marker.addTo(map);
            vehicleMarkers[vehicle.vehicle_callsign] = marker;
        }
    });
    
    console.log(`✅ ${data.vehicles.length} véhicules mis à jour`);
}

// Update toutes les 5 secondes
setInterval(updateVehicles, 5000);
updateVehicles(); // Premier chargement
```

---

## 🎮 Intégration Mod Arma (SQF)

### Prérequis
- CBA A3 installé
- Extension C# compilée (`COMSPECExtension.dll`)
- Configuration CBA (URL API + token)

### Étape 1 : Configuration CBA

Dans options CBA Arma 3 :

```
COMSPEC Overwatch Settings:
├── Athena URL: https://athena.comspec.fr
├── Access Key: your_api_key_here
└── Community ID: 1
```

### Étape 2 : Test extension

```sqf
// Test basique extension
private _result = "COMSPECExtension" callExtension ["Test", []];
systemChat format ["Extension: %1", _result];

// Si affiche "OK" ou version, extension fonctionne
```

### Étape 3 : Soumettre un rapport SPOTREP

```sqf
// Fonction: comspec_overwatch_connect_fnc_submitReport
// Usage: [type, priority, summary, details] call comspec_overwatch_connect_fnc_submitReport

private _type = "SPOTREP";
private _priority = "IMMEDIATE";
private _summary = "Contact ennemi observé";
private _details = "Section ennemie 10 hommes, équipée AK + RPG, direction nord";

// Construire données
private _data = createHashMap;
_data set ["report_type", _type];
_data set ["priority", _priority];
_data set ["submitter_callsign", groupId (group player)];
_data set ["pos_x", getPosWorld player select 0];
_data set ["pos_y", getPosWorld player select 1];
_data set ["grid_reference", mapGridPosition player];
_data set ["summary", _summary];
_data set ["details", _details];

// Données structurées SALUTE si applicable
if (_type == "SPOTREP") then {
    private _structuredData = createHashMap;
    _structuredData set ["size", "SQUAD"];
    _structuredData set ["activity", "MOVING"];
    _structuredData set ["location", mapGridPosition player];
    _structuredData set ["equipment", "AK, RPG"];
    _data set ["structured_data", _structuredData];
};

// Envoyer via extension
private _jsonString = [_data] call comspec_overwatch_connect_fnc_hashMapToJson;
private _result = "COMSPECExtension" callExtension ["SubmitReport", [_jsonString]];

// Feedback joueur
if (_result select 0 == "OK") then {
    systemChat "✅ Rapport envoyé au commandement";
} else {
    systemChat format ["❌ Erreur: %1", _result select 1];
};
```

### Étape 4 : Créer un POI en pointant une position

```sqf
// Fonction: comspec_overwatch_connect_fnc_createPOI
// Usage: Appelée depuis action terrain ou menu tablet

private _targetPos = screenToWorld [0.5, 0.5]; // Position centre écran
private _targetObj = cursorTarget; // Objet pointé

if (!isNull _targetObj) then {
    private _data = createHashMap;
    _data set ["poi_name", "Cache d'armes suspecte"];
    _data set ["category", "CACHE"];
    _data set ["affiliation", "ENEMY"];
    _data set ["certainty", "POSSIBLE"];
    _data set ["pos_x", getPosWorld _targetObj select 0];
    _data set ["pos_y", getPosWorld _targetObj select 1];
    _data set ["grid_reference", mapGridPosition _targetObj];
    _data set ["description", format ["Bâtiment %1, activité suspecte", typeOf _targetObj]];
    _data set ["threat_level", "MEDIUM"];
    _data set ["source_type", "VISUAL"];
    _data set ["reported_by_callsign", name player];
    
    private _jsonString = [_data] call comspec_overwatch_connect_fnc_hashMapToJson;
    private _result = "COMSPECExtension" callExtension ["CreatePOI", [_jsonString]];
    
    if (_result select 0 == "OK") then {
        systemChat "✅ POI créé et partagé";
        // Optionnel: placer marker local temporaire
        private _marker = createMarkerLocal [format ["poi_%1", time], getPosWorld _targetObj];
        _marker setMarkerTypeLocal "mil_warning";
        _marker setMarkerColorLocal "ColorRed";
        _marker setMarkerTextLocal "Cache suspecte";
    };
};
```

### Étape 5 : Demander MEDEVAC depuis terrain

```sqf
// Fonction: comspec_overwatch_connect_fnc_requestMEDEVAC
// Usage: Appelée depuis ACE Medical menu ou action medic

private _patients = [
    [player, "T1", "Blessure par éclat thorax"],
    [unitAlpha2, "T3", "Blessure balle bras"]
];

private _data = createHashMap;
_data set ["priority", "URGENT"];
_data set ["pickup_grid", mapGridPosition player];
_data set ["pickup_pos_x", getPosWorld player select 0];
_data set ["pickup_pos_y", getPosWorld player select 1];
_data set ["radio_frequency", "30.0"];
_data set ["radio_callsign", groupId (group player)];

// Compter patients par catégorie
private _t1Count = {(_x select 1) == "T1"} count _patients;
private _t2Count = {(_x select 1) == "T2"} count _patients;
private _t3Count = {(_x select 1) == "T3"} count _patients;

_data set ["patients_t1_urgent", _t1Count];
_data set ["patients_t2_urgent", _t2Count];
_data set ["patients_t3_delayed", _t3Count];
_data set ["patients_t4_expectant", 0];

_data set ["security_status", "POSSIBLE_ENEMY"];
_data set ["lz_marking", "SMOKE"];
_data set ["lz_marking_color", "GREEN"];
_data set ["requested_by_callsign", name player];

private _jsonString = [_data] call comspec_overwatch_connect_fnc_hashMapToJson;
private _result = "COMSPECExtension" callExtension ["RequestMEDEVAC", [_jsonString]];

if (_result select 0 == "OK") then {
    systemChat "✅ MEDEVAC demandée, standby pour extraction";
    hint "MEDEVAC EN ROUTE\nMarquez LZ avec fumée verte\nSécurisez périmètre";
};
```

### Étape 6 : Update position véhicule (périodique)

```sqf
// Fonction périodique (toutes les 5-10s) pour véhicules occupés
// À ajouter dans boucle principale mod ou event handler "GetIn"

if (vehicle player != player) then {
    private _vehicle = vehicle player;
    
    private _data = createHashMap;
    _data set ["vehicle_callsign", getText (configOf _vehicle >> "displayName")];
    _data set ["vehicle_class", typeOf _vehicle]; // Sera mappé côté serveur
    _data set ["pos_x", getPosWorld _vehicle select 0];
    _data set ["pos_y", getPosWorld _vehicle select 1];
    _data set ["heading", getDir _vehicle];
    _data set ["speed", speed _vehicle];
    _data set ["fuel_percent", fuel _vehicle * 100];
    _data set ["status", if (canMove _vehicle) then {"OPERATIONAL"} else {"IMMOBILIZED"}];
    
    // Santé composants
    _data set ["engine_health", (_vehicle getHitPointDamage "HitEngine") * 100];
    _data set ["hull_health", (1 - damage _vehicle) * 100];
    
    // Équipage
    _data set ["crew_count", count crew _vehicle];
    
    private _jsonString = [_data] call comspec_overwatch_connect_fnc_hashMapToJson;
    "COMSPECExtension" callExtension ["UpdateVehicle", [_jsonString]];
    
    // Pas de feedback visible (update silencieux)
};
```

---

## 🧪 Tests rapides

### Test 1 : Créer un rapport depuis web

```javascript
async function testCreateReport() {
    const report = {
        report_type: 'SPOTREP',
        priority: 'IMMEDIATE',
        submitter_callsign: 'ALPHA-1',
        pos_x: 15000,
        pos_y: 8000,
        grid_reference: '150080',
        summary: 'Contact ennemi test',
        details: 'Test API création rapport',
        structured_data: {
            size: 'SQUAD',
            activity: 'MOVING'
        }
    };
    
    const result = await atakFetch('/reports', {
        method: 'POST',
        body: JSON.stringify(report)
    });
    
    console.log('✅ Rapport créé:', result);
}

testCreateReport();
```

### Test 2 : Vérifier position dans zones

```javascript
async function testZoneCheck() {
    const result = await atakFetch('/zones/check-position', {
        method: 'POST',
        body: JSON.stringify({
            pos_x: 10050,
            pos_y: 10050,
            callsign: 'TEST-1'
        })
    });
    
    console.log('Zones détectées:', result.zones);
    console.log('Alertes:', result.alerts);
}

testZoneCheck();
```

### Test 3 : Lister MEDEVAC actives

```javascript
async function testMEDEVAC() {
    const result = await atakFetch('/medevac?status=REQUESTED&priority=URGENT');
    
    result.medevacs.forEach(medevac => {
        console.log(`MEDEVAC ${medevac.medevac_number}:`);
        console.log(`  Golden hour: ${medevac.golden_hour_minutes_remaining}min`);
        console.log(`  Patients: ${medevac.total_patients} (T1: ${medevac.patients_t1_urgent})`);
    });
}

testMEDEVAC();
```

---

## 🐛 Troubleshooting

### Erreur 401 Unauthorized
**Symptôme** : API retourne `{"error": "Unauthorized"}`  
**Cause** : Token invalide ou manquant  
**Solution** : Vérifier `X-ATAK-Token` header et validité token

### Erreur 404 Not Found
**Symptôme** : `Cannot GET /api/atak/...`  
**Cause** : Route inexistante ou URL incorrecte  
**Solution** : Vérifier URL exacte dans `docs/GUIDE-INTEGRATION-API-ATAK.md`

### Données vides []
**Symptôme** : API retourne `{"reports": [], "count": 0}`  
**Cause** : Pas de données pour le tenant/context actuel  
**Solution** : Créer des données de test ou vérifier filtres tenant/context

### Extension Arma ne répond pas
**Symptôme** : `callExtension` retourne chaîne vide  
**Cause** : DLL non chargée ou incompatible  
**Solution** :
1. Vérifier `@COMSPECOverwatch/COMSPECExtension_x64.dll` existe
2. Vérifier BattlEye autorise extension
3. Tester avec `callExtension ["Test", []]`

### Véhicules non trackés
**Symptôme** : Aucun véhicule visible sur carte web  
**Cause** : Boucle update non lancée ou extension non appelée  
**Solution** :
1. Vérifier event handler "GetIn" actif
2. Ajouter debug `systemChat` dans boucle update
3. Vérifier logs API côté serveur

---

## 📚 Ressources

### Documentation complète
- **API Reference** : `docs/GUIDE-INTEGRATION-API-ATAK.md`
- **Architecture** : `docs/SYNTHESE-TECHNIQUE-ATAK-PHASES-1-2.md`
- **Roadmap** : `docs/NOUVELLES-FEATURES-ATAK-MOD.md`
- **CHANGELOG** : `CHANGELOG-ATAK.md`

### Exemples code
- **JavaScript** : Voir section "Intégration interface web" dans `GUIDE-INTEGRATION-API-ATAK.md`
- **SQF** : Voir section "Intégration mod Arma" dans `GUIDE-INTEGRATION-API-ATAK.md`

### Support
- **Issues** : GitHub repository
- **Discord** : Canal #dev-atak (si disponible)

---

## ✅ Checklist premiers tests

- [ ] Connexion API testée (curl/fetch)
- [ ] Token authentification valide
- [ ] Carte Leaflet initialisée
- [ ] POI chargés et affichés
- [ ] Zones affichées correctement
- [ ] Rapport créé depuis web
- [ ] Extension Arma répond
- [ ] Rapport créé depuis Arma
- [ ] Véhicule tracké temps réel
- [ ] Check position zones fonctionne

**Une fois tous les items cochés, vous êtes prêt pour l'intégration complète !** 🎉

---

*Guide créé : 24 juillet 2026 - Cloud Agent*
