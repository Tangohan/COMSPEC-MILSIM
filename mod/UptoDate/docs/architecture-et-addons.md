# Architecture du mod COMSPEC Overwatch

Vue d’ensemble pour **moddeurs** et **intégrateurs** — sans détail des structures de stockage portail.

---

## Structure du dépôt build

```text
mod/UptoDate/
├── Sources/comspec-overwatch-addons/
│   ├── main/           → comspec_overwatch_main.pbo
│   ├── connect/        → comspec_overwatch_connect.pbo  (cœur liaison)
│   ├── atak_athena/    → comspec_overwatch_atak_athena.pbo (pont cTab/BCE)
│   └── mavik_compat/   → comspec_overwatch_mavik_compat.pbo
├── COMSPECExtension/   → COMSPECExtension_x64.dll (C# Native AOT)
├── @COMSPECOverwatch/  → sortie build (PBO + DLL + mod.cpp)
├── build_mod.bat
├── workshop-pack.ps1
└── docs/               → cette documentation
```

Préfixe PBO : `z\comspec_overwatch\addons\{main|connect|atak_athena|mavik_compat}`

---

## Addons — responsabilités

### main

- Métadonnées pack, logos, patch minimal
- Peu de logique runtime

### connect (addon principal)

| Domaine | Exemples |
|---|---|
| Liaison Athena | Connexion, position, heartbeat, déconnexion |
| Hub & UI | Dialogs hub, tablette, téléphone, rapports |
| Roleplay 1.3.0 | canTransmit, zones, dommages, overlays |
| Rapports & intel | SALUTE, SPOTREP, POI, photos recon |
| ACE | Menus tactiques, réparation ATAK |
| Crash recovery | HandleDisconnect, restore session |

Point d’entrée : **CBA Extended** (`XEH_preInit`, `XEH_postInit`, `XEH_postInitClient`).

### atak_athena

- Pont **Iceman / BCE / cTab** (variables publiques, events CBA)
- Photos, marqueurs, feeds, alertes — **API mod tiers uniquement**
- Ne pas dupliquer le code BCE

### mavik_compat

- Charge uniquement si **Mavic_Core** présent
- Évite conflits settings CBA drone

---

## Extension native (DLL)

**COMSPECExtension_x64.dll** — HTTP(S) vers Athena, callbacks async.

| Famille | Rôle |
|---|---|
| Auth | Connect, clé, Steam |
| Position | UpdatePosition + métadonnées JSON |
| Polls | Ordres, CAS, alertes, cartes, roleplay config |
| Upload | Photos recon, marqueurs, rapports |
| Realism | Terminal, certificat |
| Session | Restauration post-déconnexion |

Communication SQF : `callExtension` + `ExtensionCallback` (Connected, Error, NetworkDisconnected…).

Format retour : `OK|payload` ou `ERR|code` — payloads souvent **tabulation / lignes** (pas JSON côté SQF).

---

## Flux principal (liaison)

```text
[Client Arma]
    → fn_connect / handshake
    → COMSPECExtension Connect
    → Portail Athena (auth communauté)
    → COMSPEC_AthenaReady = true
    → startSyncLoops (position, polls, roleplay)
    → fn_updatePosition (canTransmit → extension)
    → Tacmap / TOC mise à jour
```

---

## Roleplay 1.3.0 — fichiers clés (connect)

| Fichier | Rôle |
|---|---|
| `fn_canTransmit.sqf` | Gate envoi liaison |
| `fn_isAtakFunctional.sqf` | État terminal |
| `fn_checkAtakDamage.sqf` | Dommages combat |
| `fn_applyZoneEffects.sqf` | Zones + effets visuels |
| `fn_updateAtakEnhancedRoleplay.sqf` | Overlays hub |
| `fn_pollRoleplayConfig.sqf` | Sync config portail |
| `fn_initCrashRecovery.sqf` | Reprise serveur |
| `display_hub.hpp` | IDC overlays 9200–9204 |
| `modules/module_roleplay_zone.hpp` | Modules Eden |

---

## Intégrations mods tiers

| Mod | Méthode |
|---|---|
| ACE Medical | Variables ace_medical_* |
| KAT Medical | Branche optionnelle SpO2, airway, pneumothorax |
| cTab / BCE | atak_athena, Iceman_* |
| Mavic | mavik_compat |

**Licence** : ne pas copier assets/SQF tiers ; consommer API publique uniquement.

---

## Portail Athena (complément)

Le mod est la moitié client d’un système **client ↔ API ↔ UI web**.

Responsabilités portail (hors PBO) :

- Authentification communauté
- Carte Tacmap / TOC
- Configuration roleplay, zones, certificats
- Stockage photos, rapports, POI, positions
- Reprise session (snapshot courte durée)

Les guides **joueur** ne documentent que le comportement visible.

---

## Conventions SQF

- Tag fonctions : `comspec_overwatch_connect_fnc_*`
- Variables mission : préfixe `COMSPEC_`
- UI sans jargon (règle projet)
- `profileNamespace` : persistance terminal_uid, callsign

---

## Dépendances

**Required addons (connect)** : `cba_main`, `cba_xeh`, `cba_settings`, `comspec_overwatch_main`, `A3_Modules_F` (modules roleplay)

---

## Voir aussi

- [Compilation & publication](compilation-et-publication.md)
- [Réalisme liaison](realisme-liaison-atak.md)
- CHANGELOG pack : `@COMSPECOverwatch/CHANGELOG.md`
