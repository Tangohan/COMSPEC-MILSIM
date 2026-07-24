# Système Sons à Distance - Documentation

## Vue d'Ensemble

Le système de sons à distance permet au **commandement web** de jouer des sons sur les joueurs **in-game** en temps réel.

Deux modes disponibles :
- **🎭 Mode Troll** : Sons fun/comiques avec limitations (OFF par défaut)
- **🔊 Mode Réaliste** : Sons immersifs 3D pour améliorer l'expérience (ON par défaut)

---

## 🎯 Cas d'Usage

### Mode Réaliste

**Objectif** : Renforcer l'immersion et la coordination

- 📻 **Radio** : "Ordre incoming", "SITREP demandé"
- 🚨 **Alertes** : Alarmes base, sirènes MEDEVAC, warnings zone
- 💥 **Combat** : Explosions distantes, tirs d'artillerie, avions
- 🚁 **Véhicules** : Hélicos approchant, moteurs, alarmes
- ⛈️ **Météo** : Tonnerre, pluie forte, vent
- 🎯 **Mission** : Objectif capturé, intel découvert, mission complete
- ⚕️ **Médical** : Heartbeat rapide, alerte médicale

**Exemple** : Commandement déclenche "explosion_distant" position [15000, 8000] → Tous joueurs dans 500m entendent l'explosion avec audio 3D directionnel.

---

### Mode Troll

**Objectif** : Fun entre amis, détente après mission

- 📯 **Airhorn** : BWAAAAA classique
- 💥 **Inception** : BWOOOOM dramatique
- 🎵 **Rickroll** : Never gonna give you up
- 🚀 **Yeet** : YEEEET
- 😐 **Bruh** : Bruh moment
- 🙈 **Oh no** : Oh no no no
- 🎉 **Surprise** : Jump scare amical

**Limitations strictes** :
- Désactivé par défaut (joueur doit activer)
- Cooldown 60s entre deux sons
- Maximum 10 sons/heure
- Permissions admin requises

---

## 📊 Architecture

### Workflow Complet

```
Interface Web (Commandement)
  ↓ POST /api/atak/sounds/trigger
Backend PHP (Repository)
  ↓ INSERT atak_remote_sounds (status=pending)
Base de Données MySQL
  ↑ Polling toutes les 5s
  GET /api/atak/sounds/pending?callsign=ALPHA-1
Extension C# (HttpClient)
  ↓ Retourne JSON sons à jouer
Fonction SQF fn_pollRemoteSounds
  ↓ Parse et appelle fn_playRemoteSound
Arma 3 (playSound / say3D)
  ↓ Son joué in-game
  POST /api/atak/sounds/ack (confirmation)
Backend (status=delivered)
```

---

## 🗄️ Base de Données

### Tables (3)

**`atak_remote_sounds`** : Queue sons à jouer
- `sound_type` : 'troll' ou 'realistic'
- `sound_id` : Identifiant du son
- `target_type` : 'player', 'unit', 'group', 'all', 'position'
- `target_identifier` : Callsign ou Steam ID
- `position_x/y/z` : Coordonnées Arma (sons 3D)
- `volume` : 0.0 à 1.0
- `distance_audible` : Portée en mètres
- `status` : pending → delivered / failed / expired
- `expires_at` : Auto-expiration (5min défaut)

**`atak_sound_history`** : Analytics
- Tous sons joués archivés
- Stats par type, joueur, période

**`atak_sound_config`** : Configuration tenant
- `troll_mode_enabled` : ON/OFF global
- `troll_cooldown_seconds` : 60s par défaut
- `troll_max_per_hour` : 10 max
- `realistic_sounds_role` : Permissions

### Vue

**`v_atak_pending_sounds`** : Sons en attente enrichis

### Triggers

**`trg_remote_sound_expire`** : Auto-expiration sons non joués

---

## 🎵 Sons Disponibles

### Troll (15 sons)

| ID | Description | Son Arma |
|----|-------------|----------|
| `airhorn` | Klaxon BWAAAAA | Alarm |
| `inception` | BWOOOOM | RadioAmbient9 |
| `alert_crazy` | Alerte folle | UAV_loop |
| `clown` | Cirque | FD_Finish_F |
| `suspense` | Suspense | UAV_05 |
| `dramatic` | Dramatique | FD_Start_F |
| `victory` | Victoire | FD_CP_Clear_F |
| `fail` | Échec | FD_CP_Not_Clear_F |
| `rickroll` | Rick Astley | RadioAmbient1 |
| `nope` | Nope | UAV_03 |
| `yeet` | YEET | FD_Finish_F |
| `bruh` | Bruh | UAV_loop |
| `ohno` | Oh no | Alarm |
| `surprise` | Surprise | FD_Start_F |
| `cursed` | Maudit | UAV_05 |

---

### Réaliste (35+ sons)

#### Radio / Communications

| ID | Description |
|----|-------------|
| `radio_static` | Friture radio |
| `radio_beep` | Bip radio |
| `radio_squelch` | Squelch |
| `radio_voice_order` | Ordre vocal radio |
| `radio_voice_sitrep` | SITREP radio |
| `radio_voice_medevac` | MEDEVAC radio |

#### Alertes / Alarmes

| ID | Description |
|----|-------------|
| `alarm_base` | Alarme base |
| `alarm_vehicle` | Alarme véhicule |
| `siren_medevac` | Sirène MEDEVAC |
| `siren_qrf` | Sirène QRF |
| `warning_zone` | Alerte zone |
| `warning_critical` | Alerte critique |

#### Explosions / Combat

| ID | Description |
|----|-------------|
| `explosion_distant` | Explosion lointaine |
| `explosion_near` | Explosion proche |
| `gunfire_distant` | Tirs distants |
| `artillery_incoming` | Artillerie arrivant |
| `aircraft_flyby` | Avion passant |

#### Véhicules

| ID | Description |
|----|-------------|
| `vehicle_engine_start` | Moteur démarrant |
| `vehicle_alarm` | Alarme véhicule |
| `helicopter_approach` | Hélico approchant |
| `helicopter_landing` | Hélico atterrissant |

#### Environnement

| ID | Description |
|----|-------------|
| `thunder` | Tonnerre |
| `rain_heavy` | Pluie forte |
| `wind_strong` | Vent fort |
| `ambient_forest` | Ambiance forêt |
| `ambient_urban` | Ambiance urbaine |

#### Événements Mission

| ID | Description |
|----|-------------|
| `mission_start` | Début mission |
| `mission_complete` | Mission terminée |
| `objective_captured` | Objectif capturé |
| `objective_lost` | Objectif perdu |
| `intel_discovered` | Intel découvert |

#### Médical

| ID | Description |
|----|-------------|
| `heartbeat_fast` | Battement cœur rapide |
| `flatline` | Arrêt cardiaque |
| `medical_alert` | Alerte médicale |

#### Notifications

| ID | Description |
|----|-------------|
| `notif_incoming` | Notification incoming |
| `notif_urgent` | Notif urgente |
| `notif_critical` | Notif critique |

---

## 🔧 Configuration

### ⚠️ Activation Requise (OFF par Défaut)

**Le système est COMPLÈTEMENT DÉSACTIVÉ par défaut.**

Pour l'utiliser :
1. **CBA Settings** → COMSPEC Overwatch → ATAK Sons
2. ☑ **Sons Réalistes Activés** (ou Mode Troll)
3. **Intervalle Polling** : Mettre à **5** secondes (ou plus)
4. Redémarrer mission (ou wait settings update)

**Pourquoi OFF par défaut ?**
- Évite sons non désirés
- Joueur contrôle total
- Compatible avec tous mods
- Opt-in explicite

---

### CBA Settings Joueur

**COMSPEC Overwatch → ATAK Sons**

| Setting | Défaut | Description |
|---------|--------|-------------|
| Mode Troll Activé | ☐ OFF | Autoriser sons troll |
| **Volume Sons Troll** | 50% | Volume sons troll (0-100%) |
| Sons Réalistes Activés | ☐ OFF | Autoriser sons réalistes |
| **Volume Sons Réalistes** | 80% | Volume sons réalistes (0-100%) |
| Intervalle Polling (s) | 0 (OFF) | Fréquence check API (0 = désactivé) |

**⚠️ IMPORTANT : Système désactivé par défaut !**

Pour activer :
1. **Sons Réalistes** : ☑ Activer + Intervalle > 0 (recommandé : 5s)
2. **Sons Troll** : ☑ Activer Mode Troll + Intervalle > 0
3. **Ajuster volumes** : Sliders 0-100% (50% troll, 80% réaliste par défaut)

**Volumes indépendants** :
- Volume troll et réaliste séparés
- Mettez à 0 pour mute un mode sans le désactiver
- Volume API (1.0) × Volume Setting = Volume final

**Note** : Même si activé côté joueur, le mode troll doit aussi être activé côté serveur.

---

### Configuration Serveur (Admin)

**Via SQL** :

```sql
-- Activer mode troll pour tenant 1
INSERT INTO atak_sound_config (tenant_id, troll_mode_enabled)
VALUES (1, TRUE)
ON DUPLICATE KEY UPDATE troll_mode_enabled = TRUE;

-- Modifier cooldown troll (défaut 60s)
UPDATE atak_sound_config
SET troll_cooldown_seconds = 30
WHERE tenant_id = 1;

-- Modifier limite horaire (défaut 10)
UPDATE atak_sound_config
SET troll_max_per_hour = 20
WHERE tenant_id = 1;
```

**Via Interface Web** (à implémenter) :
- Admin panel → ATAK Configuration → Sons à Distance

---

## 📡 API Endpoints

### 1. Déclencher Son

**Endpoint** : `POST /api/atak/sounds/trigger`

**Headers** :
```
Content-Type: application/json
X-ATAK-Token: [TOKEN]
```

**Body** :
```json
{
  "sound_type": "realistic",
  "sound_id": "explosion_distant",
  "target_type": "all",
  "position": [15234.56, 8765.43, 0],
  "volume": 1.0,
  "distance_audible": 500,
  "reason": "Bombardement zone nord"
}
```

**Réponse** :
```json
{
  "success": true,
  "sound_id": 123,
  "message": "Son déclenché avec succès"
}
```

---

### 2. Polling Sons Pending (Client Mod)

**Endpoint** : `GET /api/atak/sounds/pending`

**Params** :
- `callsign` : Callsign joueur
- `steam_id` : Steam ID
- `position` : Position actuelle (optionnel)

**Réponse** :
```json
[
  {
    "id": 123,
    "sound_type": "realistic",
    "sound_id": "explosion_distant",
    "position": [15234.56, 8765.43, 0],
    "volume": 1.0,
    "distance_audible": 500,
    "triggered_by_user_id": 5,
    "triggered_at": "2026-07-24 14:00:00",
    "reason": "Bombardement zone nord"
  }
]
```

---

### 3. Acknowledge Son Joué (Client Mod)

**Endpoint** : `POST /api/atak/sounds/ack`

**Body** :
```json
{
  "sound_id": 123
}
```

**Réponse** :
```json
{
  "success": true
}
```

---

## 🎮 Utilisation In-Game

### Automatique (Recommandé)

Le système fonctionne **automatiquement** une fois configuré :

1. Admin web déclenche son
2. Mod polling toutes les 5s
3. Son joué automatiquement
4. Feedback visuel/sonore

**Aucune action joueur requise** pendant mission.

---

### Manuelle (Debug)

Pour tester manuellement :

```sqf
// Test son troll 2D
["troll", "airhorn"] call comspec_overwatch_connect_fnc_playRemoteSound;

// Test son réaliste 3D à position
["realistic", "explosion_distant", [15000, 8000, 0], 1, 500] call comspec_overwatch_connect_fnc_playRemoteSound;

// Test son 2D global
["realistic", "alarm_base"] call comspec_overwatch_connect_fnc_playRemoteSound;
```

---

## 🔒 Sécurité

### Protections Côté Serveur

✅ **Mode troll** : OFF par défaut tenant  
✅ **Cooldown** : 60s entre sons troll (configurable)  
✅ **Limite horaire** : 10 sons troll/heure max  
✅ **Permissions** : Rôle requis (admin par défaut)  
✅ **Expiration** : Sons auto-expirés après 5min  
✅ **Validation** : sound_id whitelist (pas de sons arbitraires)

### Protections Côté Client

✅ **Opt-in** : Joueur active explicitement mode troll  
✅ **Settings CBA** : Peut désactiver complètement  
✅ **Filtrage** : Sons troll ignorés si setting OFF  
✅ **Logs** : Tous sons loggés dans RPT

### Anti-Abuse

- **Cooldown global** : Empêche spam
- **Limite horaire** : Empêche abus répété
- **Historique** : Tous sons tracés avec auteur
- **Cleanup auto** : Sons anciens supprimés (24h)

---

## 📊 Analytics

### Stats Disponibles

```sql
-- Sons joués dernières 7 jours
SELECT * FROM atak_sound_history
WHERE played_at > DATE_SUB(NOW(), INTERVAL 7 DAY);

-- Utilisateur déclenchant le plus de sons troll
SELECT triggered_by_user_id, COUNT(*) as count
FROM atak_sound_history
WHERE sound_type = 'troll'
GROUP BY triggered_by_user_id
ORDER BY count DESC
LIMIT 10;

-- Sons les plus populaires
SELECT sound_id, COUNT(*) as count
FROM atak_sound_history
GROUP BY sound_id
ORDER BY count DESC
LIMIT 20;
```

### Dashboard (À Implémenter)

- Graphique sons/jour (troll vs réaliste)
- Top 10 sons plus joués
- Top déclencheurs
- Taux acknowledge
- Latence moyenne déclenchement→lecture

---

## 🐛 Troubleshooting

### "Mode troll désactivé, son ignoré"

**Cause** : Mode troll OFF côté serveur ou client

**Solution** :
1. Activer côté serveur (SQL ou admin panel)
2. Joueur active dans CBA Settings
3. Relancer mission

---

### "Cooldown troll actif"

**Cause** : Son troll déclenché < 60s avant

**Solution** : Attendre fin cooldown

---

### "Limite horaire atteinte"

**Cause** : 10 sons troll déjà déclenchés dernière heure

**Solution** : Attendre 1h ou augmenter limite serveur

---

### "Extension not found"

**Cause** : Extension C# non chargée

**Solution** : Voir troubleshooting extension (GUIDE-INSTALLATION-TEST.md)

---

### "Pas de sons reçus"

**Causes possibles** :
1. Polling désactivé (interval = 0)
2. Token invalide
3. Pas de sons pending API

**Debug** :
```sqf
// Forcer polling manuel
[] call comspec_overwatch_connect_fnc_pollRemoteSounds;

// Check dernière poll
diag_log format ["Last poll: %1", missionNamespace getVariable ["COMSPEC_LastSoundPoll", 0]];
```

---

## 🚀 Évolutions Futures

### Phase 2

- [ ] **Playlists** : Séquences sons (ambiance mission)
- [ ] **Sons custom** : Upload fichiers .ogg personnalisés
- [ ] **Triggers automatiques** : Sons sur événements (objectif capturé → victory)
- [ ] **Zones audio 3D** : Sons looping dans zones (ambiance usine)
- [ ] **Voice TTS** : Conversion texte→voix pour ordres vocaux

### Phase 3

- [ ] **Equalizer** : Filtres audio (low-pass pour radio)
- [ ] **Echo/Reverb** : Effets selon environnement (intérieur vs extérieur)
- [ ] **Attenuation** : Volume décroissant avec distance
- [ ] **Occlusion** : Sons bloqués par bâtiments
- [ ] **Doppler** : Effet Doppler véhicules/avions

---

## 📝 Notes Développeur

### Ajouter Nouveau Son

**1. Côté SQF** (`fn_playRemoteSound.sqf`) :

```sqf
// Dans _trollSounds ou _realisticSounds
private _trollSounds = createHashMapFromArray [
    // ... sons existants
    ["nouveau_son", "ClasseSonArma"]
];
```

**2. Documentation** : Ajouter dans ce fichier section "Sons Disponibles"

**3. Test** :
```sqf
["troll", "nouveau_son"] call comspec_overwatch_connect_fnc_playRemoteSound;
```

---

### Maintenance BDD

**Cleanup manuel** :
```sql
CALL sp_cleanup_old_remote_sounds();
```

**Désactiver cleanup auto** :
```sql
DROP EVENT evt_cleanup_remote_sounds;
```

---

## ✨ Résumé

**Système sons à distance = Immersion + Fun (Opt-In)**

- ✅ **OFF par défaut** : Joueur active explicitement
- ✅ **2 modes** : Troll (opt-in) + Réaliste (opt-in)
- ✅ **50+ sons** : 15 troll + 35 réalistes
- ✅ **Audio 3D** : Sons positionnés dans le monde
- ✅ **Sécurité** : Cooldowns, limites, permissions
- ✅ **Analytics** : Historique complet
- ✅ **Contrôle total** : Joueur choisit ce qu'il accepte

**Activation** :
1. CBA Settings → ATAK Sons
2. Activer mode(s) désiré(s)
3. Intervalle Polling > 0 (ex: 5s)

**Utilisation recommandée** :
- Mode Réaliste : Missions immersives (si désiré)
- Mode Troll : Events spéciaux (si désiré)
- Désactivé : Par défaut (aucun son)

---

**Version** : 1.0  
**Date** : 24 juillet 2026  
**Auteur** : COMSPEC
