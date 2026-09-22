# Améliorations UX et Architecture — Réalisme ATAK

## Vue d'ensemble

Ce document décrit 4 améliorations majeures demandées par l'utilisateur pour simplifier l'utilisation et la maintenance de la configuration réalisme ATAK.

---

## 1. Simplicité admin + joueur : Deux niveaux d'UI

### 1.1 Admin : UI générée depuis schéma JSON

**Principe** : L'écran admin 10 onglets ne doit JAMAIS exposer du JSON brut. Tous les champs doivent être générés automatiquement depuis le schéma JSON avec des contrôles appropriés.

**Schéma JSON étendu** :

```json
{
  "schema_version": "1.0.0",
  "domains": {
    "radio_relays": {
      "label": "📡 Relais radio",
      "description": "Configuration des relais radio et communications",
      "parameters": {
        "relay_range_m": {
          "label": "Portée des relais",
          "description": "Distance maximale de transmission d'un relais radio",
          "type": "slider",
          "unit": "m",
          "min": 50,
          "max": 8000,
          "step": 50,
          "default": 2000,
          "help": "Portée nominale d'un relais. Affectée par la météo si activée."
        },
        "weather_effects_enabled": {
          "label": "Effets météo activés",
          "description": "La météo impacte la portée et le débit",
          "type": "toggle",
          "default": true,
          "help": "Pluie, brouillard et vent réduisent les performances radio."
        },
        "rain_range_multiplier": {
          "label": "Multiplicateur pluie",
          "description": "Facteur de réduction sous la pluie",
          "type": "slider",
          "unit": "%",
          "min": 0,
          "max": 1,
          "step": 0.05,
          "default": 0.85,
          "help": "0.85 = 85% de la portée nominale",
          "depends_on": "weather_effects_enabled"
        }
      }
    }
  },
  "profiles": {
    "beginner": {
      "label": "🟢 Débutant (arcade)",
      "description": "Expérience simplifiée, pas de contraintes réalistes",
      "overrides": {
        "radio_relays": {
          "link_via_relays": false,
          "weather_effects_enabled": false,
          "relay_range_m": 5000
        },
        "certificates": {
          "certificate_required": false
        },
        "terminal_damage": {
          "terminal_damage_enabled": false
        }
      }
    },
    "expert": {
      "label": "🔴 Expert (simulation)",
      "description": "Réalisme maximal, contraintes militaires réelles",
      "overrides": {
        "radio_relays": {
          "link_via_relays": true,
          "weather_effects_enabled": true,
          "relay_range_m": 2000
        },
        "certificates": {
          "certificate_required": true,
          "certificate_duration_days": 180
        },
        "terminal_damage": {
          "terminal_damage_enabled": true,
          "hits_before_destruction": 2
        }
      }
    },
    "event": {
      "label": "🟡 Événement (équilibré)",
      "description": "Compromis entre réalisme et accessibilité",
      "overrides": {
        "radio_relays": {
          "link_via_relays": true,
          "weather_effects_enabled": true,
          "relay_range_m": 3000
        },
        "certificates": {
          "certificate_required": true,
          "certificate_duration_days": 365
        },
        "terminal_damage": {
          "terminal_damage_enabled": true,
          "hits_before_destruction": 5
        }
      }
    }
  }
}
```

**Génération UI automatique** :

```php
// Exemple de génération de champ depuis schéma
foreach ($schema['domains']['radio_relays']['parameters'] as $key => $param) {
    $value = $config['radio_relays'][$key] ?? $param['default'];
    
    echo '<div class="form-field">';
    echo '<label>' . htmlspecialchars($param['label']) . '</label>';
    
    switch ($param['type']) {
        case 'slider':
            echo '<input type="range" 
                    name="radio_relays.' . $key . '" 
                    min="' . $param['min'] . '" 
                    max="' . $param['max'] . '" 
                    step="' . $param['step'] . '" 
                    value="' . $value . '">';
            echo '<span class="value">' . $value . ' ' . $param['unit'] . '</span>';
            break;
            
        case 'toggle':
            $checked = $value ? 'checked' : '';
            echo '<input type="checkbox" 
                    name="radio_relays.' . $key . '" 
                    value="1" ' . $checked . '>';
            break;
    }
    
    if (!empty($param['help'])) {
        echo '<span class="help-icon" data-tooltip="' . htmlspecialchars($param['help']) . '">?</span>';
    }
    
    echo '</div>';
}
```

**Sélecteur de profil** :

```php
<div class="profile-selector">
    <h3>⚡ Configuration rapide</h3>
    <p>Choisissez un profil pré-configuré ou personnalisez manuellement :</p>
    
    <div class="profiles">
        <?php foreach ($schema['profiles'] as $profileKey => $profile): ?>
        <button class="profile-btn" data-profile="<?= $profileKey ?>">
            <h4><?= $profile['label'] ?></h4>
            <p><?= $profile['description'] ?></p>
        </button>
        <?php endforeach; ?>
    </div>
    
    <button id="btn-custom-config" class="secondary">
        🔧 Configuration personnalisée
    </button>
</div>
```

**JavaScript pour application profil** :

```javascript
document.querySelectorAll('.profile-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
        const profile = btn.dataset.profile;
        
        if (confirm(`Appliquer le profil "${btn.querySelector('h4').textContent}" ? Cela écrasera votre configuration actuelle.`)) {
            const response = await fetch('/api/atak/realism/apply-profile', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ profile: profile })
            });
            
            if (response.ok) {
                location.reload();
            }
        }
    });
});
```

---

### 1.2 Joueur : Vue contextuelle réduite

**Principe** : Un joueur ne voit QUE les infos relatives à SES objets/actions, jamais la "config" globale.

**Exemples de vues joueur** :

#### A. Joueur qui a posé un relais

**Page** : `/atak/my-relays` ou panneau latéral Tacmap

```php
<div class="player-relay-view">
    <h3>Mes relais radio</h3>
    
    <?php foreach ($playerRelays as $relay): ?>
    <div class="relay-card">
        <div class="relay-header">
            <span class="relay-name"><?= $relay['name'] ?></span>
            <span class="relay-status <?= $relay['alive'] ? 'online' : 'offline' ?>">
                <?= $relay['alive'] ? '🟢 En ligne' : '🔴 Hors ligne' ?>
            </span>
        </div>
        
        <div class="relay-stats">
            <div class="stat">
                <label>Portée</label>
                <span class="value"><?= $relay['effective_range'] ?>m</span>
                <?php if ($relay['weather_impact']): ?>
                <span class="weather-badge"><?= $relay['weather_description'] ?></span>
                <?php endif; ?>
            </div>
            
            <div class="stat">
                <label>Connexions</label>
                <span class="value"><?= $relay['used_slots'] ?> / <?= $relay['max_slots'] ?></span>
            </div>
            
            <div class="stat">
                <label>Certificat</label>
                <span class="value <?= $relay['cert_expires_soon'] ? 'warning' : '' ?>">
                    <?php if ($relay['has_valid_cert']): ?>
                        ✅ Valide (<?= $relay['cert_days_left'] ?>j)
                    <?php else: ?>
                        ❌ Expiré
                    <?php endif; ?>
                </span>
            </div>
        </div>
        
        <div class="relay-actions">
            <button class="btn-repair" data-relay-id="<?= $relay['id'] ?>">
                🔧 Réparer
            </button>
            <button class="btn-renew-cert" data-relay-id="<?= $relay['id'] ?>">
                🔑 Renouveler certificat
            </button>
        </div>
    </div>
    <?php endforeach; ?>
</div>
```

**Pas de config exposée** : Le joueur voit "Portée 1700m (🌧️ Pluie 85%)" mais ne sait pas que `rain_range_multiplier=0.85` existe.

#### B. Joueur avec terminal ATAK

**HUD en jeu ou widget Tacmap** :

```
┌─────────────────────────────────────┐
│ Terminal ATAK — Statut              │
├─────────────────────────────────────┤
│ 🟢 Opérationnel                     │
│                                      │
│ Relais le plus proche : 450m        │
│ Liaison : ✅ Stable                 │
│ Certificat : ✅ Valide (120j)       │
│                                      │
│ [📡 Chercher relais]                │
└─────────────────────────────────────┘
```

Si problème météo :

```
┌─────────────────────────────────────┐
│ Terminal ATAK — Statut              │
├─────────────────────────────────────┤
│ 🟡 Dégradé                          │
│                                      │
│ Relais le plus proche : 450m        │
│ Liaison : ⚠️ Réduite (🌧️ Pluie)    │
│ Débit : 220 kbps (- 15%)            │
│ Certificat : ✅ Valide (120j)       │
│                                      │
│ [📡 Chercher relais]                │
└─────────────────────────────────────┘
```

**Actions joueur** (2-3 max par contexte) :
- Chercher relais
- Renouveler certificat (si expiré)
- Réparer terminal (si endommagé)

---

## 2. Modifier depuis le web la config des objets déjà posés

### 2.1 Distinction modèle de données

**Principe** : Séparer les **défauts globaux** des **overrides par instance**.

#### Table existante : `atak_realism_config` (défauts globaux)

```sql
-- Ce qui existe déjà
CREATE TABLE atak_realism_config (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    config_json JSON NOT NULL,
    -- ...
    PRIMARY KEY (id)
);
```

#### Nouvelle table : `atak_relay_overrides` (overrides par relais)

```sql
CREATE TABLE atak_relay_overrides (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    relay_uid VARCHAR(64) NOT NULL COMMENT 'UID du relais (objet en jeu)',
    override_json JSON NOT NULL COMMENT 'Paramètres surchargés : {range_m: 5000, ...}',
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at DATETIME DEFAULT NULL COMMENT 'Override temporaire (boost mission)',
    PRIMARY KEY (id),
    UNIQUE KEY uk_relay_override (tenant_id, relay_uid),
    KEY idx_relay_override_expires (expires_at),
    CONSTRAINT fk_relay_override_tenant FOREIGN KEY (tenant_id) 
        REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Overrides par relais (boost temporaire ou permanent)';
```

**Exemple override** :

```json
{
  "range_m": 5000,
  "reason": "Relais stratégique pour mission NEPTUNE",
  "boosted_by_admin": true,
  "boost_until": "2026-09-23T18:00:00Z"
}
```

#### Table existante : `atak_relays` (déjà présente d'après audit)

Compléter avec champs nécessaires :

```sql
ALTER TABLE atak_relays ADD COLUMN has_override BOOLEAN NOT NULL DEFAULT FALSE AFTER alive;
ALTER TABLE atak_relays ADD INDEX idx_relay_has_override (tenant_id, has_override);
```

---

### 2.2 Mécanisme de push temps réel

**Problème** : Modifier depuis le web doit se refléter en jeu immédiatement.

**Solutions** :

#### Option A : Polling côté Extension C# (recommandé)

**Extension C#** poll l'API toutes les 10-30 secondes pour les objets actifs :

```csharp
// Extension.cs
private async Task SyncRelayOverrides()
{
    // Liste des relais actifs en jeu (UIDs)
    List<string> activeRelayUids = GetActiveRelayUids(); // À implémenter
    
    if (activeRelayUids.Count == 0) return;
    
    // Appel API
    string endpoint = $"{apiBaseUrl}/api/atak/relays/overrides?uids={string.Join(",", activeRelayUids)}";
    HttpResponseMessage response = await httpClient.GetAsync(endpoint);
    
    if (!response.IsSuccessStatusCode) return;
    
    string jsonResponse = await response.Content.ReadAsStringAsync();
    var overrides = JsonSerializer.Deserialize<Dictionary<string, RelayOverride>>(jsonResponse);
    
    // Appliquer les overrides via SQF
    foreach (var kvp in overrides)
    {
        string relayUid = kvp.Key;
        var overrideData = kvp.Value;
        
        // Appeler fonction SQF pour mettre à jour l'objet
        string sqfCommand = $"['{relayUid}', {overrideData.RangeM}, {overrideData.MaxSlots}] call ATHENA_fnc_updateRelayFromWeb;";
        ArmaExtension.CallSQF(sqfCommand);
    }
}

// Appel périodique (toutes les 15 secondes)
private Timer _syncTimer;

public void StartSyncLoop()
{
    _syncTimer = new Timer(async _ => await SyncRelayOverrides(), null, 0, 15000);
}
```

**API endpoint** :

```php
// app/Controllers/Api/AtakRelayApiController.php
public function getOverrides(Request $request, array $params = []): Response
{
    $tenantId = $this->resolveTenantId($request);
    $uids = explode(',', $request->query('uids', ''));
    
    if (empty($uids)) {
        return Response::json(['ok' => false, 'error' => 'No relay UIDs provided'], 422);
    }
    
    $overrides = [];
    foreach ($uids as $uid) {
        $override = $this->relayOverrideRepo->getByUid($tenantId, trim($uid));
        if ($override !== null) {
            $overrides[trim($uid)] = json_decode($override['override_json'], true);
        }
    }
    
    return Response::json(['ok' => true, 'overrides' => $overrides]);
}
```

**Fonction SQF** :

```sqf
// fn_updateRelayFromWeb.sqf
params ["_relayUid", "_newRange", "_newSlots"];

private _relay = ATHENA_activeRelays getVariable [_relayUid, objNull];
if (isNull _relay) exitWith {};

// Appliquer nouveaux paramètres
_relay setVariable ["ATHENA_range", _newRange, true];
_relay setVariable ["ATHENA_maxSlots", _newSlots, true];

// Notification joueur propriétaire
private _owner = _relay getVariable ["ATHENA_owner", objNull];
if (!isNull _owner) then {
    [
        format["📡 Relais %1 : Portée boostée à %2m par le commandement", _relayUid, _newRange],
        "info"
    ] remoteExec ["systemChat", _owner];
};

diag_log format["[ATHENA] Relay %1 updated from web: range=%2m slots=%3", _relayUid, _newRange, _newSlots];
```

#### Option B : WebSocket (futur, plus complexe)

Pour des updates instantanées, un serveur WebSocket peut push les changements :

```javascript
// Tacmap web
const ws = new WebSocket('wss://athena.com/ws');

ws.onmessage = (event) => {
    const data = JSON.parse(event.data);
    
    if (data.type === 'relay_override_updated') {
        updateRelayMarker(data.relay_uid, data.new_range);
    }
};
```

**Recommandation** : Option A (polling 15s) suffit pour 99% des cas. WebSocket pour Phase future si besoin temps réel strict.

---

### 2.3 Interface web pour overrides

**Page admin** : `/admin/atak/relays` (existante, à enrichir)

```php
<section class="relay-overrides">
    <h2>Booster un relais</h2>
    
    <table>
        <thead>
            <tr>
                <th>Relais</th>
                <th>Portée actuelle</th>
                <th>Portée boostée</th>
                <th>Expiration</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($relays as $relay): ?>
            <tr>
                <td><?= $relay['name'] ?> (<?= $relay['uid'] ?>)</td>
                <td><?= $relay['base_range'] ?>m</td>
                <td>
                    <?php if ($relay['has_override']): ?>
                        <span class="boosted"><?= $relay['override_range'] ?>m</span>
                    <?php else: ?>
                        <span class="default">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($relay['override_expires_at']): ?>
                        <?= $relay['override_expires_at'] ?>
                    <?php else: ?>
                        Permanent
                    <?php endif; ?>
                </td>
                <td>
                    <button class="btn-boost" data-relay-uid="<?= $relay['uid'] ?>">
                        ⚡ Booster
                    </button>
                    <?php if ($relay['has_override']): ?>
                    <button class="btn-remove-boost" data-relay-uid="<?= $relay['uid'] ?>">
                        ❌ Retirer boost
                    </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>
```

**Modal boost** :

```html
<div id="modal-boost" class="modal">
    <h3>Booster le relais</h3>
    
    <form id="form-boost">
        <input type="hidden" name="relay_uid" id="boost-relay-uid">
        
        <div class="form-field">
            <label>Nouvelle portée (m)</label>
            <input type="number" name="range_m" min="50" max="12000" value="5000">
        </div>
        
        <div class="form-field">
            <label>Durée du boost</label>
            <select name="duration">
                <option value="permanent">Permanent</option>
                <option value="1h">1 heure</option>
                <option value="4h">4 heures</option>
                <option value="24h">24 heures</option>
                <option value="mission">Durée de la mission</option>
            </select>
        </div>
        
        <div class="form-field">
            <label>Raison</label>
            <input type="text" name="reason" placeholder="Ex: Relais stratégique pour op NEPTUNE">
        </div>
        
        <button type="submit">Appliquer boost</button>
    </form>
</div>
```

---

## 3. Peu de code à toucher pour ajouter un paramètre

### 3.1 Architecture : Schéma JSON comme source unique

**Principe** : Ajouter un paramètre = **1 seule modification** dans le schéma JSON, tout le reste est généré automatiquement.

#### Fichier schéma : `config/realism-schema.json`

```json
{
  "schema_version": "1.0.0",
  "domains": {
    "radio_relays": {
      "label": "📡 Relais radio",
      "parameters": {
        "relay_range_m": {
          "label": "Portée des relais",
          "type": "slider",
          "unit": "m",
          "min": 50,
          "max": 8000,
          "step": 50,
          "default": 2000,
          "help": "Distance maximale de transmission",
          "validation": {
            "min": 50,
            "max": 8000
          }
        },
        
        // Nouveau paramètre : ajouter UNIQUEMENT ces lignes
        "relay_power_consumption_w": {
          "label": "Consommation électrique",
          "type": "slider",
          "unit": "W",
          "min": 10,
          "max": 500,
          "step": 10,
          "default": 100,
          "help": "Consommation électrique du relais (impact durée batterie)",
          "validation": {
            "min": 10,
            "max": 500
          }
        }
      }
    }
  }
}
```

**C'est tout.** Le reste est automatique.

---

### 3.2 Endpoint API générique

**Route unique** :

```php
// routes/web.php
$router->get('/api/atak/realism/config/{domain}/{key}', [AtakRealismApiController::class, 'getParam']);
$router->post('/api/atak/realism/config/{domain}/{key}', [AtakRealismApiController::class, 'setParam']);
```

**Controller générique** :

```php
// app/Controllers/Api/AtakRealismApiController.php
public function getParam(Request $request, array $params = []): Response
{
    $tenantId = $this->resolveTenantId($request);
    $domain = $params['domain'] ?? '';
    $key = $params['key'] ?? '';
    
    $config = $this->realismConfigRepo->getActiveConfig($tenantId);
    $configJson = json_decode($config['config_json'], true);
    
    if (!isset($configJson[$domain][$key])) {
        return Response::json(['ok' => false, 'error' => 'Parameter not found'], 404);
    }
    
    return Response::json([
        'ok' => true,
        'domain' => $domain,
        'key' => $key,
        'value' => $configJson[$domain][$key],
    ]);
}

public function setParam(Request $request, array $params = []): Response
{
    $tenantId = $this->resolveTenantId($request);
    $domain = $params['domain'] ?? '';
    $key = $params['key'] ?? '';
    
    $body = $this->body($request);
    $newValue = $body['value'] ?? null;
    
    // Validation depuis schéma
    $schema = $this->loadSchema();
    $paramDef = $schema['domains'][$domain]['parameters'][$key] ?? null;
    
    if ($paramDef === null) {
        return Response::json(['ok' => false, 'error' => 'Parameter not defined in schema'], 404);
    }
    
    // Validation automatique
    $validation = $paramDef['validation'] ?? [];
    if (isset($validation['min']) && $newValue < $validation['min']) {
        return Response::json(['ok' => false, 'error' => "Value must be >= {$validation['min']}"], 422);
    }
    if (isset($validation['max']) && $newValue > $validation['max']) {
        return Response::json(['ok' => false, 'error' => "Value must be <= {$validation['max']}"], 422);
    }
    
    // Mettre à jour
    $config = $this->realismConfigRepo->getActiveConfig($tenantId);
    $configJson = json_decode($config['config_json'], true);
    $configJson[$domain][$key] = $newValue;
    
    $this->realismConfigRepo->upsertConfig($tenantId, $configJson, $userId);
    
    return Response::json(['ok' => true, 'updated' => true]);
}
```

---

### 3.3 Fonction SQF générique

**Helper unique** :

```sqf
// fn_getRealismParam.sqf
params [
    ["_domain", "", [""]],
    ["_key", "", [""]],
    ["_default", nil]
];

private _config = call ATHENA_fnc_getRealismConfig;
private _domainData = _config getVariable [_domain, locationNull];

if (isNull _domainData) exitWith { _default };

private _value = _domainData getVariable [_key, _default];
_value
```

**Usage** :

```sqf
// Ancien (avant centralisation)
_relayRange = 2000; // Hardcodé

// Nouveau (générique)
_relayRange = ["radio_relays", "relay_range_m", 2000] call ATHENA_fnc_getRealismParam;

// Nouveau paramètre : RIEN à changer dans le code SQF
_powerConsumption = ["radio_relays", "relay_power_consumption_w", 100] call ATHENA_fnc_getRealismParam;
```

**Zéro fichier à modifier** pour ajouter un paramètre.

---

### 3.4 Helper JS générique

**Déjà implémenté** dans `atak-realism-config-helper.js` :

```javascript
// Usage actuel
const relayRange = (await AtakRealismConfig.getDomain('radio_relays')).relay_range_m;

// Nouveau paramètre : RIEN à changer
const powerConsumption = (await AtakRealismConfig.getDomain('radio_relays')).relay_power_consumption_w;
```

---

### 3.5 Génération UI automatique

**Déjà générique** dans l'admin UI (à compléter) :

```php
// views/admin/atak_realism/config.php
<?php
$schema = loadSchema(); // Depuis config/realism-schema.json

foreach ($schema['domains'] as $domainKey => $domain):
?>
<div class="tab-content" data-tab="<?= $domainKey ?>">
    <h2><?= $domain['label'] ?></h2>
    
    <?php foreach ($domain['parameters'] as $paramKey => $param): ?>
        <?php 
        $value = $config[$domainKey][$paramKey] ?? $param['default'];
        $fieldHtml = generateField($domainKey, $paramKey, $param, $value);
        echo $fieldHtml;
        ?>
    <?php endforeach; ?>
</div>
<?php endforeach; ?>
```

**Nouveau paramètre** : Apparaît automatiquement dans l'onglet approprié avec le bon contrôle (slider/toggle/dropdown).

---

### 3.6 Récapitulatif "ajout paramètre"

**Aujourd'hui (sans architecture générique)** :
1. Ajouter dans schéma JSON ❌
2. Modifier `AtakRealismConfigRepository::validateConfigJson()` ❌
3. Modifier `AdminAtakRealismConfigController::save()` ❌
4. Modifier `views/admin/atak_realism/config.php` (ajouter le champ HTML) ❌
5. Modifier SQF `fn_syncAtakRealism.sqf` (ajouter variable) ❌
6. Modifier Extension C# (ajouter constante) ❌
7. Modifier JS (ajouter variable) ❌

**Total : 7 fichiers à modifier**

---

**Demain (avec architecture générique)** :
1. Ajouter dans `config/realism-schema.json` ✅

**Total : 1 fichier à modifier**

Tout le reste (validation, UI, API, SQF, C#, JS) utilise automatiquement le nouveau paramètre.

---

## 4. Tutoriel + UI

### 4.1 Principe

Le tutoriel devient un **livrable Phase 2** (back-office), pas un à-côté.

**Deux pièces** :
1. **Aide contextuelle** : Intégrée à l'écran admin (à côté de chaque onglet)
2. **Page tutoriel dédiée** : Pour les joueurs, avec captures d'écran HUD/Tacmap

---

### 4.2 Aide contextuelle (admin)

**Onglet avec icône aide** :

```php
<nav class="flex flex-wrap gap-2" id="tabs-nav">
    <button class="tab-btn active" data-tab="radio">
        📡 Relais radio
        <span class="help-icon" data-help="radio">❓</span>
    </button>
    <!-- ... autres onglets ... -->
</nav>

<div id="help-panel" class="help-panel hidden">
    <div class="help-content" data-help-for="radio">
        <h3>📡 Relais radio — Aide</h3>
        
        <h4>À quoi ça sert ?</h4>
        <p>Les relais radio permettent aux joueurs de communiquer au-delà de la portée directe de leurs terminaux ATAK. Sans relais intact, les données ne passent pas.</p>
        
        <h4>Paramètres clés</h4>
        <ul>
            <li><strong>Portée des relais</strong> : Distance max de transmission. Valeur typique : 2000m.</li>
            <li><strong>Relais obligatoire</strong> : Si activé, les joueurs DOIVENT être à portée d'un relais pour transmettre.</li>
            <li><strong>Effets météo</strong> : La pluie/brouillard réduit la portée. Désactiver pour mode arcade.</li>
        </ul>
        
        <h4>Profils recommandés</h4>
        <ul>
            <li>🟢 <strong>Débutant</strong> : Relais non obligatoire, pas de météo, portée 5000m</li>
            <li>🟡 <strong>Événement</strong> : Relais obligatoire, météo activée, portée 3000m</li>
            <li>🔴 <strong>Expert</strong> : Relais obligatoire, météo activée, portée 2000m</li>
        </ul>
        
        <h4>Impact en jeu</h4>
        <img src="/assets/img/help/relay-tacmap.png" alt="Relais sur Tacmap" style="max-width: 100%; margin: 1rem 0;">
        <p>Les joueurs voient un cercle de portée autour de chaque relais. S'ils sortent de ce cercle, la liaison est perdue.</p>
    </div>
    
    <div class="help-content" data-help-for="zones">
        <h3>🗺️ Zones roleplay — Aide</h3>
        <!-- ... contenu similaire ... -->
    </div>
    
    <!-- ... autres helps ... -->
</div>
```

**JavaScript toggle help** :

```javascript
document.querySelectorAll('.help-icon').forEach(icon => {
    icon.addEventListener('click', (e) => {
        e.stopPropagation();
        const helpFor = icon.dataset.help;
        
        const helpPanel = document.getElementById('help-panel');
        const helpContent = document.querySelector(`[data-help-for="${helpFor}"]`);
        
        // Masquer tous les helps
        document.querySelectorAll('.help-content').forEach(h => h.classList.add('hidden'));
        
        // Afficher le help ciblé
        helpContent.classList.remove('hidden');
        helpPanel.classList.toggle('hidden');
    });
});
```

---

### 4.3 Page tutoriel joueur

**URL** : `/guide/realism-atak`

```php
<article class="tutorial">
    <h1>Guide du réalisme ATAK</h1>
    <p class="lead">Comprendre les relais radio, certificats, et dégâts terminaux pour survivre sur le terrain.</p>
    
    <nav class="toc">
        <h2>Sommaire</h2>
        <ul>
            <li><a href="#relais">📡 Relais radio</a></li>
            <li><a href="#certificats">🔐 Certificats</a></li>
            <li><a href="#degats">💥 Dégâts au terminal</a></li>
            <li><a href="#meteo">🌧️ Météo</a></li>
            <li><a href="#faq">❓ FAQ</a></li>
        </ul>
    </nav>
    
    <section id="relais">
        <h2>📡 Relais radio</h2>
        
        <h3>Qu'est-ce qu'un relais ?</h3>
        <p>Un relais radio est un mât que vous ou votre équipe placez sur le terrain. Il permet de transmettre les données ATAK au-delà de la portée directe de votre terminal.</p>
        
        <div class="screenshot">
            <img src="/assets/img/guide/relay-placement.jpg" alt="Placement d'un relais">
            <p class="caption">Placement d'un relais via Zeus ou addAction en jeu</p>
        </div>
        
        <h3>Comment savoir si je suis à portée ?</h3>
        <p>Ouvrez votre terminal ATAK (app Android ou Tacmap web). Un cercle vert autour du relais indique sa portée. Si vous êtes dedans, la liaison est active.</p>
        
        <div class="screenshot">
            <img src="/assets/img/guide/relay-tacmap-range.jpg" alt="Portée relais sur Tacmap">
            <p class="caption">Cercle de portée d'un relais sur Tacmap</p>
        </div>
        
        <h3>Que se passe-t-il si je sors de portée ?</h3>
        <p>Votre terminal affiche "⚠️ Liaison perdue". Vous ne recevez plus les positions des alliés ni les ordres. Retournez dans la zone de couverture ou demandez le placement d'un nouveau relais.</p>
        
        <div class="alert alert-warning">
            <strong>⚠️ Astuce</strong> : En configuration "Expert", sans relais à portée, votre terminal est inutile. Planifiez vos déplacements !
        </div>
        
        <h3>Effet de la météo</h3>
        <p>La pluie, le brouillard et le vent réduisent la portée des relais. Un cercle orange sur Tacmap indique une portée réduite. Un badge météo (🌧️, 🌫️, 💨) apparaît sur le relais.</p>
        
        <div class="screenshot">
            <img src="/assets/img/guide/relay-weather-impact.jpg" alt="Impact météo sur relais">
            <p class="caption">Relais avec portée réduite sous la pluie (85%)</p>
        </div>
    </section>
    
    <section id="certificats">
        <h2>🔐 Certificats</h2>
        
        <h3>Qu'est-ce qu'un certificat ?</h3>
        <p>Un certificat électronique authentifie votre terminal auprès du réseau. Sans certificat valide, vous ne pouvez ni transmettre ni recevoir de données.</p>
        
        <h3>Comment obtenir un certificat ?</h3>
        <p>En début de mission, votre terminal reçoit automatiquement un certificat valide pour la durée configurée par votre communauté (par défaut 365 jours).</p>
        
        <h3>Que se passe-t-il si mon certificat expire ?</h3>
        <p>Votre terminal affiche "❌ Certificat expiré". Vous devez demander un renouvellement au poste de commandement (admin web) ou via un officier sur le terrain.</p>
        
        <div class="alert alert-info">
            <strong>ℹ️ Info</strong> : En mode "Débutant", les certificats ne sont pas requis. En mode "Expert", un certificat expiré = terminal inutile.
        </div>
    </section>
    
    <section id="degats">
        <h2>💥 Dégâts au terminal</h2>
        
        <h3>Mon terminal peut-il être endommagé ?</h3>
        <p>Oui, si activé par l'admin. Recevoir des impacts proches, des explosions ou être blessé peut endommager votre terminal.</p>
        
        <h3>Comment réparer mon terminal ?</h3>
        <p>Utilisez l'action "🔧 Réparer terminal ATAK" dans votre menu (ACE Interact). Cela prend du temps et nécessite un kit de réparation.</p>
        
        <div class="alert alert-danger">
            <strong>⚠️ Danger</strong> : Après 3 impacts (par défaut), votre terminal est détruit. Il faudra en obtenir un nouveau auprès du QG.
        </div>
    </section>
    
    <section id="meteo">
        <h2>🌧️ Météo</h2>
        
        <h3>Comment la météo m'impacte ?</h3>
        <ul>
            <li><strong>Pluie</strong> : -15% portée relais</li>
            <li><strong>Brouillard</strong> : -30% portée relais</li>
            <li><strong>Orage</strong> : -40% portée relais</li>
            <li><strong>Vent &gt; 50 km/h</strong> : Pénalité supplémentaire progressive</li>
        </ul>
        
        <p>Ces effets sont cumulatifs. En cas d'orage + vent fort, la portée peut chuter à 30% de la normale.</p>
        
        <div class="screenshot">
            <img src="/assets/img/guide/weather-hud.jpg" alt="HUD météo">
            <p class="caption">HUD affichant la météo actuelle et l'impact sur les comms</p>
        </div>
    </section>
    
    <section id="faq">
        <h2>❓ FAQ</h2>
        
        <details>
            <summary>Pourquoi mon terminal affiche "⚠️ Liaison réduite" ?</summary>
            <p>Soit vous êtes en limite de portée d'un relais, soit la météo dégrade les communications. Rapprochez-vous du relais ou attendez que la météo s'améliore.</p>
        </details>
        
        <details>
            <summary>Je suis admin : comment changer ces paramètres ?</summary>
            <p>Allez dans <a href="/admin/atak/realism/config">Admin > Configuration réalisme ATAK</a>. Choisissez un profil (Débutant/Événement/Expert) ou personnalisez manuellement.</p>
        </details>
        
        <details>
            <summary>Puis-je désactiver le réalisme ?</summary>
            <p>Oui, appliquez le profil "Débutant" qui désactive toutes les contraintes (relais non obligatoires, pas de météo, pas de certificats, pas de dégâts).</p>
        </details>
        
        <details>
            <summary>Comment savoir quel profil est actif ?</summary>
            <p>Ouvrez votre terminal ATAK et consultez l'onglet "Paramètres" > "Réalisme". Le profil actif est affiché en haut.</p>
        </details>
    </section>
    
    <footer class="tutorial-footer">
        <p>Dernière mise à jour : 22 septembre 2026</p>
        <p>Questions ? Contactez un admin ou consultez le <a href="/forum/category/support">Forum Support</a>.</p>
    </footer>
</article>
```

**CSS** :

```css
.tutorial {
    max-width: 900px;
    margin: 2rem auto;
    padding: 2rem;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.tutorial .toc {
    background: #f8f9fa;
    border-left: 4px solid #007bff;
    padding: 1rem;
    margin: 2rem 0;
}

.tutorial .screenshot {
    margin: 2rem 0;
    text-align: center;
}

.tutorial .screenshot img {
    max-width: 100%;
    border: 2px solid #ddd;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.tutorial .screenshot .caption {
    margin-top: 0.5rem;
    font-size: 0.9rem;
    color: #666;
    font-style: italic;
}

.tutorial .alert {
    padding: 1rem;
    border-radius: 8px;
    margin: 1rem 0;
    border-left: 4px solid;
}

.tutorial .alert-warning {
    background: #fff3cd;
    border-color: #ffc107;
    color: #856404;
}

.tutorial .alert-info {
    background: #d1ecf1;
    border-color: #17a2b8;
    color: #0c5460;
}

.tutorial .alert-danger {
    background: #f8d7da;
    border-color: #dc3545;
    color: #721c24;
}

.tutorial details {
    background: #f8f9fa;
    padding: 1rem;
    margin: 1rem 0;
    border-radius: 8px;
    cursor: pointer;
}

.tutorial details summary {
    font-weight: 600;
    color: #007bff;
}

.tutorial details[open] summary {
    margin-bottom: 0.5rem;
}
```

---

## Récapitulatif des 4 améliorations

| # | Amélioration | Impact | Effort |
|---|--------------|--------|--------|
| 1 | Deux niveaux d'UI (admin/joueur) | UX drastiquement simplifiée | 2-3j |
| 2 | Modifier objets posés depuis web | Flexibilité opérationnelle totale | 2j |
| 3 | Architecture générique (1 fichier = 1 param) | Maintenance divisée par 7 | 3j |
| 4 | Tutoriel intégré + page dédiée | Adoption joueurs facilitée | 1-2j |

**Total effort** : 8-10 jours (intégrable en Phase 2 ou début Phase 3)

---

## Prochaines étapes

1. **Créer schéma JSON** : `config/realism-schema.json` avec types, labels, validations
2. **Profils prédéfinis** : Débutant, Événement, Expert dans le schéma
3. **Refactor admin UI** : Générer depuis schéma au lieu de HTML hardcodé
4. **Table overrides** : `atak_relay_overrides` + API CRUD
5. **Polling Extension C#** : `SyncRelayOverrides()` toutes les 15s
6. **Vue joueur** : `/atak/my-relays` ou widget Tacmap
7. **Aide contextuelle** : Panneau help dans admin UI
8. **Page tutoriel** : `/guide/realism-atak` avec screenshots

---

**Fin du document**
