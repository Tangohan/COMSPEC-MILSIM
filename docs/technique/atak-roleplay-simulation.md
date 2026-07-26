# Système de simulation roleplay ATAK

**Date** : 24 juillet 2026  
**Version** : 1.0

---

## Vue d'ensemble

Le système de simulation roleplay permet de simuler des dysfonctionnements réalistes dans la liaison tactique ATAK pour renforcer l'immersion et le réalisme des opérations. Les simulations incluent :

- **Dégradation réseau** : latence variable, perte de paquets, déconnexions temporaires
- **Défauts capteurs** : dysfonctionnements du capteur de rythme cardiaque
- **Effets visuels** : glitchs, parasites, messages d'erreur contextualisés

Toutes les simulations sont **entièrement configurables** par communauté via l'interface d'administration web.

---

## Architecture

### Composants serveur

#### 1. Migration BDD

**Fichier** : `bootstrap/tenant_atak_roleplay_config_migration.php`

Ajoute les colonnes de configuration roleplay dans `tenant_atak_config` :

```sql
-- Simulation réseau
roleplay_network_enabled TINYINT(1) DEFAULT 0
roleplay_network_mode VARCHAR(20) DEFAULT 'normal'
roleplay_latency_min_ms INT UNSIGNED DEFAULT 0
roleplay_latency_max_ms INT UNSIGNED DEFAULT 0
roleplay_packet_loss_percent DECIMAL(5,2) DEFAULT 0.00
roleplay_disconnect_enabled TINYINT(1) DEFAULT 0
roleplay_disconnect_min_sec INT UNSIGNED DEFAULT 5
roleplay_disconnect_max_sec INT UNSIGNED DEFAULT 30
roleplay_disconnect_interval_sec INT UNSIGNED DEFAULT 600

-- Défauts capteurs
roleplay_sensor_enabled TINYINT(1) DEFAULT 0
roleplay_sensor_failure_percent DECIMAL(5,2) DEFAULT 0.00
roleplay_sensor_error_percent DECIMAL(5,2) DEFAULT 0.00
roleplay_sensor_missing_percent DECIMAL(5,2) DEFAULT 0.00

-- Zones géographiques (à venir)
roleplay_zones_enabled TINYINT(1) DEFAULT 0
roleplay_zones_config TEXT DEFAULT NULL
```

#### 2. Service de simulation

**Fichier** : `app/Services/Tactical/RoleplaySimulationService.php`

Classe centrale qui gère toutes les simulations :

| Méthode | Description |
|---------|-------------|
| `applyNetworkLatency($tenantId)` | Applique un délai artificiel (usleep) |
| `shouldSimulateDisconnection($tenantId)` | Vérifie si une déconnexion doit être simulée |
| `shouldSimulatePacketLoss($tenantId)` | Détermine si un paquet doit être perdu |
| `applyHeartRateSensorFailure($tenantId, $hr)` | Modifie ou supprime la valeur du rythme cardiaque |
| `getNetworkStats($tenantId)` | Retourne les stats réseau pour l'UI |
| `getSensorStats($tenantId)` | Retourne les stats capteurs pour l'UI |

#### 3. Repository

**Fichier** : `app/Repositories/TenantAtakConfigRepository.php`

Nouvelles méthodes :

- `getRoleplayConfig($tenantId)` : Récupère la configuration roleplay
- `updateRoleplayConfig($tenantId, $config)` : Met à jour la configuration

#### 4. Intégration API

**Fichier** : `app/Controllers/Api/AtakApiController.php`

Méthode helper `applyRoleplayEffects($tenantId)` appelée dans les endpoints critiques :

- `unitsIndex` : Positions et données médicales
- `chatIndex` : Messages
- `medicalAlertsIndex` : Alertes médicales
- `ping` : Latence uniquement (pas de perte/déconnexion)

**Nouveau endpoint** : `GET /api/atak/roleplay-stats`

Retourne la configuration active pour affichage UI côté client.

#### 5. Simulateur capteur cardiaque

**Fichier** : `app/Support/HeartRateSensorSimulator.php`

Helper pour appliquer les dysfonctionnements aux données médicales des unités.

Types de défauts :
- **missing** : Valeur `null` (capteur non disponible)
- **failure** : Valeur `0` (panne matérielle)
- **error** : Valeur aberrante (± 30% à 200% du réel)

---

### Composants client

#### 1. JavaScript

**Fichier** : `public/assets/js/atak-roleplay-effects.js`

Module `AtakRoleplayEffects` exposé globalement :

| Méthode | Description |
|---------|-------------|
| `applyGlitchEffect(element, duration)` | Effet glitch sur un élément DOM |
| `applyMapInterference()` | Parasites visuels sur la carte |
| `showConnectionError(message)` | Affiche une erreur de liaison |
| `degradeUnitData(units)` | Applique des effets de dégradation |
| `handleApiError(error, xhr)` | Intercepte les erreurs 503 |
| `fetchRoleplayStats()` | Récupère la config depuis l'API |

#### 2. CSS

**Fichier** : `public/assets/css/atak-roleplay-effects.css`

Classes principales :
- `.atak-glitch` : Animation de glitch
- `.atak-interference` : Effet de scan/parasites
- `.atak-roleplay-error` : Message d'erreur flottant
- `.atak-sensor-warning` : Badge avertissement capteur
- `.atak-unit-degraded` : Style pour unités avec données dégradées

#### 3. Intégration dans ATAK

**Fichier** : `views/atak.php`

Inclusion des assets :

```html
<link href="/assets/css/atak-roleplay-effects.css" rel="stylesheet" />
<script src="/assets/js/atak-roleplay-effects.js"></script>
```

Auto-initialisation au chargement.

---

### Composants Arma

#### Paramètres CBA

**Fichier** : `mod/@COMSPECOverwatch/addons/connect/XEH_preInit.sqf`

Nouveaux paramètres dans la catégorie **COMSPEC Overwatch — Roleplay** :

| Paramètre | Type | Défaut | Description |
|-----------|------|--------|-------------|
| `comspec_overwatch_roleplay_enabled` | Checkbox | false | Active le mode roleplay |
| `comspec_overwatch_roleplay_network_failures` | Checkbox | false | Active les simulations réseau |
| `comspec_overwatch_roleplay_sensor_failures` | Checkbox | false | Active les défauts capteurs |
| `comspec_overwatch_roleplay_visual_effects` | Checkbox | true | Active les effets visuels web |

**Note** : La configuration détaillée (latence, taux de perte, etc.) se fait **uniquement sur le portail** web. Les paramètres CBA servent d'interrupteurs on/off côté client.

---

## Interface d'administration

### Contrôleur

**Fichier** : `app/Controllers/Admin/AdminAtakRoleplayController.php`

Routes :
- `GET /admin/atak/roleplay` : Affiche le formulaire
- `POST /admin/atak/roleplay` : Enregistre la configuration
- `GET /admin/atak/roleplay/reset` : Réinitialise aux valeurs par défaut

### Vue

**Fichier** : `views/admin/atak/roleplay.php`

Sections du formulaire :

1. **Simulation réseau**
   - Mode (normal, hostile, dégradé, équipement)
   - Latence min/max (ms)
   - Perte de paquets (%)
   - Déconnexions temporaires (durée, intervalle)

2. **Défauts capteurs médicaux**
   - Panne complète (%)
   - Valeur erronée (%)
   - Données manquantes (%)

3. **Zones géographiques** (placeholder)
   - Configuration JSON (à implémenter)

---

## Modes de simulation réseau

### Modes contextuels

| Mode | Message d'erreur | Usage |
|------|-----------------|-------|
| `normal` | Liaison temporairement indisponible | Conditions standard |
| `hostile` | Interférences détectées — liaison interrompue | Zone de combat, brouillage |
| `degraded` | Conditions réseau dégradées — connexion perdue | Infrastructure dégradée |
| `equipment` | Défaut matériel — reconnexion en cours | Problème technique |

### Comportements

#### Latence

Délai artificiel appliqué via `usleep()` sur chaque requête API.

```php
$delayMs = random_int($latency_min_ms, $latency_max_ms);
usleep($delayMs * 1000);
```

#### Perte de paquets

Probabilité qu'une requête soit rejetée avec une erreur 503.

```php
$roll = random_int(0, 10000);
if ($roll < ($packet_loss_percent * 100)) {
    return Response::json(['error' => 'packet_lost'], 503);
}
```

#### Déconnexions temporaires

Gestion par session :
1. Planification de la prochaine déconnexion (intervalle configurable)
2. Déclenchement aléatoire entre `disconnect_min_sec` et `disconnect_max_sec`
3. Toutes les requêtes retournent 503 pendant la période de déconnexion
4. Retour à la normale + planification de la prochaine coupure

---

## Simulation capteurs

### Types de défauts

| Type | Probabilité | Effet | Status |
|------|-------------|-------|--------|
| **Panne** | `sensor_failure_percent` | FC = 0 | `failure` |
| **Erreur** | `sensor_error_percent` | FC aberrant (× 0.3 à 2.0) | `error` |
| **Manquant** | `sensor_missing_percent` | FC = null | `missing` |

### Application

Les défauts sont appliqués dans `unitsIndex()` avant l'envoi au client :

```php
$simulatedHr = $this->roleplaySim->applyHeartRateSensorFailure($tenantId, $originalHr);

if ($simulatedHr === null) {
    $extra['heart_rate'] = null;
    $extra['sensor_status'] = 'missing';
    $extra['sensor_message'] = 'Capteur non disponible — Données manquantes';
}
```

Les messages sont affichés dans l'UI web via le champ `sensor_message`.

---

## Effets visuels

### Glitch

Animation CSS appliquée aux éléments lors de pertes de paquets ou erreurs.

```css
@keyframes atak-glitch-anim {
  0%, 100% { transform: translate(0); opacity: 1; }
  25% { transform: translate(-2px, 1px); opacity: 0.8; }
  50% { transform: translate(2px, -1px); opacity: 0.9; }
  75% { transform: translate(-1px, -2px); opacity: 0.85; }
}
```

### Interférences carte

Overlay de lignes de scan appliqué temporairement sur la carte Leaflet.

### Messages d'erreur

Notification flottante affichée en haut à droite avec le message contextuel du mode actif.

---

## Workflow d'activation

### 1. Configuration admin

1. Accéder à `/admin/atak/roleplay`
2. Activer les simulations souhaitées
3. Configurer les paramètres (latence, taux, etc.)
4. Enregistrer

### 2. Activation joueurs

1. Ouvrir les options CBA dans Arma 3
2. Section **COMSPEC Overwatch — Roleplay**
3. Cocher **Activer le mode roleplay**
4. Activer les simulations réseau/capteurs selon besoin

### 3. Vérification

- Les effets visuels apparaissent automatiquement sur `/atak` si la configuration est active
- Les joueurs peuvent désactiver les effets visuels tout en gardant les simulations serveur
- Les statistiques sont visibles via `GET /api/atak/roleplay-stats`

---

## Cas d'usage

### Entraînement réaliste

- Latence 50-200 ms
- Perte 1-3%
- Déconnexions 10-20s toutes les 10 minutes

### Scénario hostile

- Latence 100-500 ms
- Perte 5-10%
- Déconnexions 20-60s toutes les 5 minutes
- Mode `hostile`

### Test de résilience

- Latence 200-1000 ms
- Perte 10-20%
- Déconnexions fréquentes (2-3 min)
- Défauts capteurs 5-10%

---

## Limitations et améliorations futures

### Limitations actuelles

1. **Zones géographiques** : Non implémenté (placeholder JSON)
2. **Pas de simulation Arma → serveur** : Seules les requêtes serveur → client sont affectées
3. **Capteurs** : Uniquement rythme cardiaque (pas d'autres vitals)
4. **Pas de rejeu** : Les déconnexions ne simulent pas de file d'attente

### Améliorations prévues

1. **Zones de dégradation géographique** : Appliquer des effets selon la position sur la carte
2. **Simulation bidirectionnelle** : Affecter aussi les uploads depuis Arma
3. **Profils préconfigurés** : Templates ("Entraînement", "Combat", "Survie")
4. **Statistiques** : Dashboard admin avec métriques d'impact
5. **File d'attente** : Mettre en cache les données pendant les coupures

---

## Maintenance

### Désactivation d'urgence

Si les simulations causent des problèmes, désactivation rapide :

```sql
UPDATE tenant_atak_config 
SET roleplay_network_enabled = 0, 
    roleplay_sensor_enabled = 0, 
    roleplay_zones_enabled = 0
WHERE tenant_id = <ID>;
```

Ou via l'interface admin : bouton **Réinitialiser**.

### Logs

Aucun log spécifique actuellement. Les erreurs 503 apparaissent dans les logs API standards.

### Performance

Impact négligeable :
- Latence : `usleep()` n'utilise pas de CPU
- Packet loss : vérification aléatoire < 1ms
- Capteurs : calculs simples sur données déjà chargées

---

## Références

- Migration : `bootstrap/tenant_atak_roleplay_config_migration.php`
- Service : `app/Services/Tactical/RoleplaySimulationService.php`
- Repository : `app/Repositories/TenantAtakConfigRepository.php`
- API : `app/Controllers/Api/AtakApiController.php`
- Interface admin : `app/Controllers/Admin/AdminAtakRoleplayController.php`
- Vue admin : `views/admin/atak/roleplay.php`
- CSS : `public/assets/css/atak-roleplay-effects.css`
- JS : `public/assets/js/atak-roleplay-effects.js`
- CBA : `mod/@COMSPECOverwatch/addons/connect/XEH_preInit.sqf`

---

**Document rédigé pour COMSPEC MILSIM — Juillet 2026**
