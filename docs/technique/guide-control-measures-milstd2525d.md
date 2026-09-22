# Control Measures MIL-STD-2525D — Guide d'implémentation

## Vue d'ensemble

Ce document décrit l'implémentation complète des control measures MIL-STD-2525D dans ATHENA C2, permettant de tracer, nommer et synchroniser des symboles de commandement tactique conformes à la doctrine militaire US Army et OTAN.

## Types de Control Measures implémentés

### 1. Axis of Advance (Axe d'attaque)

**Usage** : Indique la direction et le couloir d'avancement d'une unité.

**Configuration** :
```json
{
  "axis_naming_enabled": true,
  "axis_default_width_m": 500,
  "axis_label_prefix": "AXIS"
}
```

**Représentation SQF** :
- Polyline de markers (jaune)
- Largeur configurée (couloir de mouvement)
- Nom de code libre (ex: "AXIS NEPTUNE", "AXIS MARS")

**Représentation Web** :
- Polyline avec bordures pour indiquer la largeur
- Label au centre de l'axe
- Couleur : #FFD700 (gold)

**Symbologie MIL-STD-2525D** : `G*GPOLAA---****X` (Axis of Advance)

---

### 2. Line of Departure (LD, Ligne de départ)

**Usage** : Marque la ligne depuis laquelle l'attaque démarre.

**Configuration** :
```json
{
  "ld_enabled": true,
  "ld_label_prefix": "LD",
  "ld_default_color": "#00ff00"
}
```

**Représentation SQF** :
- Ligne droite ou multi-segments (verte)
- Label automatique ou nommé (ex: "LD BLUE", "LD ALPHA")

**Représentation Web** :
- Polyline verte épaisse
- Label au centre ou aux extrémités
- Couleur par défaut : #00ff00

**Symbologie MIL-STD-2525D** : `G*GPOLLD---****X` (Line of Departure)

---

### 3. Limit of Advance (LOA, Limite de progression)

**Usage** : Marque la ligne au-delà de laquelle une unité ne doit pas progresser sans ordre.

**Configuration** :
```json
{
  "loa_enabled": true,
  "loa_label_prefix": "LOA",
  "loa_default_color": "#ff0000"
}
```

**Représentation SQF** :
- Ligne droite ou multi-segments (rouge)
- Label automatique ou nommé (ex: "LOA RED", "LOA BRAVO")

**Représentation Web** :
- Polyline rouge épaisse
- Style : tirets longs
- Label au centre

**Symbologie MIL-STD-2525D** : `G*GPOLLA---****X` (Limit of Advance)

---

### 4. Phase Lines (Lignes de phase)

**Usage** : Marque les phases successives d'une opération.

**Configuration** :
```json
{
  "phase_line_enabled": true,
  "phase_line_label_prefix": "PL",
  "phase_line_default_color": "#ffff00"
}
```

**Représentation SQF** :
- Ligne droite ou multi-segments (jaune)
- Label obligatoire avec nom de phase (ex: "PL ORANGE", "PL GOLD")

**Représentation Web** :
- Polyline jaune épaisse
- Style : tirets courts
- Label au centre

**Symbologie MIL-STD-2525D** : `G*GPOLLP---****X` (Phase Line)

---

### 5. Objectives (Objectifs)

**Usage** : Zone ou point à capturer/sécuriser.

**Configuration** :
```json
{
  "objective_enabled": true,
  "objective_label_prefix": "OBJ",
  "objective_default_radius_m": 200
}
```

**Représentation SQF** :
- Cercle coloré avec label central
- Rayon configurable (50-2000m)
- Nom de code libre (ex: "OBJ WOLF", "OBJ TIGER")

**Représentation Web** :
- Cercle avec remplissage semi-transparent
- Bordure épaisse
- Label au centre
- Couleur par défaut : #ff6600

**Symbologie MIL-STD-2525D** : `G*GPOLEO---****X` (Objective)

---

### 6. Checkpoints (Points de contrôle)

**Usage** : Points de repère pour la navigation et les rapports.

**Configuration** :
```json
{
  "checkpoint_enabled": true,
  "checkpoint_label_prefix": "CP",
  "checkpoint_auto_number": true,
  "checkpoint_default_radius_m": 50
}
```

**Représentation SQF** :
- Marker circulaire ou carré
- Auto-numérotation (CP1, CP2...) ou nom libre
- Rayon d'influence configurable (10-500m)

**Représentation Web** :
- Icône spécifique (drapeau ou marqueur)
- Cercle d'influence en pointillés
- Label toujours visible
- Couleur par défaut : #0099ff

**Symbologie MIL-STD-2525D** : `G*GPIGPC---****X` (Checkpoint)

---

## Structure de données

### Format JSON (stockage API)

```json
{
  "type": "control_measure",
  "subtype": "axis|ld|loa|phase_line|objective|checkpoint",
  "name": "AXIS NEPTUNE",
  "coordinates": [
    [5123.45, 2567.89, 12.3],
    [5234.56, 2678.90, 15.2]
  ],
  "properties": {
    "width_m": 500,
    "color": "#FFD700",
    "visibility": "team",
    "created_by": "user_id",
    "created_at": "2026-09-22T10:30:00Z",
    "team_id": 3,
    "editable_by": ["commander", "platoon_leader"]
  }
}
```

### Format SQF (mod Arma 3)

```sqf
_controlMeasure = [
    "axis",                    // type
    "AXIS NEPTUNE",           // nom
    [                         // coordonnées (3D positions)
        [5123.45, 2567.89, 12.3],
        [5234.56, 2678.90, 15.2]
    ],
    500,                      // largeur ou rayon (selon type)
    "#FFD700",                // couleur
    "team",                   // visibilité
    3,                        // team_id
    ["commander", "platoon_leader"] // roles autorisés à éditer
];
```

---

## API Endpoints

### 1. Lister les control measures

**Endpoint** : `GET /api/atak/control-measures`

**Query params** :
- `team_id` (int, requis) : ID de l'équipe
- `type` (string, optionnel) : Filtrer par type (`axis`, `ld`, `loa`, etc.)

**Response** :
```json
{
  "ok": true,
  "measures": [
    {
      "id": 123,
      "type": "axis",
      "name": "AXIS NEPTUNE",
      "coordinates": [...],
      "properties": {...},
      "created_at": "2026-09-22T10:30:00Z"
    }
  ]
}
```

---

### 2. Créer un control measure

**Endpoint** : `POST /api/atak/control-measures`

**Body** :
```json
{
  "type": "axis",
  "name": "AXIS NEPTUNE",
  "coordinates": [[5123.45, 2567.89, 12.3], [5234.56, 2678.90, 15.2]],
  "team_id": 3,
  "width_m": 500,
  "color": "#FFD700"
}
```

**Response** :
```json
{
  "ok": true,
  "measure_id": 123
}
```

---

### 3. Mettre à jour un control measure

**Endpoint** : `PUT /api/atak/control-measures/{id}`

**Body** : Même structure que POST

---

### 4. Supprimer un control measure

**Endpoint** : `DELETE /api/atak/control-measures/{id}`

---

## Workflow d'utilisation

### Côté Arma 3 (SQF)

1. **Placer un control measure via Zeus ou script** :
   ```sqf
   // Créer un axe d'attaque
   _axis = ["axis", "AXIS NEPTUNE", [[5123, 2567], [5234, 2678]], 500, "#FFD700", "team", 3, ["commander"]];
   
   // Envoyer au serveur via extension COMSPEC
   "COMSPECExtension" callExtension ["CreateControlMeasure", [_axis]];
   ```

2. **Synchroniser depuis le serveur** :
   ```sqf
   // Récupérer tous les control measures
   _measures = "COMSPECExtension" callExtension ["GetControlMeasures", [3]]; // team_id = 3
   
   // Parser et afficher sur carte
   {
       _type = _x select 0;
       _name = _x select 1;
       _coords = _x select 2;
       
       // Créer markers locaux
       switch (_type) do {
           case "axis": { [_coords, _name] call ATHENA_fnc_drawAxis; };
           case "objective": { [_coords, _name] call ATHENA_fnc_drawObjective; };
       };
   } forEach _measures;
   ```

---

### Côté Web (Tacmap)

1. **Dessiner un control measure** :
   ```javascript
   // Utiliser Leaflet.draw ou équivalent
   map.on('draw:created', async function(e) {
       const layer = e.layer;
       const type = document.getElementById('cm-type').value; // axis, ld, loa...
       const name = prompt('Nom du control measure:');
       
       const coordinates = layer.getLatLngs().map(ll => [ll.lat, ll.lng]);
       
       await fetch('/api/atak/control-measures', {
           method: 'POST',
           headers: { 'Content-Type': 'application/json' },
           body: JSON.stringify({
               type: type,
               name: name,
               coordinates: coordinates,
               team_id: currentTeamId
           })
       });
   });
   ```

2. **Afficher les control measures** :
   ```javascript
   async function loadControlMeasures() {
       const response = await fetch(`/api/atak/control-measures?team_id=${currentTeamId}`);
       const data = await response.json();
       
       data.measures.forEach(measure => {
           switch(measure.type) {
               case 'axis':
                   drawAxis(measure);
                   break;
               case 'objective':
                   drawObjective(measure);
                   break;
               // ... autres types
           }
       });
   }
   
   function drawAxis(measure) {
       const latlngs = measure.coordinates.map(c => L.latLng(c[0], c[1]));
       const polyline = L.polyline(latlngs, {
           color: measure.properties.color || '#FFD700',
           weight: 4
       }).addTo(map);
       
       // Ajouter label
       const midpoint = polyline.getCenter();
       L.marker(midpoint, {
           icon: L.divIcon({
               className: 'control-measure-label',
               html: `<div class="cm-label">${measure.name}</div>`
           })
       }).addTo(map);
   }
   ```

---

## Gestion des permissions

### Visibilité

- `team` : Visible uniquement par l'équipe qui l'a créé
- `faction` : Visible par toute la faction (BLUFOR, OPFOR, INDEP)
- `all` : Visible par tous les joueurs (uniquement pour briefings)

### Édition

Défini par `editable_by` : liste de rôles autorisés à modifier/supprimer.

**Rôles possibles** :
- `commander` : Chef de faction
- `platoon_leader` : Chef de peloton
- `squad_leader` : Chef de section
- `all` : Tous les membres de l'équipe

---

## Rendu MIL-STD-2525D

### Symboles tactiques

Utiliser une bibliothèque existante côté web :
- **milsymbol.js** : <https://github.com/spatialillusions/milsymbol>
- **ms-symbol-editor** : <https://github.com/Esri/military-symbology-styles>

### Exemple d'intégration

```javascript
import { ms } from 'milsymbol';

function drawMilStdSymbol(measure) {
    const sidc = getControlMeasureSIDC(measure.type);
    const symbol = new ms.Symbol(sidc, {
        size: 30,
        uniqueDesignation: measure.name
    });
    
    L.marker(measure.coordinates[0], {
        icon: L.icon({
            iconUrl: symbol.toDataURL(),
            iconSize: [30, 30]
        })
    }).addTo(map);
}

function getControlMeasureSIDC(type) {
    const sidcMap = {
        'axis': 'G*GPOLAA---****X',
        'ld': 'G*GPOLLD---****X',
        'loa': 'G*GPOLLA---****X',
        'phase_line': 'G*GPOLLP---****X',
        'objective': 'G*GPOLEO---****X',
        'checkpoint': 'G*GPIGPC---****X'
    };
    return sidcMap[type] || 'G*GPIGPC---****X';
}
```

---

## Base de données

### Table `atak_control_measures`

```sql
CREATE TABLE atak_control_measures (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    team_id INT UNSIGNED NOT NULL,
    map_id INT UNSIGNED NOT NULL,
    type ENUM('axis','ld','loa','phase_line','objective','checkpoint') NOT NULL,
    name VARCHAR(100) NOT NULL,
    coordinates JSON NOT NULL COMMENT 'Array of [x,y,z] positions',
    properties JSON NOT NULL COMMENT 'width, color, visibility, editable_by, etc.',
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_cm_tenant_team (tenant_id, team_id),
    KEY idx_cm_map (map_id),
    KEY idx_cm_type (type),
    CONSTRAINT fk_cm_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_cm_team FOREIGN KEY (team_id) REFERENCES atak_teams (id) ON DELETE CASCADE,
    CONSTRAINT fk_cm_map FOREIGN KEY (map_id) REFERENCES atak_maps (id) ON DELETE CASCADE,
    CONSTRAINT fk_cm_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Control measures MIL-STD-2525D pour commandement tactique';
```

---

## Tests

### Tests unitaires (PHP)

```php
public function testCreateAxisOfAdvance()
{
    $repo = new AtakControlMeasuresRepository();
    $measureId = $repo->create(1, 3, 1, 'axis', 'AXIS NEPTUNE', [
        [5123.45, 2567.89, 12.3],
        [5234.56, 2678.90, 15.2]
    ], ['width_m' => 500, 'color' => '#FFD700'], 1);
    
    $this->assertGreaterThan(0, $measureId);
    
    $measure = $repo->getById($measureId);
    $this->assertEquals('axis', $measure['type']);
    $this->assertEquals('AXIS NEPTUNE', $measure['name']);
}
```

### Tests E2E (Manuel)

1. Créer un axe via Zeus (module custom)
2. Vérifier synchronisation sur Tacmap web
3. Modifier le nom depuis Tacmap
4. Vérifier mise à jour en jeu (markers)
5. Supprimer depuis web
6. Vérifier disparition en jeu

---

## Documentation utilisateur

### Guide opérateur

**Créer un axe d'attaque (Zeus)** :
1. Ouvrir Zeus
2. Module ATHENA > Control Measures > Axis of Advance
3. Placer les points sur la carte (double-clic pour terminer)
4. Entrer le nom de code (ex: "NEPTUNE")
5. Valider → synchronisation automatique

**Voir les control measures (Tacmap)** :
1. Ouvrir Tacmap dans le navigateur
2. Panneau latéral > Control Measures
3. Les symboles s'affichent sur la carte
4. Cliquer pour voir détails/modifier (si autorisé)

---

## Roadmap

### Phase 3 actuelle
- Implémentation base de données
- API CRUD complète
- Affichage web de base (polylines/cercles simples)

### Phase future
- Rendu MIL-STD-2525D complet avec milsymbol.js
- Interface de dessin avancée (snap to grid, terrain-aware)
- Export/import KML pour interopérabilité
- Historique des modifications (audit trail)
- Templates prédéfinis (schémas de manœuvre classiques)

---

## Références

- **MIL-STD-2525D** : <https://www.jcs.mil/Portals/36/Documents/Doctrine/Other_Pubs/ms_2525d.pdf>
- **APP-6D (OTAN)** : Équivalent OTAN de MIL-STD-2525D
- **milsymbol.js** : <https://github.com/spatialillusions/milsymbol>
- **Arma 3 Scripting Commands** : <https://community.bistudio.com/wiki/Category:Scripting_Commands_Arma_3>
