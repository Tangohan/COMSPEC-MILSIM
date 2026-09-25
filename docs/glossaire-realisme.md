# Glossaire — Paramètres de réalisme ATAK

**Date** : 25 septembre 2026  
**Contexte** : ATHENA C2 (COMSPEC-MILSIM) · Module Overwatch

---

## Vue d'ensemble

Le système ATHENA/Overwatch dispose de plusieurs **couches de réalisme indépendantes** qui peuvent être activées séparément ou combinées. Ce glossaire clarifie les différences entre ces concepts souvent confondus.

---

## 1. `realism` — Mode immersion communauté

**Type** : Expérience utilisateur (UX)  
**Portée** : Toute la communauté  
**Défaut** : `false` (désactivé)

### Description
Mode d'expérience immersive qui réduit les aides visuelles et les alertes de confort pour une expérience plus réaliste.

### Effets concrets
- ✅ Moins d'indicateurs à l'écran
- ✅ Moins de notifications "confort"
- ✅ Interface plus épurée
- ❌ Ne touche PAS aux dommages physiques du terminal
- ❌ Ne touche PAS au réseau

### Mutuellement exclusif avec
- `troll` (mode fun) : Si `realism=true`, alors `troll` est forcé à `false`

### Où le régler
- **Admin** : Écran "Règles de mission" (server_control.php)
- **Joueur** : Settings CBA in-game

---

## 2. `atak_realism` — Niveau de dommages terminal

**Type** : Simulation physique  
**Portée** : Terminal ATAK (téléphone tactique)  
**Défaut** : `0` (désactivé) ou `"player"` (choix joueur)

### Description
Simule les **dommages physiques** subis par le terminal ATAK selon les blessures du porteur, les chocs, ou les conditions de combat.

### Niveaux disponibles
| Niveau | Description | Effets |
|--------|-------------|--------|
| `0` | **Désactivé** | Terminal indestructible |
| `1` | **Extinction temporaire** | Le terminal peut s'éteindre temporairement (20-45s) suite à un choc ou blessure |
| `2` | **Écran inutilisable** | L'écran peut être détruit (GPS continue de fonctionner) |
| `3` | **Device détruit** | Le terminal peut être complètement détruit + déconnexion |
| `"player"` | **Choix mission** | Laisse la mission décider via CBA settings |

### Seuils de dommages (niveau ≥1)
- **Choc écran** : Impact > 0.25 → 50% chance destruction écran (si niveau ≥2)
- **Choc extinction** : Impact > 0 → 40% chance extinction temporaire
- **Bras blessé** : Dommages bras > 0.65 → 25% chance de ne plus pouvoir tenir le terminal (45s)
- **Torse niveau 1** : Dommages > 0.5 → 30% chance extinction 30s
- **Torse niveau 2** : Dommages > 0.7 → 40% chance écran détruit
- **Torse niveau 3** : Dommages > 0.8 → 50% chance device détruit
- **KAT pneumothorax** : Aggravation si complications thoraciques ; SpO2 < 85 rend le rythme cardiaque affiché peu fiable

### Où le régler
- **Admin** : Écran "Règles de mission" ou futur écran "Dommages terminal"
- **Joueur** : CBA settings `comspec_overwatch_atak_realism` (si mode `"player"`)

### Relation avec `realism`
**AUCUNE** : Ce sont deux systèmes indépendants. Vous pouvez avoir :
- `realism=true` (UI épurée) + `atak_realism=0` (terminal indestructible)
- `realism=false` (UI normale) + `atak_realism=3` (terminal fragile)

---

## 3. Réseau roleplay — Simulation liaison

**Type** : Simulation réseau  
**Portée** : Liaison de données entre terminal et serveur  
**Défaut** : Tous désactivés

### Composants indépendants

#### 3.1. `roleplay_network_enabled` — Simulation réseau portail (serveur)
Simule des **dégradations réseau côté serveur** :
- **Latence** : `latency_min_ms` à `latency_max_ms` (défaut 0-0 ms)
- **Pertes de paquets** : `packet_loss_percent` (défaut 0%)
- **Coupures périodiques** : Si `disconnect_enabled=true`
  - Durée : `disconnect_min_sec` à `disconnect_max_sec` (défaut 5-30s)
  - Intervalle : `disconnect_interval_sec` (défaut 600s = 10 min)

#### 3.2. `comspec_overwatch_link_degrade_sim` — Simulation liaison client (mod)
Simule des **dégradations réseau côté mod** (in-game) :
- **Pertes paquets aléatoires** : `tx_drop_chance` (défaut 8%)
- **Coupures périodiques** :
  - 1ère coupure : `180 + random 240` s après début mission
  - Durée coupure : `4 + random 18` s
  - Intervalle suivant : `240 + random 360` s

#### 3.3. Zones roleplay géographiques (`zones_enabled`)
Crée des **zones géographiques** sur la carte qui appliquent des effets réseau localisés :
- **Sans couverture** (`no_coverage`) : +2000ms latence, 90-100% pertes
- **Interférence** (`interference`) : +500ms latence, 30-50% pertes
- **Dégradé** (`degraded`) : +200ms latence, 10-20% pertes
- **Brouilleur** (`jammer`) : +1000ms latence, 50-80% pertes + crash terminal possible (si intensité ≥65)

### ⚠️ ATTENTION : Cumul des effets

Les trois systèmes (portail + client + zones) **s'additionnent** ! Si vous activez les trois simultanément, vous risquez une **double ou triple pénalité** réseau.

**Exemple de cumul** :
- Portail : +100ms latence + 10% pertes + coupure toutes les 10 min (30s)
- Client : 8% drop + coupure aléatoire (4-22s) toutes les 4-10 min
- Zone interférence : +500ms + 30-50% pertes

**Résultat** : Réseau quasi inutilisable (600ms+ latence, 48-68% pertes, coupures multiples)

### Où le régler
- **Admin** : Écran "Simulation réseau et capteurs" (roleplay.php)
- **Joueur** : CBA settings pour `link_degrade_sim` client seulement

---

## 4. `link_via_relays` — Exigence relais

**Type** : Règle de connectivité  
**Portée** : Liaison de données  
**Défaut** : `false` (liaison libre)

### Description
Si activé, les joueurs **doivent être à proximité d'un relais actif** pour transmettre des données vers le serveur. La voix (radio) n'est PAS concernée.

### Portée relais
- **Défaut** : 2000 m
- **Min/Max** : 50-8000 m (clamped)
- **Dégradation** : Le débit diminue avec la distance et les dégâts du relais

### Relation avec autres paramètres
- **Indépendant** de `realism`, `atak_realism`, et simulation réseau
- **Complémentaire** avec zones roleplay : une zone "sans couverture" + `link_via_relays=true` = dépendance totale aux relais

### Où le régler
- **Admin** : Écran "Simulation réseau et capteurs" (roleplay.php)
- **Anciennement** : Était aussi dans server_control.php (maintenant supprimé pour éviter duplication)

---

## 5. Capteurs — Défaillance équipement médical

**Type** : Simulation capteurs  
**Portée** : Affichage rythme cardiaque (HR)  
**Défaut** : Désactivé

### Paramètres
- `sensor_enabled` : Active les défaillances
- `sensor_failure_percent` : % échec complet (pas de données)
- `sensor_error_percent` : % valeurs erronées
- `sensor_missing_percent` : % données manquantes

### Où le régler
- **Admin** : Écran "Simulation réseau et capteurs" (roleplay.php)

---

## Résumé comparatif

| Paramètre | Cible | Impact | Indépendant de |
|-----------|-------|--------|----------------|
| `realism` | UX | Interface épurée | Tous les autres |
| `atak_realism` | Terminal | Dommages physiques device | `realism`, réseau |
| `roleplay_network_enabled` | Réseau serveur | Latence/pertes/coupures côté portail | `atak_realism` |
| `link_degrade_sim` | Réseau client | Pertes/coupures côté mod | Simulation portail |
| `zones_enabled` | Zones géo | Effets réseau localisés | Simulations globales |
| `link_via_relays` | Connectivité | Exigence relais pour transmettre | `realism`, dommages |
| `sensor_enabled` | Capteurs | Défaillance équipement médical | Tous les autres |

---

## Recommandations d'usage

### Configuration "Débutant"
```
realism = false
atak_realism = 0
roleplay_network_enabled = false
link_degrade_sim = false
zones_enabled = false
link_via_relays = false
sensor_enabled = false
```
**Résultat** : Expérience arcade, aucune contrainte.

### Configuration "Réaliste modéré"
```
realism = true (UI épurée)
atak_realism = 1 (extinctions temporaires)
roleplay_network_enabled = false
link_degrade_sim = false
zones_enabled = true (quelques zones interférence)
link_via_relays = false
sensor_enabled = false
```
**Résultat** : Immersion visuelle + risque léger d'extinction terminal + zones tactiques.

### Configuration "Hardcore"
```
realism = true
atak_realism = 2 ou 3 (écran/device destructible)
roleplay_network_enabled = true (latence+pertes légères)
link_degrade_sim = false (⚠️ ne pas cumuler avec portail)
zones_enabled = true
link_via_relays = true (relais obligatoires)
sensor_enabled = true
```
**Résultat** : Maximum de contraintes, expérience MILSIM complète.

### ⚠️ Configuration à éviter (trop punitive)
```
roleplay_network_enabled = true (coupures 10 min)
link_degrade_sim = true (coupures 4-10 min)
zones_enabled = true (pertes 30-50%)
```
**Résultat** : Triple pénalité réseau = liaison inutilisable.

---

## Changelog

- **2026-09-25** : Création du glossaire (Phase 0 plan migration)
- **2026-09-22** : Audit complet ~100 paramètres (PR #545)

---

**Maintenance** : Ce glossaire sera intégré dans la future interface de configuration centralisée (Phase 2).
