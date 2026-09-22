# Glossaire — Les trois "réalismes" ATHENA/ATAK

Ce document clarifie la terminologie autour des concepts de "réalisme" dans ATHENA C2, qui peuvent prêter à confusion car ils utilisent le même mot pour des fonctionnalités distinctes.

## 1. `realism` (Expérience générale)

**Emplacement** : `tenant_experience.realism` (booléen)  
**Portée** : Ensemble de la plateforme ATHENA  
**Description** : Indicateur général d'expérience "réaliste" qui influence plusieurs comportements transverses :
- Notifications à l'écran (on/off)
- Menus ACE (accessibles depuis le poste ou non)
- Composition d'ordres (depuis le poste ou non)
- Affichage du temps de jeu
- Flux ATHENA (affichage des événements système)

**Valeurs** :
- `false` (par défaut) : Mode permissif, toutes les fonctionnalités disponibles depuis le poste
- `true` : Mode strict, certaines fonctionnalités nécessitent d'être en jeu

**Utilisation** :
```php
$experience = $experienceService->getExperience($tenantId);
$isRealisticMode = $experience['realism'] ?? false;
```

---

## 2. `atak_realism` (Dommages au terminal ATAK)

**Emplacement** : `tenant_experience.atak_realism` (tri-state: 0/1/2 ou 'off'/'player'/'server')  
**Portée** : Module de dommages au terminal ATAK dans le mod Arma 3  
**Description** : Simule la dégradation ou destruction du terminal ATAK du joueur suite à des dommages physiques (blessures, impacts). Gère :
- Destruction de l'écran (seuil de dommage + probabilité)
- Extinction temporaire (shutdown)
- Dysfonctionnements suite à blessure au bras (durée variable)
- Dysfonctionnements suite à blessure au torse (3 niveaux de gravité)
- Interactions avec ACE Medical et KAT (pneumothorax, SpO2)

**Valeurs** :
- `0` ou `'off'` : Désactivé, le terminal est indestructible
- `1` ou `'player'` : Le joueur choisit dans les options CBA
- `2` ou `'server'` : Forcé actif côté serveur, le joueur ne peut pas désactiver

**Utilisation** :
```sqf
_realismLevel = ["comspec_athena", "atak_realism", 0] call CBA_fnc_getServerSetting;
if (_realismLevel > 0) then {
    // Appliquer la logique de dommages au terminal
    call comspec_athena_atak_fnc_checkAtakDamage;
};
```

**Fichiers concernés** :
- `mod/.../functions/atak/fn_checkAtakDamage.sqf`
- `app/Controllers/Api/AtakRealismApiController.php` (API read-only)
- Extension C# : `SimplifyTerminalRealismJson()` (mapping des seuils et probabilités)

---

## 3. Simulation réseau / zones roleplay (Roleplay Network)

**Emplacement** : `tenant_atak_config.network_*` (multiples colonnes) + zones roleplay  
**Portée** : Infrastructure réseau simulée (portail + client) + zones géographiques à effets  
**Description** : Simule des conditions réseau dégradées et des zones tactiques à effets spéciaux. Comprend deux sous-systèmes :

### 3.1 Simulation réseau portail (côté serveur PHP)
Injecte artificiellement des dysfonctionnements réseau côté API ATHENA :
- Latence variable (min/max en ms)
- Perte de paquets (pourcentage)
- Déconnexions temporaires (durée min/max, intervalle)
- Modes contextuels (normal, hostile, degraded, equipment)

**Colonnes DB** :
- `tenant_atak_config.network_enabled` (booléen)
- `tenant_atak_config.network_mode` (string: normal/hostile/degraded/equipment)
- `tenant_atak_config.latency_min_ms`, `latency_max_ms` (int)
- `tenant_atak_config.packet_loss_percent` (float)
- `tenant_atak_config.disconnect_enabled` (booléen)
- `tenant_atak_config.disconnect_min_sec`, `disconnect_max_sec`, `disconnect_interval_sec` (int)

### 3.2 Simulation client (côté mod SQF, via CBA)
Paramètres hardcodés dans le mod qui simulent des coupures et pertes de données côté client :
- Première déconnexion (180-420s après le début de partie)
- Déconnexions suivantes (240-600s entre chaque)
- Durée des coupures (4-22s)
- Perte aléatoire de TX (8 % de chance par envoi)
- Perte de fond plancher (12 % base + 0-10 % aléatoire)

**Variables CBA** :
- `comspec_athena_disconnect_first_min`, `comspec_athena_disconnect_first_max`
- `comspec_athena_disconnect_next_min`, `comspec_athena_disconnect_next_max`
- `comspec_athena_disconnect_duration_min`, `comspec_athena_disconnect_duration_max`
- `comspec_athena_drop_chance_tx`
- `comspec_athena_loss_floor_base`, `comspec_athena_loss_floor_random`

### 3.3 Zones roleplay géographiques
Zones géographiques 2D (cercles définis par centre X/Y + rayon) appliquant des effets locaux :
- `no_coverage` : Aucune couverture réseau (intensité 100)
- `interference` : Interférences radio (intensité 50)
- `degraded` : Signal dégradé (intensité 30)
- `jammer` : Brouillage actif (intensité 80)

**Table DB** : `tenant_atak_roleplay_zones`  
**Colonnes** :
- `center_x`, `center_y` (float, coordonnées carte)
- `radius` (float, rayon en mètres, défaut 200m)
- `effect` (string, type d'effet)

**Fonction SQF** :
```sqf
[_x, _y, _radius, _effect] call comspec_athena_atak_fnc_createRoleplayZone;
```

**UI Admin** : `views/admin/atak/roleplay.php` (gestion des zones + simulation réseau)

### 3.4 Relais radio obligatoires
Sous-fonctionnalité liée : obligation de passer par un relais intact pour la liaison de données.

**Colonne DB** : `tenant_atak_config.link_via_relays` (booléen)  
**Description** : Si activé, le mod vérifie qu'un relais intact est à portée avant d'autoriser l'envoi de données vers le portail.

---

## Résumé visuel

| Concept | Variable clé | Portée | Géré où ? |
|---------|-------------|--------|----------|
| **1. Réalisme général** | `tenant_experience.realism` | Global ATHENA | PHP/JS (expérience) |
| **2. Dommages terminal** | `tenant_experience.atak_realism` | Module ATAK mod | SQF + Extension C# + PHP API |
| **3. Simulation réseau** | `tenant_atak_config.network_*` | Infrastructure réseau | PHP (portail) + SQF (client) |
| **3. Zones roleplay** | `tenant_atak_roleplay_zones.*` | Zones géographiques | SQF + PHP admin |
| **3. Relais obligatoires** | `tenant_atak_config.link_via_relays` | Infrastructure relais | SQF + PHP admin |

---

## Incohérences résolues par la centralisation

**Avant** : Ces trois concepts partageaient le mot "realism" mais étaient dispersés dans des tables et fichiers différents, causant confusion et duplication.

**Après (plan de centralisation)** : Tous ces paramètres seront regroupés dans `atak_realism_config.config_json` avec une structure claire par domaine :
- `experience_ambiance.realism_mode` (ex-`realism`)
- `terminal_damage.atak_realism_level` (ex-`atak_realism`)
- `network_simulation.portal_*` + `network_simulation.client_*` (ex-`network_*`)
- `zones_roleplay.*` (zones géographiques)
- `radio_relays.link_via_relays` (relais obligatoires)

---

## Voir aussi

- [Audit complet paramètres réalisme ATAK](./audit-realisme-complet-proposition-centralisation.md)
- [Plan d'implémentation centralisation](../../opt/cursor/artifacts/plans/centralisation_config_realisme_atak_8950293d.plan.md)
- Code: `app/Services/AtakExperienceService.php`, `app/Repositories/TenantAtakConfigRepository.php`
- Mod: `mod/.../functions/atak/fn_syncAtakRealism.sqf`, `fn_checkAtakDamage.sqf`
