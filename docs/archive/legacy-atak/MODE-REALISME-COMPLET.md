# 🎯 Mode Réalisme - COMSPEC Overwatch

Récapitulatif complet de tous les paramètres et fonctionnalités de réalisme disponibles dans le mod Overwatch.

---

## 📋 Vue d'ensemble

Le mod Overwatch propose **plusieurs couches de réalisme** configurables indépendamment via les paramètres CBA. Chaque système peut être activé/désactivé selon les besoins de la communauté.

---

## 🎮 Catégorie "COMSPEC Overwatch — Roleplay"

Tous les paramètres suivants se trouvent dans **Addons Options > COMSPEC Overwatch — Roleplay**.

### 1. 🌐 Mode roleplay général

**Paramètre** : `comspec_overwatch_roleplay_enabled`  
**Type** : Case à cocher  
**Défaut** : ☐ Désactivé  

**Description** :  
Active les dysfonctionnements simulés (réseau, capteurs). Configuration détaillée via l'administration web du portail.

**Active** :
- Zones géographiques roleplay (brouilleurs, zones mortes...)
- Système de déconnexions
- Calculs de perte de paquets
- Effets de zones (interférence, dégradation...)

**N'active PAS automatiquement** :
- Simulations réseau (paramètre séparé)
- Défauts capteurs (paramètre séparé)
- Dommages ATAK (paramètre séparé)

---

### 2. 📡 Simulations réseau

**Paramètre** : `comspec_overwatch_roleplay_network_failures`  
**Type** : Case à cocher  
**Défaut** : ☐ Désactivé  

**Description** :  
Active les délais, pertes de paquets et déconnexions temporaires. Les paramètres (latence, taux de perte) sont configurés sur le portail.

**Effets** :
- Déconnexions aléatoires (5-30s toutes les ~10min)
- Simulation de latence variable
- Perte de paquets simulée
- Backoff automatique en cas d'erreurs

**Mécaniques** :
```sqf
// Déconnexion cyclique
État : "is_disconnected" => false/true
Durée : 5-30 secondes (aléatoire)
Intervalle : ~600 secondes (10 minutes)
```

**Fonctions impliquées** :
- `fn_simulateNetworkDisconnect.sqf`
- `fn_trackPacketLoss.sqf`
- `fn_updatePosition.sqf` (vérification backoff)

---

### 3. 💓 Défauts capteurs médicaux

**Paramètre** : `comspec_overwatch_roleplay_sensor_failures`  
**Type** : Case à cocher  
**Défaut** : ☐ Désactivé  

**Description** :  
Simule des dysfonctionnements du capteur de rythme cardiaque (valeurs manquantes, erronées ou nulles). Les taux de défaillance sont configurés sur le portail.

**Effets** :
- Rythme cardiaque affiché incorrect
- Valeurs nulles (---)
- Valeurs aberrantes (300 BPM, 0 BPM...)
- Disparition temporaire du signal

**Usage** :
- Ajoute de l'incertitude médicale
- Force les joueurs à vérifier visuellement l'état des blessés
- Simule défaillance d'équipement sous stress

**Taux configurables** :
```
Portail admin > Roleplay > Capteurs médicaux
├── Taux défaillance : 0-100%
├── Durée défaut : 5-60s
└── Type défaut : null, aberrant, intermittent
```

---

### 4. 🎨 Effets visuels de dégradation

**Paramètre** : `comspec_overwatch_roleplay_visual_effects`  
**Type** : Case à cocher  
**Défaut** : ☑ Activé  

**Description** :  
Affiche des glitchs, parasites et messages d'erreur dans l'interface ATAK web quand la liaison se dégrade.

**Effets visuels** :

#### Écran cassé
```
┌─────────────────────────────┐
│  ⚠️ ÉCRAN ENDOMMAGÉ         │
│                             │
│  [█▓▒░ VISUAL DAMAGE █▓▒░]  │
│                             │
│  Signal: CONNECTED          │
│  Affichage: BROKEN          │
└─────────────────────────────┘
```

#### Perte de connexion
```
┌─────────────────────────────┐
│  ⚠️ Liaison ATAK perdue     │
│                             │
│  Reconnexion dans 08s       │
│                             │
│  [MAP INTERFERENCE 60%]     │
└─────────────────────────────┘
```

#### Glitch aléatoire
- Flash rouge 0.1s si perte paquets >10%
- Parasites sur la carte
- Interférence progressive selon intensité

**Fonction** : `fn_updateAtakEnhancedRoleplay.sqf`

---

### 5. 💥 Réalisme ATAK (dommages physiques)

**Paramètre** : `comspec_overwatch_atak_realism`  
**Type** : Liste déroulante  
**Défaut** : 0 (Désactivé)  

**Valeurs** :
```
0 = Désactivé
1 = Niveau 1 : Extinction temporaire
2 = Niveau 2 : Écran peut être détruit
3 = Niveau 3 : Destruction complète possible
```

**Description** :  
Les blessures au torse peuvent endommager l'ATAK. Chaque niveau ajoute de la gravité.

#### Niveau 1 : Extinction

| Déclenchement | >50% dommages torse, 30% chance |
| Effet | ATAK s'éteint suite au choc |
| Durée | 30 secondes (auto-redémarrage) |
| Réparation | Automatique ou manuelle (gratuit) |
| Impact gameplay | Perte temporaire liaison |

**Message** : `"ATAK éteint suite au choc !"`

#### Niveau 2 : Écran détruit

| Déclenchement | >70% dommages torse, 40% chance |
| Effet | Écran détruit, connexion maintenue |
| Durée | Permanent jusqu'à réparation |
| Réparation | Action ACE avec **Toolkit** (~5s) |
| Impact gameplay | Pas d'affichage, données transmises |

**Message** : `"Écran ATAK détruit ! Connexion maintenue mais pas d'affichage."`

#### Niveau 3 : Destruction complète

| Déclenchement | >80% dommages torse, 50% chance |
| Effet | ATAK complètement détruit |
| Durée | Permanent, **irréparable** |
| Réparation | **Impossible** |
| Impact gameplay | Déconnexion forcée, perte totale |

**Message** : `"ATAK complètement détruit ! Connexion perdue."`

**Fonction** : `fn_checkAtakDamage.sqf`

---

## 🔧 Actions de réparation ACE

### Menu ACE Self-Interact > Équipement

#### 1. Rallumer l'ATAK
- **Condition** : ATAK éteint (niveau 1)
- **Outil** : Aucun
- **Durée** : Instantanée
- **Action** : Rallume l'équipement

#### 2. Réparer l'écran
- **Condition** : Écran détruit (niveau 2)
- **Outil** : **Toolkit** dans inventaire
- **Durée** : ~5 secondes
- **Action** : Répare l'écran, restaure affichage

#### 3. Réparation complète
- **Condition** : Dommages partiels
- **Outil** : **Toolkit**
- **Durée** : ~10 secondes
- **Action** : Répare tous dommages non-critiques

#### 4. État ATAK (diagnostic)
- **Condition** : Toujours disponible
- **Outil** : Aucun
- **Durée** : Instantanée
- **Action** : Affiche diagnostic détaillé

**Diagnostic exemple** :
```
=== État ATAK ===
Alimentation : ✓ OK
Écran : ✗ Détruit
Appareil : ✓ Fonctionnel
Liaison : ● Active

Réparation écran disponible (Toolkit requis)
```

**Fonction** : `fn_repairAtak.sqf`, `fn_addAtakRepairAction.sqf`

---

## 🗺️ Zones géographiques roleplay

**Activation** : Nécessite `comspec_overwatch_roleplay_enabled = true`

### 4 types de zones

#### 1. No Coverage 🔴
```sqf
Type : "no_coverage"
Effet : Déconnexion forcée totale
Intensité : 100% (fixe)
Usage : Bunkers, souterrains, zones mortes
```

#### 2. Interference 📡
```sqf
Type : "interference"
Effet : Perte de paquets ×3 max
Intensité : 0-100% configurable
Usage : Zones urbaines, installations ennemies
```

#### 3. Degraded 📶
```sqf
Type : "degraded"
Effet : Latence +500ms, perte ×1.5
Intensité : 0-100% configurable
Usage : Forêts, périphéries, collines
```

#### 4. Jammer 🚫
```sqf
Type : "jammer"
Effet : Déconnexions intermittentes, perte ×2
Intensité : 0-100% configurable
Usage : Brouilleurs ennemis, guerre électronique
```

**Fonctions** :
- `fn_createRoleplayZone.sqf`
- `fn_deleteRoleplayZone.sqf`
- `fn_applyZoneEffects.sqf`
- `fn_getPlayerRoleplayZone.sqf`

---

## 🎚️ Paramètres complémentaires

### Autres paramètres de réalisme

Ces paramètres ne sont pas dans la catégorie "Roleplay" mais affectent le réalisme :

#### Detection terminal (ItemAndroid)

**Paramètre** : `comspec_overwatch_terminal_mode`  
**Valeurs** :
```
0 = Object slot uniquement (comme GPS/NVG)
1 = Inventaire (simplement porté)
2 = Les deux (par défaut)
```

**Impact** :
- Mode 0 (strict) : Force équipement du terminal dans le slot objet
- Mode 1 (souple) : Suffit de l'avoir dans l'inventaire
- Mode 2 (équilibré) : Accepte les deux

**Réalisme** :  
Mode 0 = Maximum (doit être équipé comme un vrai terminal tactique)

---

#### Surveillance radio proximité

**Paramètre** : `comspec_overwatch_radio_proximity_enabled`  
**Défaut** : ☑ Activé

**Description** :  
Détecte qui émet près de vous et remonte l'état vers Athena. Nécessite ACRE2 ou TFAR.

**Rayon** : Configurable 10-300m (défaut 75m)  
**Intervalle scan** : 1-10s (défaut 2s)

**Réalisme** :  
Simule la capacité d'un système ATAK à tracker les émissions radio à proximité.

---

#### Mode véhicule détaillé

**Paramètre** : `comspec_overwatch_vehicle_mode`  
**Défaut** : ☑ Activé

**Description** :  
Envoie orientation 3D et vitesse quand le joueur est en véhicule.

**Réalisme** :  
Représente les capteurs embarqués (accéléromètre, gyroscope, GPS différentiel).

---

## 📊 Configuration recommandée par niveau

### Débutant / Découverte

```
Roleplay général               : ☐ Désactivé
Simulations réseau             : ☐ Désactivé
Défauts capteurs               : ☐ Désactivé
Effets visuels                 : ☑ Activé
Réalisme ATAK                  : 0 (Désactivé)
Terminal mode                  : 2 (Les deux)
Radio proximité                : ☑ Activé
```

**Impact** : Expérience guidée, pas de frustration, effets visuels pour immersion.

---

### Intermédiaire / Milsim

```
Roleplay général               : ☑ Activé
Simulations réseau             : ☐ Désactivé
Défauts capteurs               : ☐ Désactivé
Effets visuels                 : ☑ Activé
Réalisme ATAK                  : 1 (Extinction)
Terminal mode                  : 2 (Les deux)
Radio proximité                : ☑ Activé
```

**Impact** : Zones roleplay actives, ATAK peut s'éteindre temporairement, pas de frustration majeure.

---

### Avancé / Hardcore

```
Roleplay général               : ☑ Activé
Simulations réseau             : ☑ Activé
Défauts capteurs               : ☑ Activé
Effets visuels                 : ☑ Activé
Réalisme ATAK                  : 2 (Écran détruit)
Terminal mode                  : 0 (Object slot strict)
Radio proximité                : ☑ Activé
```

**Impact** : Déconnexions aléatoires, capteurs défaillants, écran destructible, strict sur équipement.

---

### Expert / Simulation

```
Roleplay général               : ☑ Activé
Simulations réseau             : ☑ Activé
Défauts capteurs               : ☑ Activé
Effets visuels                 : ☑ Activé
Réalisme ATAK                  : 3 (Destruction complète)
Terminal mode                  : 0 (Object slot strict)
Radio proximité                : ☑ Activé
```

**Impact** : Maximum de réalisme, ATAK peut être détruit définitivement, incertitude maximale.

---

## 🎯 Tableau récapitulatif

| Paramètre | Catégorie | Impact réalisme | Frustration | Réparable |
|-----------|-----------|-----------------|-------------|-----------|
| Mode roleplay général | Roleplay | ⭐⭐⭐ | 😐 Faible | N/A |
| Simulations réseau | Roleplay | ⭐⭐⭐⭐ | 😤 Moyenne | Auto |
| Défauts capteurs | Roleplay | ⭐⭐⭐ | 😑 Faible | Auto |
| Effets visuels | Roleplay | ⭐⭐ | 😐 Nulle | N/A |
| ATAK Niveau 1 | Roleplay | ⭐⭐⭐ | 😐 Faible | Auto 30s |
| ATAK Niveau 2 | Roleplay | ⭐⭐⭐⭐⭐ | 😤 Moyenne | Toolkit |
| ATAK Niveau 3 | Roleplay | ⭐⭐⭐⭐⭐ | 😡 Élevée | ❌ Non |
| Terminal strict | Gameplay | ⭐⭐⭐ | 😐 Faible | N/A |
| Radio proximité | Tactical | ⭐⭐ | 😐 Nulle | N/A |
| Mode véhicule | Tactical | ⭐⭐ | 😐 Nulle | N/A |

---

## 🔊 Effets sonores par système

| Événement | Fichier son | Volume | Système |
|-----------|-------------|--------|---------|
| Déconnexion réseau | `ambient_radio18.wss` | 0.8 | Simulations réseau |
| Reconnexion | `beep_target.wss` | 0.5 | Simulations réseau |
| Interférence | `ambient_radio17.wss` | 0.4 | Zones roleplay |
| Alerte zone | `alarm_independent.wss` | 0.6 | Zones roleplay |
| Écran cassé | `vehicle_collision.wss` | 0.7 | ATAK dommages |
| Extinction ATAK | `addItemFailed` | 1.0 | ATAK dommages |
| Destruction ATAK | `FD_CP_Not_Clear_F` | 1.0 | ATAK dommages |
| Réparation OK | `FD_CP_Clear_F` | 1.0 | Réparation ACE |

**Note** : Tous les sons sont des assets natifs Arma 3, pas de fichiers externes.

---

## 📖 Variables globales de monitoring

### Pour debugging/monitoring

```sqf
// État général roleplay
COMSPEC_RoleplayEnabled = missionNamespace getVariable ["comspec_overwatch_roleplay_enabled", false];

// État ATAK
COMSPEC_AtakState = [
    "powered_on" => true/false,
    "screen_destroyed" => true/false,
    "device_destroyed" => true/false,
    "last_check" => timestamp
];

// État réseau
COMSPEC_NetworkDisconnectState = [
    "is_disconnected" => true/false,
    "disconnect_until" => timestamp,
    "next_disconnect_at" => timestamp,
    "disconnect_count" => number
];

// Effets de zone
COMSPEC_ZoneEffects = [
    "force_disconnect" => true/false,
    "packet_loss_multiplier" => 1.0-3.0,
    "latency_add" => 0-500
];

// Dernière zone
COMSPEC_LastRoleplayZone = hashmap ou nil;
COMSPEC_InRoleplayZone = true/false;
```

---

## 🛠️ Commandes debug utiles

### Forcer déconnexion (test)
```sqf
missionNamespace setVariable ["COMSPEC_NetworkDisconnectState", nil];
[] call comspec_overwatch_connect_fnc_simulateNetworkDisconnect;
```

### Forcer destruction écran (test)
```sqf
private _state = missionNamespace getVariable ["COMSPEC_AtakState", createHashMap];
_state set ["screen_destroyed", true];
missionNamespace setVariable ["COMSPEC_AtakState", _state];
```

### Voir zones actives
```sqf
private _zones = [] call comspec_overwatch_connect_fnc_listRoleplayZones;
systemChat str _zones;
```

### État ATAK complet
```sqf
private _status = [] call comspec_overwatch_connect_fnc_isAtakFunctional;
systemChat str _status;
```

### Activer logs détaillés
```sqf
missionNamespace setVariable ["COMSPEC_Debug_PacketLoss", true];
```

---

## 📚 Documentation associée

- **[MOD-FONCTIONNALITES-ROLEPLAY-TROLL.md](./MOD-FONCTIONNALITES-ROLEPLAY-TROLL.md)** : Guide technique complet
- **[FONCTIONNALITES-TROLL-RESUME.md](./FONCTIONNALITES-TROLL-RESUME.md)** : Résumé fun et scénarios
- **[GUIDE-ZEUS-ROLEPLAY-RAPIDE.md](./GUIDE-ZEUS-ROLEPLAY-RAPIDE.md)** : Guide opérationnel Zeus
- **[INDEX-DOCS-ROLEPLAY-TROLL.md](./INDEX-DOCS-ROLEPLAY-TROLL.md)** : Index et navigation

---

## 🎓 FAQ Réalisme

### Puis-je activer uniquement les effets visuels ?

**Oui !** Les paramètres sont indépendants. Tu peux avoir :
```
Roleplay général : ☐ Non
Effets visuels : ☑ Oui
```
→ Effets visuels sans gameplay modifié.

### Les zones fonctionnent sans simulations réseau ?

**Oui !** Les zones géographiques fonctionnent dès que `roleplay_enabled = true`, même si `network_failures = false`.

### Le niveau 3 ATAK est-il vraiment irréparable ?

**Oui, par design.** C'est le risque du niveau 3. Solution : avoir un second terminal ou éviter le niveau 3.

### Les IA sont-elles affectées ?

**Non.** Seuls les joueurs humains sont impactés. Les IA n'utilisent pas le système ATAK.

### Peut-on réparer sans Toolkit ?

**Non** (sauf niveau 1 qui s'auto-répare). Le Toolkit ACE est obligatoire pour niveaux 2 et 3.

### Les paramètres peuvent-ils changer en cours de mission ?

**Oui**, via CBA. Mais certains effets ne s'appliquent qu'aux nouveaux événements (ex: dommages ATAK futurs).

---

**Dernière mise à jour** : 2026-07-24  
**Version mod** : 1.0.0  
**Documentation** : Complète

---

*"Realism is not about punishment, it's about consequences."*
