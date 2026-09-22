# Guide de test — Météo et Control Measures

## Vue d'ensemble

Ce guide documente les tests pour valider les deux nouvelles fonctionnalités :
1. **Effets météo sur les communications radio** (pluie, brouillard, vent)
2. **Control Measures MIL-STD-2525D** (axes, LD, LOA, phase lines, objectifs, checkpoints)

---

## 1. Tests effets météo

### 1.1 Configuration initiale

**Prérequis** :
- Config réalisme créée avec météo activée
- Serveur Arma 3 avec mod ATHENA C2
- Extension COMSPEC fonctionnelle

**Config JSON de base** :
```json
{
  "radio_relays": {
    "weather_effects_enabled": true,
    "relay_range_m": 2000,
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

---

### 1.2 Test 1 : Temps clair (baseline)

**Objectif** : Vérifier que sans météo, la portée reste nominale.

**Procédure** :
1. Lancer mission Arma 3 avec météo claire :
   ```sqf
   0 setRain 0;
   0 setFog 0;
   0 setOvercast 0;
   setWind [0, 0, false];
   ```
2. Placer un relais :
   ```sqf
   _relay = [player getPos [0, 0], 2000] call ATHENA_fnc_placeAtakRelay;
   ```
3. Calculer portée effective :
   ```sqf
   _effects = [2000] call ATHENA_fnc_calculateWeatherEffects;
   hint format["Portée effective: %1m (%2)", _effects select 0, _effects select 3];
   ```

**Résultat attendu** :
- Portée effective : **2000m** (100%)
- Description : "Temps clair"

---

### 1.3 Test 2 : Pluie légère

**Objectif** : Vérifier réduction de portée sous pluie.

**Procédure** :
1. Activer pluie :
   ```sqf
   0 setRain 0.5;
   ```
2. Recalculer portée :
   ```sqf
   _effects = [2000] call ATHENA_fnc_calculateWeatherEffects;
   ```

**Résultat attendu** :
- Portée effective : **1700m** (85%)
- Description : "Pluie (85%)"

---

### 1.4 Test 3 : Brouillard dense

**Objectif** : Vérifier réduction de portée sous brouillard.

**Procédure** :
1. Activer brouillard :
   ```sqf
   0 setFog [0.7, 0, 0];
   ```
2. Recalculer portée.

**Résultat attendu** :
- Portée effective : **1400m** (70%)
- Description : "Brouillard (70%)"

---

### 1.5 Test 4 : Orage (pluie + nuages épais)

**Objectif** : Vérifier réduction maximale sous orage.

**Procédure** :
1. Activer orage :
   ```sqf
   0 setRain 0.8;
   0 setOvercast 0.9;
   ```
2. Recalculer portée.

**Résultat attendu** :
- Portée effective : **1200m** (60%)
- Description : "Orage (60%)"

---

### 1.6 Test 5 : Vent fort

**Objectif** : Vérifier pénalité vent.

**Procédure** :
1. Activer vent fort (80 km/h) :
   ```sqf
   setWind [22, 0, false]; // 22 m/s ≈ 80 km/h
   ```
2. Recalculer portée.

**Résultat attendu** :
- Portée effective : **1700m** (85%)
  - Seuil : 50 km/h
  - Dépassement : 30 km/h → 3 × 10 km/h
  - Pénalité : 3 × 5% = 15%
- Description : "Temps clair × Vent 80 km/h (85%)"

---

### 1.7 Test 6 : Pluie + vent (cumul)

**Objectif** : Vérifier que météo et vent se cumulent.

**Procédure** :
1. Activer pluie + vent :
   ```sqf
   0 setRain 0.5;
   setWind [22, 0, false];
   ```
2. Recalculer portée.

**Résultat attendu** :
- Portée effective : **1445m** (72.25%)
  - Pluie : 85%
  - Vent : 85%
  - Total : 0.85 × 0.85 = 0.7225
- Description : "Pluie (85%) × Vent 80 km/h (85%)"

---

### 1.8 Test 7 : Orage + vent extrême (pire cas)

**Objectif** : Vérifier cap à -50% sur le vent.

**Procédure** :
1. Activer orage + vent extrême (150 km/h) :
   ```sqf
   0 setRain 0.8;
   0 setOvercast 0.9;
   setWind [42, 0, false]; // 42 m/s ≈ 150 km/h
   ```
2. Recalculer portée.

**Résultat attendu** :
- Portée effective : **600m** (30%)
  - Orage : 60%
  - Vent : Cap à 50% (car 150 km/h > seuil + 100 km/h)
  - Total : 0.60 × 0.50 = 0.30
- Description : "Orage (60%) × Vent 150 km/h (50%)"

---

### 1.9 Test 8 : Admin — Modifier multiplicateurs

**Objectif** : Vérifier que la config admin impacte les calculs.

**Procédure** :
1. Aller dans admin `/admin/atak/realism/config`
2. Onglet "📡 Relais radio"
3. Modifier `rain_range_multiplier` de 0.85 à **0.95**
4. Sauvegarder
5. Retourner en jeu
6. Recalculer portée sous pluie

**Résultat attendu** :
- Portée effective : **1900m** (95%)
- Description : "Pluie (95%)"

---

### 1.10 Test 9 : Désactiver météo

**Objectif** : Vérifier que désactiver `weather_effects_enabled` annule les effets.

**Procédure** :
1. Admin : décocher "Effets météo activés"
2. Sauvegarder
3. En jeu, activer orage
4. Recalculer portée

**Résultat attendu** :
- Portée effective : **2000m** (100%)
- Description : "Effets météo désactivés"

---

### 1.11 Test 10 : Calculateur web

**Objectif** : Vérifier que le calculateur admin fonctionne.

**Procédure** :
1. Admin `/admin/atak/realism/config` → onglet "📡 Relais radio"
2. Section "💡 Calculateur d'impact"
3. Saisir :
   - Portée base : 2000m
   - Condition : Orage
   - Vent : 80 km/h
4. Observer résultat

**Résultat attendu** :
- Portée effective : **1020m**
  - Orage : 60%
  - Vent : 85%
  - Total : 2000 × 0.60 × 0.85 = 1020m
- Détail : "Multiplicateur : 51% (météo 60% × vent 85%)"

---

### 1.12 Test 11 : Synchronisation temps réel (Tacmap)

**Objectif** : Vérifier que les cercles de portée s'adaptent à la météo en temps réel.

**Procédure** :
1. Placer un relais en jeu
2. Ouvrir Tacmap web
3. Observer cercle de portée : devrait afficher **2000m** (temps clair)
4. En jeu, activer pluie
5. Attendre 10s (polling)
6. Observer cercle Tacmap

**Résultat attendu** :
- Cercle de portée se réduit à **1700m**
- Tooltip du relais affiche "🌧️ Pluie (85%)"

---

## 2. Tests Control Measures

### 2.1 Configuration initiale

**Config JSON de base** :
```json
{
  "control_measures": {
    "enabled": true,
    "axis_naming_enabled": true,
    "axis_default_width_m": 500,
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

---

### 2.2 Test 1 : Créer un Axis of Advance (Zeus)

**Objectif** : Placer un axe d'attaque depuis Zeus.

**Procédure** :
1. Ouvrir Zeus
2. Module ATHENA > Control Measures > Axis of Advance
3. Cliquer sur la carte pour définir les points (3+ points)
4. Double-clic pour terminer
5. Entrer nom : **NEPTUNE**
6. Valider

**Résultat attendu** :
- Polyline jaune apparaît sur carte in-game
- Label "AXIS NEPTUNE" au centre de l'axe
- Largeur du couloir : 500m (semi-transparent)
- Axe visible par toute l'équipe

---

### 2.3 Test 2 : Voir l'axe sur Tacmap

**Objectif** : Vérifier synchronisation Zeus → web.

**Procédure** :
1. Après création de l'axe en Zeus
2. Ouvrir Tacmap web
3. Panneau latéral > Control Measures
4. Vérifier liste

**Résultat attendu** :
- "AXIS NEPTUNE" apparaît dans la liste
- Axe affiché sur carte web avec label
- Couleur : #FFD700 (gold)

---

### 2.4 Test 3 : Créer une Line of Departure (web)

**Objectif** : Placer une LD depuis Tacmap.

**Procédure** :
1. Tacmap > Outils > Control Measures > Line of Departure
2. Dessiner ligne (2+ points)
3. Entrer nom : **LD BLUE**
4. Valider

**Résultat attendu** :
- Ligne verte épaisse apparaît sur carte web
- Label "LD BLUE" au centre
- Synchronisation vers mod Arma (markers in-game)

---

### 2.5 Test 4 : Créer un objectif

**Objectif** : Placer un objectif nommé.

**Procédure** :
1. Zeus ou Tacmap > Objective
2. Cliquer position
3. Rayon : 200m (défaut)
4. Nom : **OBJ WOLF**
5. Valider

**Résultat attendu** :
- Cercle orange (rayon 200m) avec remplissage semi-transparent
- Label "OBJ WOLF" au centre
- Synchronisé bi-directionnel (Zeus ↔ web)

---

### 2.6 Test 5 : Checkpoints auto-numérotés

**Objectif** : Placer des checkpoints et vérifier auto-numérotation.

**Procédure** :
1. Zeus > Checkpoint
2. Placer 3 checkpoints le long d'une route
3. Ne pas saisir de nom (auto)

**Résultat attendu** :
- CP1, CP2, CP3 apparaissent automatiquement
- Icônes drapeau 🚩
- Cercle d'influence 50m en pointillés

---

### 2.7 Test 6 : Phase Lines

**Objectif** : Marquer des phases d'opération.

**Procédure** :
1. Tacmap > Phase Line
2. Dessiner ligne horizontale
3. Nom : **PL ORANGE**
4. Valider
5. Créer 2e phase line : **PL GOLD**

**Résultat attendu** :
- PL ORANGE : ligne jaune tirets courts
- PL GOLD : ligne jaune tirets courts
- Labels distincts

---

### 2.8 Test 7 : Limit of Advance

**Objectif** : Marquer limite de progression.

**Procédure** :
1. Zeus > LOA
2. Dessiner ligne
3. Nom : **LOA RED**

**Résultat attendu** :
- Ligne rouge épaisse, tirets longs
- Label "LOA RED"

---

### 2.9 Test 8 : Permissions d'édition

**Objectif** : Vérifier que seuls les rôles autorisés peuvent modifier.

**Procédure** :
1. Créer un axe en tant que `commander`
2. Se connecter en tant que `rifleman` (role non autorisé)
3. Tenter de supprimer l'axe depuis Tacmap

**Résultat attendu** :
- Erreur 403 Forbidden
- Message : "Rôle non autorisé à modifier ce control measure"

---

### 2.10 Test 9 : Visibilité par équipe

**Objectif** : Vérifier isolation par équipe.

**Procédure** :
1. Team 1 (BLUFOR) : créer "AXIS NEPTUNE"
2. Team 2 (OPFOR) : ouvrir Tacmap
3. Vérifier liste control measures

**Résultat attendu** :
- Team 2 ne voit PAS "AXIS NEPTUNE"
- Isolation complète entre équipes

---

### 2.11 Test 10 : Modifier un control measure existant

**Objectif** : Éditer nom/couleur/position.

**Procédure** :
1. Cliquer sur "AXIS NEPTUNE" dans Tacmap
2. Modal d'édition s'ouvre
3. Changer nom → **AXIS MARS**
4. Changer couleur → #FF6600
5. Sauvegarder

**Résultat attendu** :
- Axe renommé "AXIS MARS"
- Couleur orange
- Synchronisation vers mod Arma

---

### 2.12 Test 11 : Supprimer un control measure

**Objectif** : Supprimer depuis Tacmap.

**Procédure** :
1. Cliquer sur "LD BLUE"
2. Bouton "Supprimer"
3. Confirmer

**Résultat attendu** :
- LD disparaît de Tacmap
- Markers supprimés en jeu (Zeus)

---

### 2.13 Test 12 : Admin — Modifier préfixes et couleurs

**Objectif** : Vérifier impact config admin sur nouveaux control measures.

**Procédure** :
1. Admin `/admin/atak/realism/config` → onglet "🎖️ Control Measures"
2. Modifier `axis_label_prefix` : **AXE**
3. Modifier `ld_default_color` : **#0000ff** (bleu)
4. Sauvegarder
5. Créer un nouvel axe : devrait afficher "**AXE** MARS"
6. Créer une nouvelle LD : devrait être **bleue**

**Résultat attendu** :
- Nouveau préfixe appliqué
- Nouvelle couleur appliquée
- **Ancien axe "AXIS NEPTUNE" garde son préfixe original** (pas rétroactif)

---

### 2.14 Test 13 : Désactiver un type de control measure

**Objectif** : Vérifier que désactiver un type empêche sa création.

**Procédure** :
1. Admin : décocher "Objectives activés"
2. Sauvegarder
3. Zeus > tenter de créer un objectif

**Résultat attendu** :
- Module "Objective" grisé ou absent
- Message : "Les objectifs sont désactivés dans la configuration"

---

### 2.15 Test 14 : Export KML (futur)

**Objectif** : Exporter les control measures pour interopérabilité.

**Procédure** :
1. Tacmap > Control Measures > Bouton "Exporter KML"
2. Télécharger fichier `control_measures_team3.kml`
3. Ouvrir dans Google Earth

**Résultat attendu** :
- Tous les axes, LD, LOA, objectifs visibles dans Google Earth
- Labels corrects
- Couleurs préservées

---

### 2.16 Test 15 : Rendu MIL-STD-2525D (futur)

**Objectif** : Vérifier conformité symbologie.

**Procédure** :
1. Tacmap > Activer "Mode MIL-STD-2525D"
2. Observer axes et objectifs

**Résultat attendu** :
- Symboles conformes à MIL-STD-2525D
- SIDCs corrects :
  - Axis : `G*GPOLAA---****X`
  - LD : `G*GPOLLD---****X`
  - LOA : `G*GPOLLA---****X`
  - Phase Line : `G*GPOLLP---****X`
  - Objective : `G*GPOLEO---****X`
  - Checkpoint : `G*GPIGPC---****X`

---

## 3. Tests de régression

### 3.1 Test : Relais sans météo activée

**Objectif** : Vérifier que désactiver météo ne casse pas les relais.

**Procédure** :
1. Admin : décocher "Effets météo activés"
2. Placer relais en jeu
3. Vérifier portée

**Résultat attendu** :
- Portée = valeur nominale (2000m)
- Pas d'erreur console

---

### 3.2 Test : Control measures sans config centralisée (fallback)

**Objectif** : Vérifier fallback si API config échoue.

**Procédure** :
1. Couper temporairement API `/api/atak/realism/config` (502)
2. Tenter de créer un axe depuis Zeus

**Résultat attendu** :
- Axe créé avec valeurs par défaut hardcodées
- Warning log : "Config API unreachable, using fallback"

---

### 3.3 Test : Migration depuis ancienne version

**Objectif** : Vérifier que les anciens relais migrent correctement.

**Procédure** :
1. Base de données avec anciens relais (avant Phase 1)
2. Lancer seed `/workspace/bootstrap/atak_realism_config_seed.php`
3. Vérifier table `atak_realism_config` créée
4. Vérifier anciennes valeurs migrées

**Résultat attendu** :
- Toutes les valeurs anciennes présentes dans `config_json`
- Pas de perte de données

---

## 4. Tests de performance

### 4.1 Test : Cache config (PHP)

**Objectif** : Vérifier que le cache évite appels DB répétés.

**Procédure** :
1. Activer query logging SQL
2. Faire 10 appels à `AtakRealismConfigRepository::getActiveConfig()`
3. Observer logs

**Résultat attendu** :
- **1 seul** query SQL (cache APCu ou in-memory)
- 9 hits de cache

---

### 4.2 Test : Cache config (JS)

**Objectif** : Vérifier TTL cache côté client.

**Procédure** :
1. Ouvrir Tacmap
2. Console : `await AtakRealismConfig.fetch()`
3. Observer Network tab : 1 appel API
4. Répéter `fetch()` 5 fois dans les 3 minutes
5. Observer Network tab

**Résultat attendu** :
- **1 seul** appel API (cache 3min)
- 4 hits de cache local

---

### 4.3 Test : Charge (100 control measures)

**Objectif** : Vérifier performances avec beaucoup de control measures.

**Procédure** :
1. Créer 100 control measures variés (axes, objectifs, checkpoints)
2. Charger Tacmap
3. Observer temps de rendu

**Résultat attendu** :
- Rendu < 2 secondes
- Pas de lag navigation carte

---

## 5. Checklist validation finale

- [ ] Tous les tests météo passent (1.1 à 1.12)
- [ ] Tous les tests control measures passent (2.1 à 2.16)
- [ ] Tests de régression OK (3.1 à 3.3)
- [ ] Tests de performance OK (4.1 à 4.3)
- [ ] Aucune régression fonctionnelle détectée
- [ ] Admin UI météo : calculateur fonctionne
- [ ] Admin UI control measures : tous les toggles fonctionnent
- [ ] Validation JSON côté serveur : rejette valeurs hors bornes
- [ ] Validation JS côté client : feedback immédiat
- [ ] Documentation à jour (guides utilisateur + dev)

---

## 6. Environnement de test recommandé

**Serveur Arma 3** :
- Version : 2.18 ou supérieure
- Mods : ATHENA C2, CBA_A3, Zeus Enhanced
- Carte : Altis (pour réseau routier complet)

**Serveur web** :
- PHP 8.2+
- MySQL 8.0+
- Extension APCu activée (pour cache config)

**Navigateur** :
- Chrome 120+ ou Firefox 120+
- Console développeur ouverte (observer logs)

**Outils** :
- PHPUnit pour tests unitaires PHP
- Jest pour tests unitaires JS (futur)
- Postman pour tests API manuels

---

## Rapport de bugs

Si un test échoue, documenter :
- Numéro du test
- Résultat attendu vs obtenu
- Logs (PHP error_log, console JS, RPT Arma 3)
- Étapes exactes pour reproduire
- Screenshot ou vidéo si applicable

**Template bug report** :
```markdown
**Test** : 1.6 Vent fort
**Statut** : ❌ FAIL
**Attendu** : Portée 1700m (85%)
**Obtenu** : Portée 2000m (100%)
**Logs** : `[ATHENA] wind_threshold_kmh not found in config`
**Cause probable** : Seed n'a pas migré les nouveaux paramètres vent
**Fix** : Rerun seed avec config complète
```
