# ­ƒÜÇ Quick Start - Int├®gration features ATAK

**Audience** : D├®veloppeurs frontend (web) et mod (Arma)  
**Pr├®requis** : Backend Phase 1 & 2 d├®ploy├®  
**Objectif** : Premiers appels API fonctionnels en < 30 minutes

---

## ­ƒôï Checklist avant de commencer

### Backend d├®ploy├®
- [ ] Migrations SQL ex├®cut├®es (001 ├á 006)
- [ ] Tables visibles dans base de donn├®es
- [ ] API accessible (`/api/atak/reports` r├®pond 200 ou 401)
- [ ] Token authentification disponible

### Environnement dev
- [ ] Acc├¿s repository Git
- [ ] Branch `cursor/documentation-atak-comparison-9357` merg├®e
- [ ] Serveur dev/staging fonctionnel
- [ ] Outils dev install├®s (voir ci-dessous)

---

## ­ƒøá´©Å Configuration initiale

### 1. Variables d'environnement

Cr├®er `.env.local` (ou ├®quivalent selon environnement) :

```bash
# API Backend
ATAK_API_URL=https://athena.comspec.fr/api/atak
ATAK_TOKEN=your_atak_token_here

# Database (si acc├¿s direct n├®cessaire)
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

# R├®ponse attendue : {"reports": [], "count": 0}
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

**Ô£à Si vous voyez `{"reports": [], "count": 0}`, l'API fonctionne !**

---

## ­ƒîÉ Int├®gration Web (JavaScript/Leaflet)

### Pr├®requis
```bash
npm install leaflet leaflet-rotatedmarker
# ou
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
```

### ├ëtape 1 : Initialiser la carte (si pas d├®j├á fait)

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

### ├ëtape 2 : Charger et afficher les POI

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
        
        // Cr├®er marker
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
                <small>Signal├® par ${poi.reported_by_callsign}</small>
            </div>
        `);
    });
    
    console.log(`Ô£à ${data.pois.length} POI charg├®s`);
}

// Lancer au chargement page
loadPOIs();

// Refresh toutes les 30 secondes
setInterval(loadPOIs, 30000);
```

### ├ëtape 3 : Afficher les zones tactiques

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
    
    console.log(`Ô£à ${data.zones.length} zones charg├®es`);
}

loadZones();
```

### ├ëtape 4 : Tracker v├®hicules temps r├®el

```javascript
const vehicleMarkers = {};

async function updateVehicles() {
    const data = await atakFetch('/vehicles?side=BLUFOR');
    
    data.vehicles.forEach(vehicle => {
        const pos = [vehicle.pos_y, vehicle.pos_x];
        
        if (vehicleMarkers[vehicle.vehicle_callsign]) {
            // Mettre ├á jour position existante
            vehicleMarkers[vehicle.vehicle_callsign].setLatLng(pos);
            vehicleMarkers[vehicle.vehicle_callsign].setRotationAngle(vehicle.heading);
        } else {
            // Cr├®er nouveau marker
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
                       ${vehicle.is_fuel_critical ? 'ÔÜá´©Å' : ''}</p>
                    <p><strong>Munitions:</strong> ${vehicle.ammo_percent}%</p>
                    <p><strong>Vitesse:</strong> ${vehicle.speed} km/h</p>
                    <p><strong>├ëquipage:</strong> ${vehicle.crew_count}/${vehicle.crew_max}</p>
                </div>
            `);
            
            marker.addTo(map);
            vehicleMarkers[vehicle.vehicle_callsign] = marker;
        }
    });
    
    console.log(`Ô£à ${data.vehicles.length} v├®hicules mis ├á jour`);
}

// Update toutes les 5 secondes
setInterval(updateVehicles, 5000);
updateVehicles(); // Premier chargement
```

---

## ­ƒÄ« Int├®gration Mod Arma (SQF)

### Pr├®requis
- CBA A3 install├®
- Extension C# compil├®e (`COMSPECExtension.dll`)
- Configuration CBA (URL API + token)

### ├ëtape 1 : Configuration CBA

Dans options CBA Arma 3 :

```
COMSPEC Overwatch Settings:
Ôö£ÔöÇÔöÇ Athena URL: https://athena.comspec.fr
Ôö£ÔöÇÔöÇ Access Key: your_api_key_here
ÔööÔöÇÔöÇ Community ID: 1
```

### ├ëtape 2 : Test extension

```sqf
// Test basique extension
private _result = "COMSPECExtension" callExtension ["Test", []];
systemChat format ["Extension: %1", _result];

// Si affiche "OK" ou version, extension fonctionne
```

### ├ëtape 3 : Soumettre un rapport SPOTREP

```sqf
// Fonction: comspec_overwatch_connect_fnc_submitReport
// Usage: [type, priority, summary, details] call comspec_overwatch_connect_fnc_submitReport

private _type = "SPOTREP";
private _priority = "IMMEDIATE";
private _summary = "Contact ennemi observ├®";
private _details = "Section ennemie 10 hommes, ├®quip├®e AK + RPG, direction nord";

// Construire donn├®es
private _data = createHashMap;
_data set ["report_type", _type];
_data set ["priority", _priority];
_data set ["submitter_callsign", groupId (group player)];
_data set ["pos_x", getPosWorld player select 0];
_data set ["pos_y", getPosWorld player select 1];
_data set ["grid_reference", mapGridPosition player];
_data set ["summary", _summary];
_data set ["details", _details];

// Donn├®es structur├®es SALUTE si applicable
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
    systemChat "Ô£à Rapport envoy├® au commandement";
} else {
    systemChat format ["ÔØî Erreur: %1", _result select 1];
};
```

### ├ëtape 4 : Cr├®er un POI en pointant une position

```sqf
// Fonction: comspec_overwatch_connect_fnc_createPOI
// Usage: Appel├®e depuis action terrain ou menu tablet

private _targetPos = screenToWorld [0.5, 0.5]; // Position centre ├®cran
private _targetObj = cursorTarget; // Objet point├®

if (!isNull _targetObj) then {
    private _data = createHashMap;
    _data set ["poi_name", "Cache d'armes suspecte"];
    _data set ["category", "CACHE"];
    _data set ["affiliation", "ENEMY"];
    _data set ["certainty", "POSSIBLE"];
    _data set ["pos_x", getPosWorld _targetObj select 0];
    _data set ["pos_y", getPosWorld _targetObj select 1];
    _data set ["grid_reference", mapGridPosition _targetObj];
    _data set ["description", format ["B├ótiment %1, activit├® suspecte", typeOf _targetObj]];
    _data set ["threat_level", "MEDIUM"];
    _data set ["source_type", "VISUAL"];
    _data set ["reported_by_callsign", name player];
    
    private _jsonString = [_data] call comspec_overwatch_connect_fnc_hashMapToJson;
    private _result = "COMSPECExtension" callExtension ["CreatePOI", [_jsonString]];
    
    if (_result select 0 == "OK") then {
        systemChat "Ô£à POI cr├®├® et partag├®";
        // Optionnel: placer marker local temporaire
        private _marker = createMarkerLocal [format ["poi_%1", time], getPosWorld _targetObj];
        _marker setMarkerTypeLocal "mil_warning";
        _marker setMarkerColorLocal "ColorRed";
        _marker setMarkerTextLocal "Cache suspecte";
    };
};
```

### ├ëtape 5 : Demander MEDEVAC depuis terrain

```sqf
// Fonction: comspec_overwatch_connect_fnc_requestMEDEVAC
// Usage: Appel├®e depuis ACE Medical menu ou action medic

private _patients = [
    [player, "T1", "Blessure par ├®clat thorax"],
    [unitAlpha2, "T3", "Blessure balle bras"]
];

private _data = createHashMap;
_data set ["priority", "URGENT"];
_data set ["pickup_grid", mapGridPosition player];
_data set ["pickup_pos_x", getPosWorld player select 0];
_data set ["pickup_pos_y", getPosWorld player select 1];
_data set ["radio_frequency", "30.0"];
_data set ["radio_callsign", groupId (group player)];

// Compter patients par cat├®gorie
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
    systemChat "Ô£à MEDEVAC demand├®e, standby pour extraction";
    hint "MEDEVAC EN ROUTE\nMarquez LZ avec fum├®e verte\nS├®curisez p├®rim├¿tre";
};
```

### ├ëtape 6 : Update position v├®hicule (p├®riodique)

```sqf
// Fonction p├®riodique (toutes les 5-10s) pour v├®hicules occup├®s
// ├Ç ajouter dans boucle principale mod ou event handler "GetIn"

if (vehicle player != player) then {
    private _vehicle = vehicle player;
    
    private _data = createHashMap;
    _data set ["vehicle_callsign", getText (configOf _vehicle >> "displayName")];
    _data set ["vehicle_class", typeOf _vehicle]; // Sera mapp├® c├┤t├® serveur
    _data set ["pos_x", getPosWorld _vehicle select 0];
    _data set ["pos_y", getPosWorld _vehicle select 1];
    _data set ["heading", getDir _vehicle];
    _data set ["speed", speed _vehicle];
    _data set ["fuel_percent", fuel _vehicle * 100];
    _data set ["status", if (canMove _vehicle) then {"OPERATIONAL"} else {"IMMOBILIZED"}];
    
    // Sant├® composants
    _data set ["engine_health", (_vehicle getHitPointDamage "HitEngine") * 100];
    _data set ["hull_health", (1 - damage _vehicle) * 100];
    
    // ├ëquipage
    _data set ["crew_count", count crew _vehicle];
    
    private _jsonString = [_data] call comspec_overwatch_connect_fnc_hashMapToJson;
    "COMSPECExtension" callExtension ["UpdateVehicle", [_jsonString]];
    
    // Pas de feedback visible (update silencieux)
};
```

---

## ­ƒº¬ Tests rapides

### Test 1 : Cr├®er un rapport depuis web

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
        details: 'Test API cr├®ation rapport',
        structured_data: {
            size: 'SQUAD',
            activity: 'MOVING'
        }
    };
    
    const result = await atakFetch('/reports', {
        method: 'POST',
        body: JSON.stringify(report)
    });
    
    console.log('Ô£à Rapport cr├®├®:', result);
}

testCreateReport();
```

### Test 2 : V├®rifier position dans zones

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
    
    console.log('Zones d├®tect├®es:', result.zones);
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

## ­ƒÉø Troubleshooting

### Erreur 401 Unauthorized
**Sympt├┤me** : API retourne `{"error": "Unauthorized"}`  
**Cause** : Token invalide ou manquant  
**Solution** : V├®rifier `X-ATAK-Token` header et validit├® token

### Erreur 404 Not Found
**Sympt├┤me** : `Cannot GET /api/atak/...`  
**Cause** : Route inexistante ou URL incorrecte  
**Solution** : V├®rifier URL exacte dans `docs/GUIDE-INTEGRATION-API-ATAK.md`

### Donn├®es vides []
**Sympt├┤me** : API retourne `{"reports": [], "count": 0}`  
**Cause** : Pas de donn├®es pour le tenant/context actuel  
**Solution** : Cr├®er des donn├®es de test ou v├®rifier filtres tenant/context

### Extension Arma ne r├®pond pas
**Sympt├┤me** : `callExtension` retourne cha├«ne vide  
**Cause** : DLL non charg├®e ou incompatible  
**Solution** :
1. V├®rifier `@COMSPECOverwatch/COMSPECExtension_x64.dll` existe
2. V├®rifier BattlEye autorise extension
3. Tester avec `callExtension ["Test", []]`

### V├®hicules non track├®s
**Sympt├┤me** : Aucun v├®hicule visible sur carte web  
**Cause** : Boucle update non lanc├®e ou extension non appel├®e  
**Solution** :
1. V├®rifier event handler "GetIn" actif
2. Ajouter debug `systemChat` dans boucle update
3. V├®rifier logs API c├┤t├® serveur

---

## ­ƒôÜ Ressources

### Documentation compl├¿te
- **API Reference** : `docs/GUIDE-INTEGRATION-API-ATAK.md`
- **Architecture** : `docs/SYNTHESE-TECHNIQUE-ATAK-PHASES-1-2.md`
- **Roadmap** : `docs/NOUVELLES-FEATURES-ATAK-MOD.md`
- **CHANGELOG** : `CHANGELOG-ATAK.md`

### Exemples code
- **JavaScript** : Voir section "Int├®gration interface web" dans `GUIDE-INTEGRATION-API-ATAK.md`
- **SQF** : Voir section "Int├®gration mod Arma" dans `GUIDE-INTEGRATION-API-ATAK.md`

### Support
- **Issues** : GitHub repository
- **Discord** : Canal #dev-atak (si disponible)

---

## Ô£à Checklist premiers tests

- [ ] Connexion API test├®e (curl/fetch)
- [ ] Token authentification valide
- [ ] Carte Leaflet initialis├®e
- [ ] POI charg├®s et affich├®s
- [ ] Zones affich├®es correctement
- [ ] Rapport cr├®├® depuis web
- [ ] Extension Arma r├®pond
- [ ] Rapport cr├®├® depuis Arma
- [ ] V├®hicule track├® temps r├®el
- [ ] Check position zones fonctionne

**Une fois tous les items coch├®s, vous ├¬tes pr├¬t pour l'int├®gration compl├¿te !** ­ƒÄë

---

*Guide cr├®├® : 24 juillet 2026 - Cloud Agent*
