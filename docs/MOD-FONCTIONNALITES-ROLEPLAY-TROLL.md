# 🎮 Fonctionnalités Roleplay & "Troll" du Mod COMSPEC Overwatch

Ce document recense toutes les mécaniques de gameplay immersives et réalistes du mod Overwatch, qui simulent des dysfonctionnements et contraintes tactiques pour renforcer l'immersion roleplay.

---

## 📋 Table des matières

1. [Vue d'ensemble](#vue-densemble)
2. [Système de dommages ATAK](#système-de-dommages-atak)
3. [Zones géographiques roleplay](#zones-géographiques-roleplay)
4. [Déconnexions réseau aléatoires](#déconnexions-réseau-aléatoires)
5. [Effets visuels et sonores](#effets-visuels-et-sonores)
6. [Système de réparation](#système-de-réparation)
7. [Configuration](#configuration)
8. [Guide pour Zeus/MJ](#guide-pour-zeusmj)

---

## Vue d'ensemble

Le mod Overwatch intègre un **système roleplay avancé** qui simule les contraintes réelles d'un système tactique ATAK sur le terrain :

- 💥 **Dommages physiques** : l'équipement peut être endommagé par les blessures
- 📡 **Perturbations réseau** : zones de mauvaise couverture, brouilleurs, interférences
- 🔊 **Feedback immersif** : sons et effets visuels pour chaque événement
- 🔧 **Réparations** : possibilité de réparer l'équipement avec les bons outils

Ces mécaniques sont **entièrement optionnelles** et configurables via les paramètres CBA du mod.

---

## Système de dommages ATAK

### Principe

Lorsque le joueur subit des blessures au torse (via ACE Medical), son équipement ATAK peut être endommagé de façon réaliste.

### 3 niveaux de réalisme

Le système propose **3 niveaux de sévérité** configurables :

#### Niveau 1 : Extinction temporaire
- **Déclenchement** : >50% de dommages au torse, 30% de chance
- **Effet** : L'ATAK s'éteint suite au choc
- **Réparation** : Redémarrage automatique après 30 secondes
- **Impact** : Perte temporaire de la liaison tactique

```sqf
// Message affiché
"ATAK éteint suite au choc !"
```

#### Niveau 2 : Écran détruit
- **Déclenchement** : >70% de dommages au torse, 40% de chance
- **Effet** : L'écran est détruit mais la connexion reste active
- **Réparation** : Nécessite un **Toolkit** et action ACE
- **Impact** : Impossible d'afficher l'interface, mais les données sont transmises

```sqf
// Message affiché
"Écran ATAK détruit ! Connexion maintenue mais pas d'affichage."
```

#### Niveau 3 : Destruction complète
- **Déclenchement** : >80% de dommages au torse, 50% de chance
- **Effet** : L'ATAK est complètement détruit
- **Réparation** : **Irréparable** jusqu'à la fin de mission
- **Impact** : Déconnexion forcée, perte totale de liaison

```sqf
// Message affiché
"ATAK complètement détruit ! Connexion perdue."
```

### Effets sonores

Chaque niveau déclenche un son différent :
- **Extinction** : `addItemFailed` (bip d'erreur)
- **Destruction** : `FD_CP_Not_Clear_F` (alerte radio)
- **Collision** : `vehicle_collision.wss` (son d'impact)

### Activation

Paramètre CBA : **"Niveau de réalisme matériel ATAK"**
- `0` = Désactivé (par défaut)
- `1` = Extinction temporaire uniquement
- `2` = Écran destructible
- `3` = Destruction complète possible

---

## Zones géographiques roleplay

### Concept

Les Zeus/MJ peuvent placer des **modules de zones** sur la carte pour simuler des conditions réseau difficiles.

### 4 types de zones

#### 1. Zone sans couverture (No Coverage)
- **Effet** : Déconnexion forcée instantanée
- **Icône** : Rouge, "NO SIGNAL"
- **Usage** : Zones mortes, souterrains, bunkers blindés

```sqf
// Module Zeus
class Module_COMSPEC_NoCoverage
```

#### 2. Zone d'interférence (Interference)
- **Effet** : Perte de paquets élevée (jusqu'à x3)
- **Intensité** : 0-100% configurable
- **Usage** : Zones urbaines denses, environnements électromagnétiques

```sqf
// Formule packet loss
packet_loss_multiplier = (intensity / 100) * 3
```

#### 3. Zone dégradée (Degraded)
- **Effet** : Latence augmentée + perte de paquets modérée
- **Intensité** : 0-100% configurable
- **Usage** : Périphérie de couverture, forêts, collines

```sqf
// Effets
packet_loss_multiplier = (intensity / 100) * 1.5
latency_add = (intensity / 100) * 500  // +500ms max
```

#### 4. Brouilleur actif (Jammer)
- **Effet** : Déconnexions intermittentes aléatoires + perte de paquets
- **Intensité** : 0-100% configurable
- **Usage** : Équipement ennemi de guerre électronique

```sqf
// Chance de déconnexion
force_disconnect = random 100 < (intensity / 2)  // 50% max
packet_loss_multiplier = (intensity / 100) * 2
```

### Effets visuels des zones

Lorsqu'un joueur entre dans une zone :

1. **Message affiché** : "Entrée en [Nom Zone] (intensité X%)"
2. **Son d'alerte** : `alarm_independent.wss`
3. **Indicateur visuel** : Marqueur coloré sur la carte (si Zeus)

```sqf
// Exemple d'entrée en zone
"Entrée en Zone Brouillage Ennemi (intensité 75%)"
```

### Création de zones

#### Via module Zeus (en mission)

1. Ouvrir le menu Zeus
2. Catégorie **"COMSPEC Overwatch Roleplay"**
3. Placer le module souhaité
4. Configurer rayon et intensité

#### Via script (mission.sqm)

```sqf
// Créer une zone de brouillage
[
    [3500, 2800, 0],  // Position
    200,              // Rayon (mètres)
    "jammer",         // Type
    "Zone Brouillage Ennemi",
    80                // Intensité (%)
] call comspec_overwatch_connect_fnc_createRoleplayZone;
```

### Suppression de zones

```sqf
// Supprimer par ID
["zone_12345"] call comspec_overwatch_connect_fnc_deleteRoleplayZone;

// Lister toutes les zones actives
private _zones = [] call comspec_overwatch_connect_fnc_listRoleplayZones;
```

---

## Déconnexions réseau aléatoires

### Principe

Simule des **micro-coupures réseau** réalistes indépendamment des zones géographiques.

### Comportement

- **Fréquence** : Déconnexion toutes les ~10 minutes (configurable)
- **Durée** : Entre 5 et 30 secondes (aléatoire)
- **Effet** : Blocage complet de toutes les transmissions ATAK

### Messages

```sqf
// Début de coupure
"Perte de liaison ATAK (12s)"

// Fin de coupure
"Liaison ATAK rétablie"
```

### Sons associés

- **Déconnexion** : `ambient_radio18.wss` (statique radio)
- **Reconnexion** : `beep_target.wss` (bip de confirmation)

### État interne

```sqf
// Variable globale trackant l'état
COMSPEC_NetworkDisconnectState = [
    "is_disconnected" => false,
    "disconnect_until" => -1,
    "next_disconnect_at" => 600,
    "disconnect_count" => 0
]
```

### Activation

Paramètres CBA :
- **"Activer le mode roleplay"** : `true`
- **"Pannes réseau simulées"** : `true` (sous-option)

---

## Effets visuels et sonores

### Interface ATAK Enhanced (Web)

Le mod injecte des effets JavaScript dans l'interface web tactique pour renforcer l'immersion.

#### Écran cassé

Lorsque l'écran est détruit :

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

- Overlay noir avec texte rouge
- Animation de glitch aléatoire
- Message alternant FR/EN pour "réalisme"

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

#### Effet de glitch

Déclenché aléatoirement si perte de paquets >10% :

```javascript
// Flash rouge aléatoire de 0.1s
ctrlGlitch ctrlSetBackgroundColor [0.8, 0, 0, 0.2];
```

#### Indicateurs de qualité réseau

```
Signal: ███▓▒░░░░░ 40%
Latence: 350ms
Perte de paquets: 15%
```

### Palette sonore complète

| Événement | Fichier son | Volume |
|-----------|-------------|--------|
| Déconnexion réseau | `ambient_radio18.wss` | 0.8 |
| Reconnexion | `beep_target.wss` | 0.5 |
| Interférence | `ambient_radio17.wss` | 0.4 |
| Alerte de zone | `alarm_independent.wss` | 0.6 |
| Écran cassé | `vehicle_collision.wss` | 0.7 |
| Extinction ATAK | `addItemFailed` | 1.0 |
| Destruction ATAK | `FD_CP_Not_Clear_F` | 1.0 |
| Réparation réussie | `FD_CP_Clear_F` | 1.0 |

Tous les sons utilisent les assets **natifs Arma 3**, aucun fichier externe requis.

---

## Système de réparation

### Actions ACE Self-Interact

Le mod ajoute des actions de réparation dans le menu **ACE > Équipement** :

#### 1. Rallumer l'ATAK
- **Condition** : ATAK éteint mais pas détruit
- **Outil requis** : Aucun
- **Durée** : Instantanée
- **Action** : Rallume l'équipement

#### 2. Réparer l'écran
- **Condition** : Écran détruit, appareil pas détruit
- **Outil requis** : **Toolkit** (dans l'inventaire)
- **Durée** : ~5 secondes (action ACE avec barre de progression)
- **Action** : Répare l'écran, restaure l'affichage

#### 3. Réparation complète
- **Condition** : ATAK partiellement endommagé
- **Outil requis** : **Toolkit**
- **Durée** : ~10 secondes
- **Action** : Répare tous les dommages

#### 4. État ATAK (diagnostic)
- **Condition** : Toujours disponible
- **Outil requis** : Aucun
- **Durée** : Instantanée
- **Action** : Affiche un diagnostic complet

```sqf
// Exemple de diagnostic affiché
"=== État ATAK ===
Alimentation : ✓ OK
Écran : ✗ Détruit
Appareil : ✓ Fonctionnel
Liaison : ● Active

Réparation écran disponible (Toolkit requis)"
```

### Limitations

- **Destruction complète (Niveau 3)** : **Irréparable** par design
- **Toolkit** : Doit être dans l'inventaire du joueur (pas dans le sac à dos)
- **Pendant réparation** : Vulnérable, animation visible

---

## Configuration

### Paramètres CBA (Mod Options)

Tous les paramètres se trouvent dans **Options > Addons Options > COMSPEC Overwatch** :

#### Roleplay général

| Paramètre | Valeurs | Défaut | Description |
|-----------|---------|--------|-------------|
| **Activer le mode roleplay** | ☑/☐ | ☐ | Active tous les systèmes roleplay |
| **Effets visuels de dégradation** | ☑/☐ | ☑ | Glitchs, parasites, messages d'erreur dans l'ATAK web |
| **Pannes réseau simulées** | ☑/☐ | ☐ | Déconnexions aléatoires indépendantes des zones |

#### Réalisme matériel

| Paramètre | Valeurs | Défaut | Description |
|-----------|---------|--------|-------------|
| **Niveau de réalisme matériel ATAK** | 0-3 | 0 | 0=Désactivé, 1=Extinction, 2=Écran, 3=Destruction |

#### Sons et notifications

| Paramètre | Valeurs | Défaut | Description |
|-----------|---------|--------|-------------|
| **Son de notification** | Liste | Discret | Joué lors des alertes (connexion, ordres, messages) |
| **Mode discret** | ☑/☐ | ☐ | Cache les bannières à l'écran (sons maintenus) |

### Configuration côté serveur (Admin)

Les zones roleplay peuvent être préconfigurées dans le fichier de mission :

```sqf
// init.sqf ou initServer.sqf

// Zone 1 : Pas de couverture dans le bunker
[
    [1200, 3400, -10],
    50,
    "no_coverage",
    "Bunker souterrain",
    100
] call comspec_overwatch_connect_fnc_createRoleplayZone;

// Zone 2 : Interférence en ville
[
    [2500, 2500, 0],
    300,
    "interference",
    "Centre-ville",
    60
] call comspec_overwatch_connect_fnc_createRoleplayZone;

// Zone 3 : Brouilleur ennemi
[
    [4000, 1000, 0],
    200,
    "jammer",
    "Secteur brouillé",
    80
] call comspec_overwatch_connect_fnc_createRoleplayZone;
```

### Variables de debug

Pour les développeurs/testeurs :

```sqf
// Forcer une déconnexion immédiate
missionNamespace setVariable ["COMSPEC_NetworkDisconnectState", nil];
[] call comspec_overwatch_connect_fnc_simulateNetworkDisconnect;

// Activer les logs détaillés
missionNamespace setVariable ["COMSPEC_Debug_PacketLoss", true];

// Forcer destruction de l'écran (test)
private _state = missionNamespace getVariable ["COMSPEC_AtakState", createHashMap];
_state set ["screen_destroyed", true];

// Voir toutes les zones actives
private _zones = [] call comspec_overwatch_connect_fnc_listRoleplayZones;
systemChat str _zones;
```

---

## Guide pour Zeus/MJ

### Scénarios recommandés

#### 1. Mission d'infiltration

**Concept** : Traverser une zone ennemie avec brouilleurs actifs.

**Setup** :
```sqf
// Zone de brouillage autour de la base ennemie
[getMarkerPos "enemy_base", 400, "jammer", "Défenses électroniques", 90] 
    call comspec_overwatch_connect_fnc_createRoleplayZone;

// Zones dégradées en périphérie
[getMarkerPos "approach_1", 200, "degraded", "Périphérie", 40] 
    call comspec_overwatch_connect_fnc_createRoleplayZone;
```

**Effet** : Les joueurs perdent progressivement la liaison en approchant, doivent communiquer par radio.

#### 2. Mission CQB urbain

**Concept** : Combats en bâtiments avec interférences.

**Setup** :
```sqf
// Interférences dans les bâtiments
{
    [getPos _x, 80, "interference", "Immeuble", 50] 
        call comspec_overwatch_connect_fnc_createRoleplayZone;
} forEach allMissionObjects "House";
```

**Effet** : Latence et glitchs dans les bâtiments, oblige à sortir pour les updates.

#### 3. Mission sous-terraine

**Concept** : Exploration de bunker sans signal.

**Setup** :
```sqf
// Aucun signal sous terre
[getMarkerPos "bunker_entry", 150, "no_coverage", "Bunker", 100] 
    call comspec_overwatch_connect_fnc_createRoleplayZone;
```

**Effet** : Déconnexion totale dès l'entrée, autonomie complète requise.

#### 4. Mission longue durée

**Concept** : Patrouille avec pannes réseau aléatoires.

**Setup** :
```sqf
// Activer les pannes réseau (paramètre CBA)
// Pas de zones spécifiques
```

**Effet** : Micro-coupures réalistes pendant l'opération, frustration/réalisme.

### Techniques de MJ

#### Intensité progressive

Augmenter l'intensité d'une zone pendant l'action :

```sqf
// Départ : 30% d'interférence
private _zoneId = "zone_123";

// Script progressif
[_zoneId] spawn {
    params ["_id"];
    private _intensity = 30;
    
    while {_intensity < 90} do {
        sleep 300; // Toutes les 5 minutes
        _intensity = _intensity + 15;
        
        // Re-créer la zone avec nouvelle intensité
        // (nécessite de stocker la position/rayon)
    };
};
```

#### Brouilleur mobile

Attacher une zone à un véhicule ennemi :

```sqf
// Véhicule de guerre électronique
private _ewVehicle = _this;

[_ewVehicle] spawn {
    params ["_veh"];
    private _zoneId = "";
    
    while {alive _veh} do {
        // Supprimer ancienne zone
        if (_zoneId != "") then {
            [_zoneId] call comspec_overwatch_connect_fnc_deleteRoleplayZone;
        };
        
        // Créer nouvelle zone à la position actuelle
        private _result = [
            getPosASL _veh,
            300,
            "jammer",
            "Véhicule GE ennemi",
            75
        ] call comspec_overwatch_connect_fnc_createRoleplayZone;
        
        _zoneId = _result get "id";
        
        sleep 5; // Update toutes les 5s
    };
    
    // Supprimer la zone à la destruction
    [_zoneId] call comspec_overwatch_connect_fnc_deleteRoleplayZone;
};
```

#### Récompense/punition

```sqf
// Objectif : détruire le brouilleur ennemi
private _jammer = _this;

_jammer addEventHandler ["Killed", {
    // Supprimer la zone de brouillage
    ["zone_jammer_01"] call comspec_overwatch_connect_fnc_deleteRoleplayZone;
    
    // Message global
    ["Brouilleur détruit ! Liaison ATAK rétablie."] remoteExec ["systemChat", 0];
}];
```

### Commandes Zeus rapides

Ajouter ces raccourcis dans le module Zeus :

```sqf
// Module Zeus custom
class COMSPEC_Zeus_Roleplay {
    // Bouton : Créer zone brouillage
    onMouseButtonDown = {
        private _pos = _this select 1;
        [_pos, 200, "jammer", "Zeus Jammer", 80] 
            call comspec_overwatch_connect_fnc_createRoleplayZone;
    };
    
    // Bouton : Supprimer toutes les zones
    onKeyDown = {
        private _zones = [] call comspec_overwatch_connect_fnc_listRoleplayZones;
        {
            [_x get "id"] call comspec_overwatch_connect_fnc_deleteRoleplayZone;
        } forEach _zones;
        
        systemChat "Toutes les zones roleplay supprimées";
    };
};
```

---

## FAQ

### Est-ce que ça affecte les performances ?

Non. Le système utilise des PerFrameHandlers optimisés avec vérifications conditionnelles. Impact < 1ms par frame.

### Les autres joueurs voient-ils mes zones ?

Oui, les zones sont synchronisées via le serveur. Un Zeus peut affecter tous les joueurs.

### Peut-on désactiver pour certains joueurs ?

Oui, via paramètres CBA individuels. Chaque joueur choisit son niveau de réalisme.

### Les IA sont-elles affectées ?

Non, uniquement les joueurs. Les IA ne dépendent pas du système ATAK.

### Compatibilité avec d'autres mods ?

- ✅ **ACE Medical** : Requis pour système de dommages
- ✅ **ACRE/TFAR** : Indépendant, pas de conflit
- ✅ **cTab** : Peut coexister (fonctions différentes)
- ✅ **Zeus Enhanced** : Modules compatibles

### Peut-on scripter des séquences ?

Oui ! Tous les événements peuvent être déclenchés par script pour des missions scénarisées.

Exemple : Explosion EMP fictive

```sqf
// Explosion EMP désactive tous les ATAK pendant 60s
{
    private _state = _x getVariable ["COMSPEC_AtakState", createHashMap];
    _state set ["powered_on", false];
    _state set ["emp_until", time + 60];
} forEach allPlayers;

// Message dramatique
"⚡ IMPULSION ÉLECTROMAGNÉTIQUE DÉTECTÉE" remoteExec ["systemChat", 0];
playSound3D ["A3\Sounds_F\sfx\alarm_independent.wss", objNull, false, [0,0,0], 2, 1, 1000];
```

---

## Ressources techniques

### Fichiers clés

| Fichier | Description |
|---------|-------------|
| `fn_checkAtakDamage.sqf` | Logique de dommages physiques |
| `fn_applyZoneEffects.sqf` | Application des effets de zones |
| `fn_simulateNetworkDisconnect.sqf` | Déconnexions aléatoires |
| `fn_updateAtakEnhancedRoleplay.sqf` | Effets visuels interface web |
| `fn_repairAtak.sqf` | Système de réparation ACE |
| `fn_createRoleplayZone.sqf` | Création de zones |
| `module_roleplay_zone.hpp` | Définition modules Zeus |

### Documentation complémentaire

- `docs/archive/legacy-atak/ROLEPLAY-ATAK-ENHANCED.md` : note historique (TM-A3-21 / SOP-A3-01)
- `ROLEPLAY-EFFETS-INGAME.md` : Catalogue des effets
- `ROLEPLAY-ZONES-GEOGRAPHIQUES.md` : Guide complet des zones
- `atak-roleplay-simulation.md` : Architecture technique

---

## Changelog

### Version 1.0 (2026-07-24)
- ✨ Système de dommages ATAK (3 niveaux)
- ✨ Zones géographiques roleplay (4 types)
- ✨ Déconnexions réseau aléatoires
- ✨ Effets visuels et sonores complets
- ✨ Système de réparation ACE
- ✨ Modules Zeus/Eden

---

## Crédits

**Développement** : COMSPEC Development Team  
**Design** : Basé sur retours de la communauté milsim  
**Tests** : Communautés partenaires  

**Inspiration** : Systèmes ATAK réels, mods cTab, modules Zeus Enhanced

---

## Support

**Problème technique ?** Ouvrir une issue GitHub  
**Suggestion de feature ?** Discussion dans #dev-suggestions  
**Bug de zone ?** Activer debug et fournir logs

```sqf
// Activer debug complet
missionNamespace setVariable ["COMSPEC_Debug_PacketLoss", true];
[] call comspec_overwatch_connect_fnc_showDebugInfo; // Copier output
```

---

*Document généré automatiquement le 2026-07-24*  
*Version du mod : 1.0.0*
