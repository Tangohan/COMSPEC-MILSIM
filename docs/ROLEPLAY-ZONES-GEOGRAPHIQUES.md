# Zones géographiques roleplay ATAK

**Date** : 24 juillet 2026  
**Module** : COMSPEC Overwatch Connect

---

## 🎯 Vue d'ensemble

Système de zones géographiques pour simuler des variations de couverture réseau selon la position sur la carte. Créables via **Zeus** et **Eden Editor** ou par script.

---

## 📍 Types de zones

### 1. Zone sans couverture (`no_coverage`)

**Description** : Absence totale de liaison ATAK  
**Couleur** : Rouge  
**Effets** :
- ✅ Blocage complet des envois HTTP
- ✅ Message : "Hors de portée réseau"
- ✅ LinkState → "offline"
- ✅ Indicateur ingame : "AUCUNE COUVERTURE"

**Cas d'usage** :
- Vallées profondes
- Bâtiments blindés
- Zones montagneuses
- Sous-sols / tunnels

---

### 2. Zone d'interférence (`interference`)

**Description** : Forte interférence radio  
**Couleur** : Orange  
**Effets** :
- ✅ Packet loss multiplié par 3 (max selon intensité)
- ✅ Qualité dégradée visible ingame
- ✅ Pas de blocage total

**Cas d'usage** :
- Proximité d'antennes
- Zones industrielles
- Interférence électromagnétique
- Activité radio ennemie

---

### 3. Zone de couverture dégradée (`degraded`)

**Description** : Qualité réseau réduite  
**Couleur** : Jaune  
**Effets** :
- ✅ Packet loss multiplié par 1.5 (max selon intensité)
- ✅ Latence ajoutée (+500ms max)
- ✅ Qualité "DÉGRADÉE" ingame

**Cas d'usage** :
- Périphérie de couverture
- Zones rurales
- Obstacles naturels
- Distance de la base

---

### 4. Brouilleur actif (`jammer`)

**Description** : Brouillage ennemi  
**Couleur** : Rose  
**Effets** :
- ✅ Déconnexions intermittentes (chance selon intensité)
- ✅ Packet loss multiplié par 2 (max)
- ✅ Liaison instable

**Cas d'usage** :
- Véhicule ennemi de guerre électronique
- Base ennemie avec brouillage
- Zone de combat
- Objectif stratégique protégé

---

## 🎮 Utilisation Zeus

### Placement d'une zone

1. Ouvrir l'interface Zeus (`Y`)
2. Clic droit → **Modules**
3. **Catégorie** : `COMSPEC Roleplay`
4. Choisir le type :
   - `Zone sans couverture ATAK`
   - `Zone d'interférence ATAK`
   - `Zone de couverture dégradée`
   - `Brouilleur ATAK actif`
5. Placer sur la carte
6. Configurer :
   - **Rayon** : Distance en mètres
   - **Intensité** : Puissance de l'effet (0-100%)
7. Valider

**Résultat** :
- Marqueur coloré apparaît sur la carte
- Zone active immédiatement
- Synchronisée sur tous les clients

### Exemples de configuration

**Vallée isolée** :
```
Type : Sans couverture
Rayon : 200m
Intensité : 100%
```

**Ville avec interférence** :
```
Type : Interférence
Rayon : 500m
Intensité : 50%
```

**Brouilleur sur véhicule** :
```
Type : Brouilleur
Rayon : 300m
Intensité : 80%
(Attacher le module au véhicule avec "Sync")
```

---

## 🗺️ Utilisation Eden Editor

### Placement permanent

1. Ouvrir l'éditeur de mission
2. **F5** → Modules → Systems
3. Catégorie `COMSPEC Roleplay`
4. Glisser-déposer sur la carte
5. Configurer dans les propriétés (F1)
6. Sauvegarder la mission

**Avantage** : Zones présentes dès le lancement de la mission

---

## 💻 Utilisation par script

### Créer une zone

```sqf
// Syntaxe
[position, radius, type, intensity] call comspec_overwatch_connect_fnc_createRoleplayZone;

// Exemples
[getPos player, 200, "no_coverage", 100] call comspec_overwatch_connect_fnc_createRoleplayZone;
[getMarkerPos "zone_interference", 400, "interference", 60] call comspec_overwatch_connect_fnc_createRoleplayZone;
[[3500, 2800, 0], 300, "jammer", 80] call comspec_overwatch_connect_fnc_createRoleplayZone;
```

**Paramètres** :
- `position` : `[x, y, z]` ou objet
- `radius` : Rayon en mètres (nombre)
- `type` : `"no_coverage"` | `"interference"` | `"degraded"` | `"jammer"`
- `intensity` : 0-100 (optionnel, défaut selon type)

**Retour** :
- HashMap avec données de la zone
- Contient l'ID unique pour suppression ultérieure

### Supprimer une zone

```sqf
// Par ID
["zone_12345_67890"] call comspec_overwatch_connect_fnc_deleteRoleplayZone;

// Par index
[0] call comspec_overwatch_connect_fnc_deleteRoleplayZone;
```

### Lister les zones

```sqf
private _zones = [] call comspec_overwatch_connect_fnc_listRoleplayZones;

{
    private _name = _x get "name";
    private _pos = _x get "position";
    private _radius = _x get "radius";
    
    hint format ["Zone: %1 à %2 (rayon %3m)", _name, _pos, _radius];
} forEach _zones;
```

### Obtenir la zone du joueur

```sqf
private _currentZone = [] call comspec_overwatch_connect_fnc_getPlayerRoleplayZone;

if (!isNil "_currentZone") then {
    private _type = _currentZone get "type";
    private _intensity = _currentZone get "intensity";
    
    hint format ["Dans zone %1 (intensité %2%%)", _type, _intensity];
} else {
    hint "Hors de toute zone";
};
```

---

## 🔧 Effets techniques détaillés

### Hiérarchie des effets

Si le joueur est dans **plusieurs zones** simultanément :
- ✅ La zone avec l'**intensité la plus forte** prend le dessus
- ✅ Changement détecté toutes les 2 secondes
- ✅ Notification à l'entrée/sortie

### Multiplicateurs appliqués

**Zone sans couverture** :
```sqf
force_disconnect = true
packet_loss_multiplier = 1.0
```

**Zone d'interférence** :
```sqf
force_disconnect = false
packet_loss_multiplier = (intensité / 100) * 3  // Max x3
```

**Zone dégradée** :
```sqf
force_disconnect = false
packet_loss_multiplier = (intensité / 100) * 1.5  // Max x1.5
latency_add = (intensité / 100) * 500  // Max +500ms
```

**Brouilleur** :
```sqf
force_disconnect = random 100 < (intensité / 2)  // Max 50% chance
packet_loss_multiplier = (intensité / 100) * 2  // Max x2
```

### Intégration avec le système existant

Les zones **s'ajoutent** aux effets roleplay de base :

```
Packet loss final = Packet loss de base × Multiplicateur de zone

Exemples :
- Base : 2% | Zone interférence 50% → 2% × 1.5 = 3%
- Base : 5% | Zone interférence 100% → 5% × 3 = 15%
- Base : 0% | Zone dégradée 60% → 0% × 0.9 = 0% (latence seulement)
```

---

## 📊 Variables globales

### `COMSPEC_RoleplayZones`

Array global contenant toutes les zones actives.

**Structure** :
```sqf
[
    createHashMap [
        ["id", "zone_123456_78901"],
        ["position", [3500, 2800, 0]],
        ["radius", 200],
        ["type", "interference"],
        ["intensity", 50],
        ["name", "Zone d'interférence"],
        ["color", "ColorOrange"],
        ["marker", "comspec_roleplay_zone_123456"],
        ["created_at", 125.6]
    ],
    // ... autres zones
]
```

### `COMSPEC_ZoneEffects`

HashMap local contenant les effets de la zone actuelle du joueur.

**Structure** :
```sqf
createHashMap [
    ["force_disconnect", false],
    ["packet_loss_multiplier", 1.5],
    ["latency_add", 250]
]
```

**Valeur** : `nil` si hors de toute zone

---

## 🎬 Exemples de scénarios

### 1. Mission d'infiltration

**Objectif** : Désactiver un brouilleur ennemi

```sqf
// init.sqf
private _brouilleur = createVehicle ["Land_Communication_anchor_F", getMarkerPos "obj_brouilleur", [], 0, "NONE"];
private _zone = [_brouilleur, 500, "jammer", 90] call comspec_overwatch_connect_fnc_createRoleplayZone;

// Quand détruit
_brouilleur addEventHandler ["Killed", {
    ["zone_brouilleur_principal"] call comspec_overwatch_connect_fnc_deleteRoleplayZone;
    hint "Brouilleur neutralisé - Liaison ATAK rétablie";
}];
```

### 2. Zone de combat dynamique

**Objectif** : Interférence autour des combats

```sqf
// Trigger zone combat
while {true} do {
    {
        if (side _x == east && {alive _x}) then {
            private _zone = [_x, 300, "interference", 40] call comspec_overwatch_connect_fnc_createRoleplayZone;
            
            // Supprimer quand l'unité meurt
            _x addEventHandler ["Killed", {
                params ["_unit"];
                private _unitZones = COMSPEC_RoleplayZones select {
                    (_x get "position") distance2D (getPos _unit) < 10
                };
                
                {
                    [_x get "id"] call comspec_overwatch_connect_fnc_deleteRoleplayZone;
                } forEach _unitZones;
            }];
        };
    } forEach allUnits;
    
    sleep 60;
};
```

### 3. Progression tactique

**Objectif** : Zones fixes à traverser

```sqf
// Zones permanentes Eden
// Zone 1 : Vallée (pas de couverture)
private _zone1 = [getMarkerPos "zone_vallee", 400, "no_coverage", 100] call comspec_overwatch_connect_fnc_createRoleplayZone;

// Zone 2 : Ville (interférence)
private _zone2 = [getMarkerPos "zone_ville", 600, "interference", 60] call comspec_overwatch_connect_fnc_createRoleplayZone;

// Zone 3 : Base ennemie (brouilleur)
private _zone3 = [getMarkerPos "zone_base", 800, "jammer", 85] call comspec_overwatch_connect_fnc_createRoleplayZone;
```

---

## 🎨 Marqueurs et visualisation

### Apparence des marqueurs

Les zones sont **automatiquement marquées** sur la carte :

**Format** : `[Type] ([Rayon]m - [Intensité]%)`

**Exemples** :
- `Absence de couverture (200m - 100%)`
- `Zone d'interférence (400m - 60%)`
- `Brouilleur actif (300m - 80%)`

**Style** :
- Forme : Ellipse
- Bordure : Pointillée
- Remplissage : 50% transparence
- Couleur : Selon le type

### Cacher les marqueurs

```sqf
// Obtenir le marqueur d'une zone
private _zone = COMSPEC_RoleplayZones select 0;
private _marker = _zone get "marker";

// Cacher
_marker setMarkerAlpha 0;

// Réafficher
_marker setMarkerAlpha 0.5;
```

---

## ⚠️ Limitations et notes

### Performance

- **Vérification** : Toutes les 2 secondes
- **Impact** : Négligeable (<0.01ms par zone)
- **Optimisation** : Utiliser des rayons raisonnables (<1000m)

### Zones multiples

- ✅ Nombre illimité de zones
- ✅ Superposition autorisée
- ⚠️ Priorité à l'intensité la plus forte

### Synchronisation

- ✅ Zones synchronisées automatiquement (JIP safe)
- ✅ Variable `publicVariable` appelée
- ✅ Fonctionne en dédié et local

### Compatibilité

- ✅ Zeus
- ✅ Eden Editor
- ✅ Scripts
- ✅ Headless Client (zones créées sur serveur)
- ❌ Pas de persistence (recréer à chaque mission)

---

## 🐛 Dépannage

### Les zones ne s'affichent pas

**Vérifier** :
```sqf
// Lister les zones
hint str ([] call comspec_overwatch_connect_fnc_listRoleplayZones);

// Vérifier la variable
hint str (isNil "COMSPEC_RoleplayZones");
```

**Solution** :
- Vérifier que le roleplay est activé dans CBA
- S'assurer que les modules sont bien placés
- Vérifier les logs RPT

### Les effets ne s'appliquent pas

**Vérifier** :
```sqf
// Zone actuelle du joueur
private _zone = [] call comspec_overwatch_connect_fnc_getPlayerRoleplayZone;
hint str _zone;

// Effets appliqués
private _effects = missionNamespace getVariable ["COMSPEC_ZoneEffects", nil];
hint str _effects;
```

**Solution** :
- Attendre 2 secondes (fréquence du PFH)
- Vérifier que le joueur est bien dans le rayon
- Activer debug : `COMSPEC_Debug_Zones = true`

### Supprimer toutes les zones

```sqf
// Script d'urgence
{
    [_x get "id"] call comspec_overwatch_connect_fnc_deleteRoleplayZone;
} forEach ([] call comspec_overwatch_connect_fnc_listRoleplayZones);

hint "Toutes les zones supprimées";
```

---

## 📈 Métriques et stats

### Statistiques disponibles

```sqf
// Nombre de zones actives
private _count = count ([] call comspec_overwatch_connect_fnc_listRoleplayZones);

// Zone la plus intense
private _zones = [] call comspec_overwatch_connect_fnc_listRoleplayZones;
_zones sort {(_b get "intensity") - (_a get "intensity")};
private _strongest = _zones select 0;

// Temps dans les zones
// (à implémenter avec un tracker custom)
```

---

**Document rédigé pour COMSPEC MILSIM — Juillet 2026**
