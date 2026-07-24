# 🎮 Guide Rapide Zeus - Fonctionnalités Roleplay ATAK

Guide de référence rapide pour les Zeus/MJ qui veulent utiliser les modules roleplay en mission.

---

## 🚀 Accès rapide

### Modules disponibles

Ouvrir Zeus → Catégorie **"COMSPEC Overwatch Roleplay"**

```
├─ 🔴 Zone sans couverture (No Coverage)
├─ 📡 Zone d'interférence (Interference)  
├─ 📶 Zone dégradée (Degraded)
└─ 🚫 Brouilleur actif (Jammer)
```

---

## 📋 Cheat Sheet

### Zone sans couverture 🔴

| Paramètre | Valeur recommandée |
|-----------|-------------------|
| **Rayon** | 50-150m (bâtiments, bunkers) |
| **Intensité** | 100% (fixe) |
| **Effet** | Déconnexion totale |
| **Usage** | Souterrains, abris blindés |

**Placement** : Sur les objectifs stratégiques, bâtiments critiques.

```sqf
// Exemple script
[getPos bunker_1, 80, "no_coverage", "Bunker", 100] 
    call comspec_overwatch_connect_fnc_createRoleplayZone;
```

---

### Zone d'interférence 📡

| Paramètre | Valeur recommandée |
|-----------|-------------------|
| **Rayon** | 200-400m (zones urbaines) |
| **Intensité** | 40-80% |
| **Effet** | Perte de paquets ×3 max |
| **Usage** | Villes, zones industrielles |

**Placement** : Centres urbains, bases ennemies.

```sqf
// Exemple script
[getMarkerPos "city_center", 300, "interference", "Centre-ville", 60] 
    call comspec_overwatch_connect_fnc_createRoleplayZone;
```

---

### Zone dégradée 📶

| Paramètre | Valeur recommandée |
|-----------|-------------------|
| **Rayon** | 300-600m (périphéries) |
| **Intensité** | 30-60% |
| **Effet** | Latence +500ms, perte ×1.5 |
| **Usage** | Forêts, collines, périphéries |

**Placement** : Zones de transition, approches.

```sqf
// Exemple script
[getMarkerPos "forest_approach", 400, "degraded", "Forêt dense", 45] 
    call comspec_overwatch_connect_fnc_createRoleplayZone;
```

---

### Brouilleur actif 🚫

| Paramètre | Valeur recommandée |
|-----------|-------------------|
| **Rayon** | 200-500m (équipement ennemi) |
| **Intensité** | 70-95% |
| **Effet** | Déconnexions intermittentes |
| **Usage** | Défenses ennemies, véhicules GE |

**Placement** : Autour des objectifs, véhicules ennemis.

```sqf
// Exemple script
[getPos enemy_hq, 350, "jammer", "Défenses GE", 85] 
    call comspec_overwatch_connect_fnc_createRoleplayZone;
```

---

## 🎯 Scénarios pré-configurés

### Scénario 1 : Infiltration progressive

**Durée** : 45-60 min  
**Difficulté** : ⭐⭐⭐

```sqf
// Zone 1 : Approche (dégradé léger)
[approachPos, 500, "degraded", "Périphérie", 30] call createZone;

// Zone 2 : Ville (interférence moyenne)
[cityPos, 400, "interference", "Zone urbaine", 55] call createZone;

// Zone 3 : Objectif (brouilleur fort)
[objectivePos, 250, "jammer", "Base ennemie", 85] call createZone;
```

**Effet** : Difficulté croissante, joueurs doivent s'adapter.

---

### Scénario 2 : Bunker raid

**Durée** : 30-45 min  
**Difficulté** : ⭐⭐⭐⭐

```sqf
// Surface : OK
// Entrée : Dégradé
[bunkerEntrance, 100, "degraded", "Entrée", 40] call createZone;

// Sous-sol : No signal
[bunkerInterior, 120, "no_coverage", "Bunker", 100] call createZone;
```

**Effet** : Connexion perdue dès l'entrée, retour aux basics.

---

### Scénario 3 : Chasse au brouilleur

**Durée** : 60+ min  
**Difficulté** : ⭐⭐⭐⭐⭐

```sqf
// Brouilleur mobile sur véhicule
_jammerVeh = // véhicule ennemi

[_jammerVeh] spawn {
    params ["_veh"];
    while {alive _veh} do {
        [getPosASL _veh, 400, "jammer", "Brouilleur mobile", 90] call createZone;
        sleep 10;
    };
};
```

**Effet** : Zone qui se balade, joueurs doivent localiser et détruire.

---

### Scénario 4 : Ville en guerre électronique

**Durée** : 45-90 min  
**Difficulté** : ⭐⭐⭐⭐

```sqf
// Interférences dans tous les bâtiments
{
    if (_x isKindOf "House") then {
        [getPos _x, 60, "interference", "Bâtiment", 50 + random 30] call createZone;
    };
} forEach nearestObjects [cityCenter, ["House"], 800];
```

**Effet** : CQB urbain avec coupures fréquentes.

---

## ⚡ Commandes rapides

### Créer zone à la position du curseur

```sqf
// Copier-coller dans console debug Zeus
_pos = screenToWorld [0.5, 0.5];
[_pos, 200, "jammer", "Zone Zeus", 75] call comspec_overwatch_connect_fnc_createRoleplayZone;
hint "Zone créée !";
```

### Lister toutes les zones actives

```sqf
private _zones = [] call comspec_overwatch_connect_fnc_listRoleplayZones;
systemChat format ["Zones actives : %1", count _zones];
{
    systemChat format ["- %1 (%2)", _x get "name", _x get "type"];
} forEach _zones;
```

### Supprimer toutes les zones

```sqf
private _zones = [] call comspec_overwatch_connect_fnc_listRoleplayZones;
{
    [_x get "id"] call comspec_overwatch_connect_fnc_deleteRoleplayZone;
} forEach _zones;
hint "Toutes les zones supprimées";
```

### Modifier intensité d'une zone

```sqf
// Note : il faut recréer la zone avec les nouveaux paramètres
// Stocker position/rayon, supprimer, recréer
```

---

## 🎨 Codes couleur des zones

| Type | Couleur marqueur | Opacité |
|------|-----------------|---------|
| No Coverage | `ColorRed` | 0.4 |
| Interference | `ColorOrange` | 0.3 |
| Degraded | `ColorYellow` | 0.2 |
| Jammer | `ColorPink` | 0.35 |

Les marqueurs sont visibles uniquement en mode Zeus/Eden, pas pour les joueurs.

---

## 📊 Tableau d'intensité recommandée

| Situation tactique | Type zone | Intensité |
|-------------------|-----------|-----------|
| Approche lointaine | Degraded | 20-30% |
| Forêt/collines | Degraded | 40-50% |
| Zone urbaine | Interference | 50-70% |
| Base ennemie | Jammer | 70-85% |
| Objectif critique | Jammer | 85-95% |
| Souterrain | No Coverage | 100% |
| Bunker blindé | No Coverage | 100% |

---

## 🛡️ Équilibrage

### Règles d'or

1. **Progression logique** : Augmenter la difficulté graduellement
2. **Zones limitées** : Max 5-7 zones simultanées
3. **Raisons narratives** : Expliquer pourquoi (brouilleur ennemi, bâtiment blindé...)
4. **Contre-mesures** : Permettre de détruire les brouilleurs
5. **Pas de surprise totale** : Prévenir que le roleplay est activé

### Durée recommandée des zones

| Durée mission | Nombre zones | Intensité max |
|--------------|--------------|---------------|
| < 30 min | 1-2 | 60% |
| 30-60 min | 2-4 | 75% |
| 60-90 min | 3-6 | 85% |
| > 90 min | 4-8 | 95% |

---

## 🎭 Communication avec les joueurs

### Avant la mission

```
"Cette mission utilise le système roleplay ATAK.
Attendez-vous à des perturbations de liaison dans certaines zones.
Préparez vos Toolkits et privilégiez la communication radio."
```

### Pendant la mission (hints)

```sqf
// Avertissement immersif
"Renseignements indiquent présence d'équipement de brouillage ennemi dans le secteur." 
remoteExec ["hint", 0];
```

### Feedback post-mission

```sqf
// Statistiques
private _zones = [] call comspec_overwatch_connect_fnc_listRoleplayZones;
systemChat format ["Mission terminée. Zones roleplay actives : %1", count _zones];
```

---

## 🔧 Troubleshooting

### "Les zones ne fonctionnent pas"

**Vérifier** :
1. Paramètre CBA **"Activer le mode roleplay"** : ✅
2. Joueurs ont-ils le mod à jour ?
3. Zones bien créées (vérifier avec listRoleplayZones) ?

### "Effet trop faible"

**Solutions** :
- Augmenter intensité (75%+)
- Réduire le rayon (concentration)
- Passer au type supérieur (degraded → interference → jammer)

### "Effet trop fort"

**Solutions** :
- Réduire intensité (30-50%)
- Augmenter le rayon (dilution)
- Passer au type inférieur (jammer → interference → degraded)

### "Les joueurs ne comprennent rien"

**Solution** :
- Ajouter hints explicatifs
- Messages radio RP ("Liaison dégradée détectée")
- Montrer les marqueurs temporairement

---

## 📱 Intégration avec autres systèmes

### Avec ACRE/TFAR

Zones roleplay n'affectent **pas** la radio. Design intentionnel : force l'usage de la radio comme backup.

### Avec ACE Medical

Blessures graves → ATAK endommagé (si réalisme activé niveau 2+).  
**Toolkit ACE** requis pour réparations.

### Avec Zeus Enhanced

Modules compatibles, peuvent être combinés avec modules ZEN.

---

## 🎪 Tips avancés

### Brouilleur destructible

```sqf
// Créer objet physique + zone
_jammer = "Land_TTowerBig_2_F" createVehicle _pos;
_zoneId = [_pos, 300, "jammer", "Brouilleur", 90] call createZone;

_jammer addEventHandler ["Killed", {
    [_zoneId] call comspec_overwatch_connect_fnc_deleteRoleplayZone;
    "Brouilleur détruit ! Liaison rétablie." remoteExec ["hint", 0];
}];
```

### Zone progressive

```sqf
// Intensité augmente avec le temps
[_pos, _radius, "interference", "Zone évolutive"] spawn {
    params ["_p", "_r", "_t", "_n"];
    private _intensity = 30;
    
    while {_intensity < 90} do {
        [_p, _r, _t, _n, _intensity] call createZone;
        sleep 180; // +15% toutes les 3min
        _intensity = _intensity + 15;
    };
};
```

### Zone aléatoire mobile

```sqf
// Zone qui se téléporte aléatoirement
[] spawn {
    while {true} do {
        _randomPos = [worldSize / 2, worldSize / 2, 0] getPos [random 2000, random 360];
        [_randomPos, 200, "jammer", "Anomalie", 70] call createZone;
        sleep 300; // Change toutes les 5min
    };
};
```

---

## 📚 Ressources

- **Documentation complète** : `MOD-FONCTIONNALITES-ROLEPLAY-TROLL.md`
- **Résumé fun** : `FONCTIONNALITES-TROLL-RESUME.md`
- **Guides techniques** : `docs/technique/atak-roleplay-simulation.md`

---

## 🎯 Checklist pré-mission

```
[ ] Paramètre roleplay activé (CBA)
[ ] Zones placées et testées
[ ] Intensités équilibrées
[ ] Joueurs prévenus
[ ] Toolkit distribués (si réalisme matériel activé)
[ ] Scénario narratif préparé (pourquoi les zones ?)
[ ] Plan B si trop difficile (commandes suppression)
```

---

## 🎬 Exemples de narratives

### Tech-thriller
```
"L'ennemi dispose d'équipements de guerre électronique de dernière génération.
Nos liaisons ATAK seront perturbées dans le secteur opérationnel.
Détruisez les émetteurs de brouillage pour restaurer les communications."
```

### Survie
```
"Cette région montagneuse est réputée pour sa mauvaise couverture réseau.
Attendez-vous à des pertes de signal fréquentes.
Restez groupés et privilégiez les communications radio."
```

### Post-apocalyptique
```
"Les infrastructures réseau sont en ruines dans cette zone.
Les liaisons satellites sont sporadiques au mieux.
Préparez-vous à opérer en autonomie complète."
```

---

## 💡 Le saviez-vous ?

- **Les zones empilent** : Placer plusieurs zones au même endroit cumule les effets
- **Intensité 0%** : La zone existe mais n'a aucun effet (utile pour tests)
- **Rayon illimité** : Techniquement possible mais déconseillé (performance)
- **ID uniques** : Format `zone_[timestamp]_[random]` pour éviter conflits

---

**Version** : 1.0.0  
**Dernière mise à jour** : 2026-07-24  
**Support** : Discord #zeus-support

---

*"The best Zeus is the one who balances challenge and fun."*
