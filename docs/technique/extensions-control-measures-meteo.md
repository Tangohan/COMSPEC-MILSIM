# Extensions config réalisme ATAK — Control Measures + Météo

Ce document décrit deux extensions ajoutées au système de configuration centralisée réalisme ATAK.

## 1. Control Measures MIL-STD-2525D / APP-6

### Contexte

Suite complète de mesures de contrôle tactique conforme à la doctrine US Army (MIL-STD-2525D) et OTAN (APP-6).

### Types de Control Measures

| Type | Préfixe | Description | Paramètres |
|------|---------|-------------|------------|
| **Axis of Advance** | AXIS | Axe d'avance nommé (NEPTUNE, MARS, etc.) | Largeur par défaut : 500m |
| **Line of Departure (LD)** | LD | Ligne de départ pour offensive | Couleur : vert (#00ff00) |
| **Limit of Advance (LOA)** | LOA | Limite de progression maximale | Couleur : rouge (#ff0000) |
| **Phase Line (PL)** | PL | Ligne de phase pour contrôle progression | Couleur : jaune (#ffff00) |
| **Objective** | OBJ | Objectif nommé (zone circulaire) | Rayon par défaut : 200m |
| **Checkpoint** | CP | Point de passage numéroté | Rayon par défaut : 50m, numérotation auto |

### Configuration JSON

**Domaine** : `control_measures`

```json
{
  "control_measures": {
    "enabled": true,
    
    "axis_naming_enabled": true,
    "axis_default_width_m": 500,
    "axis_label_prefix": "AXIS",
    
    "ld_enabled": true,
    "ld_label_prefix": "LD",
    "ld_default_color": "#00ff00",
    
    "loa_enabled": true,
    "loa_label_prefix": "LOA",
    "loa_default_color": "#ff0000",
    
    "phase_line_enabled": true,
    "phase_line_label_prefix": "PL",
    "phase_line_default_color": "#ffff00",
    
    "objective_enabled": true,
    "objective_label_prefix": "OBJ",
    "objective_default_radius_m": 200,
    
    "checkpoint_enabled": true,
    "checkpoint_label_prefix": "CP",
    "checkpoint_auto_number": true,
    "checkpoint_default_radius_m": 50,
    
    "control_measure_visibility": "team",
    "allow_edit_by_role": ["commander", "platoon_leader", "squad_leader"]
  }
}
```

### Utilisation côté web

**Création d'un control measure** :

```javascript
// Récupérer la config centralisée
const response = await fetch('/api/atak/realism/config');
const { config } = await response.json();
const cm = config.control_measures;

// Vérifier si le type est activé
if (cm.axis_naming_enabled) {
  // Créer un Axis of Advance
  createAxis({
    name: 'NEPTUNE',
    points: [...],
    width: cm.axis_default_width_m,
    visibility: cm.control_measure_visibility
  });
}
```

**Dessin sur carte** :
- Clic + tracé pour lignes (LD, LOA, PL, Axis)
- Clic + placement pour points/zones (OBJ, CP)
- Nommage dans popup après tracé
- Sauvegarde dans table `atak_control_measures` (à créer Phase 3)

### Utilisation côté mod (SQF)

```sqf
// Récupérer config via Extension C#
_config = "GetRealismConfig" callExtension "";
_configJson = parseSimpleArray _config;
_controlMeasures = _configJson select "control_measures";

// Créer un objectif
if (_controlMeasures get "objective_enabled") then {
    _radius = _controlMeasures get "objective_default_radius_m";
    [_pos, "OBJ HOTEL", _radius] call comspec_athena_atak_fnc_createObjective;
};
```

### Base de données (à créer Phase 3)

```sql
CREATE TABLE atak_control_measures (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    map_id INT UNSIGNED NOT NULL,
    measure_type VARCHAR(32) NOT NULL, -- 'axis', 'ld', 'loa', 'phase_line', 'objective', 'checkpoint'
    label VARCHAR(64) NOT NULL, -- 'AXIS NEPTUNE', 'LD RED', 'OBJ HOTEL', 'CP 1'
    geometry JSON NOT NULL, -- GeoJSON : LineString pour lignes, Polygon pour zones, Point pour checkpoints
    color VARCHAR(7) DEFAULT NULL, -- '#ff0000'
    width_m INT DEFAULT NULL, -- largeur axis
    radius_m INT DEFAULT NULL, -- rayon objectif/checkpoint
    visibility VARCHAR(16) DEFAULT 'team', -- 'public', 'team', 'private'
    created_by INT UNSIGNED,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_cm_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_cm_map FOREIGN KEY (map_id) REFERENCES atak_maps (id) ON DELETE CASCADE,
    CONSTRAINT fk_cm_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
    KEY idx_cm_tenant_map_type (tenant_id, map_id, measure_type)
);
```

---

## 2. Effet météo sur les communications radio/relais

### Contexte

Le HUD ATAK affiche déjà la météo en temps réel (pluie, brouillard, vent). Ces données peuvent influencer la portée et le débit des relais radio pour plus de réalisme.

### Effets météo

| Condition | Impact portée | Impact débit | Paramètre |
|-----------|---------------|--------------|-----------|
| **Pluie** | -15% | -10% | `rain_range_multiplier: 0.85` |
| **Brouillard** | -30% | -20% | `fog_range_multiplier: 0.70` |
| **Orage** | -40% | -35% | `storm_range_multiplier: 0.60` |
| **Vent fort** | Variable | N/A | Seuil 50 km/h, -5%/10km/h au-delà |

### Configuration JSON

**Domaine** : `radio_relays` (extension)

```json
{
  "radio_relays": {
    "link_via_relays": false,
    "relay_range_m": 2000,
    
    "weather_effects_enabled": true,
    "rain_range_multiplier": 0.85,
    "fog_range_multiplier": 0.70,
    "storm_range_multiplier": 0.60,
    "rain_throughput_multiplier": 0.90,
    "fog_throughput_multiplier": 0.80,
    "storm_throughput_multiplier": 0.65,
    "wind_threshold_kmh": 50,
    "wind_range_penalty_per_10kmh": 0.05
  }
}
```

### Calcul de portée effective

**Formule** :

```
portée_effective = portée_base × météo_multiplier × vent_multiplier
```

**Exemple** : Relais 2000m, pluie + vent 70 km/h

```
météo_multiplier = 0.85 (pluie)
vent_over_threshold = 70 - 50 = 20 km/h
vent_penalties = floor(20 / 10) = 2
vent_multiplier = 1 - (2 × 0.05) = 0.90

portée_effective = 2000 × 0.85 × 0.90 = 1530m
```

### Implémentation côté mod (SQF)

**Lecture météo Arma 3** :

```sqf
// Données météo disponibles
_rain = rain; // 0-1
_fog = fogParams select 0; // 0-1
_overcast = overcast; // 0-1
_windSpeed = windSpeed; // m/s
_windSpeedKmh = _windSpeed * 3.6;

// Détecter orage (pluie + couverture nuageuse élevée)
_isStorm = (_rain > 0.5) && (_overcast > 0.7);

// Calculer multiplicateur météo
_weatherMultiplier = 1.0;
if (_isStorm) then {
    _weatherMultiplier = _config get "storm_range_multiplier"; // 0.60
} else {
    if (_rain > 0.3) then {
        _weatherMultiplier = _config get "rain_range_multiplier"; // 0.85
    };
    if (_fog > 0.5) then {
        _weatherMultiplier = _weatherMultiplier min (_config get "fog_range_multiplier"); // 0.70
    };
};

// Calculer multiplicateur vent
_windThreshold = _config get "wind_threshold_kmh"; // 50
_windPenalty = _config get "wind_range_penalty_per_10kmh"; // 0.05
_windMultiplier = 1.0;
if (_windSpeedKmh > _windThreshold) then {
    _windOver = _windSpeedKmh - _windThreshold;
    _windPenalties = floor (_windOver / 10);
    _windMultiplier = 1.0 - (_windPenalties * _windPenalty);
    _windMultiplier = _windMultiplier max 0.5; // cap à -50%
};

// Portée effective
_relayRange = _config get "relay_range_m"; // 2000
_effectiveRange = _relayRange * _weatherMultiplier * _windMultiplier;

// Appliquer au relais
[_relay, _effectiveRange] call comspec_athena_atak_fnc_updateRelayRange;
```

**Fonction de mise à jour périodique** :

```sqf
// fn_updateWeatherEffects.sqf
// Appelé toutes les 60 secondes depuis fn_syncAtakRealism

params ["_config"];

if !(_config get "weather_effects_enabled") exitWith {};

{
    private _relay = _x;
    private _baseRange = _relay getVariable ["comspec_relay_base_range", 2000];
    
    // Calculer portée effective avec météo
    private _effectiveRange = [_baseRange, _config] call comspec_athena_atak_fnc_calculateWeatherRange;
    
    // Mettre à jour le relais
    _relay setVariable ["comspec_relay_effective_range", _effectiveRange, true];
    
    // Notifier le serveur
    ["UpdateRelay", [
        _relay getVariable "comspec_relay_uid",
        _effectiveRange,
        // ... autres params
    ]] call comspec_athena_fnc_callExtension;
    
} forEach (allMissionObjects "comspec_relay");
```

### Implémentation côté web (PHP/JS)

**Calcul côté serveur** (optionnel, pour validation) :

```php
// AtakRelayRepository::calculateEffectiveRange()
public function calculateEffectiveRange(int $tenantId, float $baseRange, array $weather): float
{
    $configRepo = new AtakRealismConfigRepository();
    $config = $configRepo->getActiveConfig($tenantId);
    $radioConfig = json_decode($config['config_json'], true)['radio_relays'];
    
    if (!$radioConfig['weather_effects_enabled']) {
        return $baseRange;
    }
    
    $weatherMult = 1.0;
    if ($weather['storm']) {
        $weatherMult = $radioConfig['storm_range_multiplier'];
    } elseif ($weather['rain'] > 0.3) {
        $weatherMult = $radioConfig['rain_range_multiplier'];
    }
    if ($weather['fog'] > 0.5) {
        $weatherMult = min($weatherMult, $radioConfig['fog_range_multiplier']);
    }
    
    $windMult = 1.0;
    $windKmh = $weather['wind_kmh'];
    if ($windKmh > $radioConfig['wind_threshold_kmh']) {
        $windOver = $windKmh - $radioConfig['wind_threshold_kmh'];
        $penalties = floor($windOver / 10);
        $windMult = 1.0 - ($penalties * $radioConfig['wind_range_penalty_per_10kmh']);
        $windMult = max(0.5, $windMult);
    }
    
    return $baseRange * $weatherMult * $windMult;
}
```

**Affichage Tacmap** :

```javascript
// Indicateur météo sur les relais
function updateRelayWeatherIndicator(relay, weather) {
  const config = await fetchRealismConfig();
  const radioConfig = config.radio_relays;
  
  if (!radioConfig.weather_effects_enabled) return;
  
  let weatherIcon = '';
  let weatherClass = '';
  
  if (weather.storm) {
    weatherIcon = '⛈️';
    weatherClass = 'weather-storm';
  } else if (weather.rain > 0.3) {
    weatherIcon = '🌧️';
    weatherClass = 'weather-rain';
  } else if (weather.fog > 0.5) {
    weatherIcon = '🌫️';
    weatherClass = 'weather-fog';
  }
  
  if (weather.wind_kmh > radioConfig.wind_threshold_kmh) {
    weatherIcon += '💨';
    weatherClass += ' weather-wind';
  }
  
  // Afficher l'icône sur la carte
  relay.marker.setTooltipContent(
    `${relay.name} ${weatherIcon}<br>` +
    `Portée: ${relay.effective_range}m (${relay.base_range}m nominal)`
  );
  relay.marker.addClass(weatherClass);
}
```

---

## Intégration avec le plan de centralisation

Ces deux extensions s'intègrent naturellement dans le système de configuration centralisée :

### Phase 1 ✅ (déjà faite + ces extensions)
- Table `atak_realism_config` inclut déjà les deux nouveaux domaines
- API GET `/api/atak/realism/config` expose la config complète
- Seed migre les valeurs par défaut

### Phase 2 ✅ (déjà faite + ces extensions)
- Onglet "Relais radio" étendu avec section météo
- Nouvel onglet "Control Measures" (10ème onglet)
- Validation des nouveaux paramètres

### Phase 3 (à faire)
- **Extension C#** : Lire `control_measures` et `radio_relays.weather_*`
- **SQF** :
  - `fn_updateWeatherEffects.sqf` (nouveau)
  - `fn_createControlMeasure.sqf` (nouveau)
  - `fn_calculateWeatherRange.sqf` (nouveau)
  - Refactor `fn_placeAtakRelay.sqf` pour inclure météo
- **Web JS** :
  - Outil de dessin control measures (toolbar)
  - Affichage indicateurs météo sur relais
  - Sauvegarde control measures en DB

### Phase 4 (à faire)
- Table `atak_control_measures` (création)
- Documentation complète schéma JSON incluant ces domaines
- Tests E2E : créer control measure, vérifier effet météo sur relais

---

## Voir aussi

- [Glossaire réalisme ATAK](./glossaire-realisme-atak.md)
- [Audit complet paramètres réalisme](./audit-realisme-complet-proposition-centralisation.md)
- MIL-STD-2525D (symbologie militaire US)
- APP-6 (symbologie OTAN)
